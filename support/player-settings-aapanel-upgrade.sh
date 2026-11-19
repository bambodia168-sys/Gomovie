#!/usr/bin/env bash
# Install the reviewed player settings and the assets built by its CI run.
set -Eeuo pipefail
umask 077

if [[ $# -ne 2 || ! "$1" =~ ^[0-9a-f]{40}$ || ! "$2" =~ ^[0-9]+$ ]]; then
    echo 'Usage: bash player-settings-aapanel-upgrade.sh RELEASE_SHA CI_RUN_ID' >&2
    exit 2
fi

release="$1"
run="$2"
repo='bambodia168-sys/Gomovie'
base='a827494592f5b3d60357698d7bbbb7eec9854034'
host='gomovie.tonsamith.com'
site="/www/wwwroot/$host"
migration='database/migrations/2026_11_19_000100_add_engine_and_ad_tag_to_player_preferences.php'
files=(
    "$migration"
    app/Support/PlayerPreferences.php
    app/Http/Controllers/Admin/PlayerSettingsController.php
    resources/views/admin/player-settings.blade.php
    resources/views/frontend/detail.blade.php
    resources/css/app.css
    resources/css/studio.css
    resources/css/plyr-player.css
    resources/js/player.js
    resources/js/player-ads.js
    resources/js/plyr-player.js
    package.json
)
manifests=(public/build/fonts-manifest.json public/build/manifest.json)

fail() {
    printf 'GOMOVIE_PLAYER_SETTINGS_ERROR=%s\n' "$*" >&2
    exit 1
}

[[ "$(id -u)" = 0 ]] || fail 'Run this updater from the aaPanel root terminal.'
[[ -f "$site/artisan" && -f "$site/vendor/autoload.php" && -d "$site/public/build/assets" ]] \
    || fail 'The GoMovie installation or its current frontend build is missing.'
php_version="$(sed -nE 's/^[[:space:]]*include[[:space:]]+[^;]*enable-php-(84|85)[.]conf[[:space:]]*;.*$/\1/p' \
    "/www/server/panel/vhost/nginx/$host.conf" 2>/dev/null | head -n1 || true)"
php="/www/server/php/${php_version:-85}/bin/php"
[[ -x "$php" ]] || fail 'The GoMovie PHP interpreter was not found.'

ci="$(gh api "repos/$repo/actions/runs/$run" --jq '[.head_sha, .name, .conclusion] | join("|")')"
[[ "$ci" = "$release|Gomovie CI|success" ]] || fail 'The build must come from the successful GoMovie CI run for this release.'

exec 9> /run/lock/gomovie-player-settings.lock
flock -n 9 || fail 'A GoMovie player settings deployment is active.'
mkdir -p /var/backups/gomovie
work="$(mktemp -d /var/backups/gomovie/player-settings.XXXXXXXX)"
echo "BACKUP_DIRECTORY=$work"
phase=prepare-sources
applied=()
complete=0

put_file() {
    local source="$1" target="$2" staged
    staged="$(mktemp "$(dirname "$target")/.gm-player-settings.XXXXXXXX")" || return 1
    if cp -p -- "$source" "$staged" && mv -fT -- "$staged" "$target"; then
        return 0
    fi
    rm -f -- "$staged"
    return 1
}

on_exit() {
    local result=$? restored=1 file
    trap - EXIT HUP INT TERM
    if (( complete )); then return; fi
    set +e
    printf 'GOMOVIE_PLAYER_SETTINGS_DEPLOYMENT=FAILED phase=%s exit=%s\n' "$phase" "$result" >&2
    if (( ${#applied[@]} > 0 )); then
        for file in "${applied[@]}"; do
            if [[ -f "$work/original/$file" ]]; then
                put_file "$work/original/$file" "$site/$file" || restored=0
            elif [[ "$file" != "$migration" ]]; then
                rm -f -- "$site/$file" || restored=0
            fi
        done
        (cd "$site" && runuser -u www -- "$php" artisan view:clear --no-interaction) >/dev/null 2>&1 || restored=0
        (( restored )) && echo 'SOURCE_ROLLBACK=COMPLETE' || echo 'SOURCE_ROLLBACK=INCOMPLETE'
    fi
    exit "$result"
}
trap on_exit EXIT
trap 'exit 130' HUP INT TERM

for file in "${files[@]}"; do
    phase="prepare:$file"
    mkdir -p "$work/original/$(dirname "$file")" "$work/ready/$(dirname "$file")"
    url="repos/$repo/contents/$file?ref=$release"
    expected="$(gh api "$url" --jq '.sha')"
    gh api -H 'Accept: application/vnd.github.raw+json' "$url" > "$work/ready/$file"
    [[ "$(git hash-object "$work/ready/$file")" = "$expected" ]] || fail "Source checksum mismatch: $file"
    [[ ! -L "$site/$file" ]] || fail "Unexpected source link: $file"

    if [[ -e "$site/$file" ]]; then
        [[ -f "$site/$file" ]] || fail "Expected a regular source file: $file"
        cp -p "$site/$file" "$work/original/$file"
        if ! cmp -s "$site/$file" "$work/ready/$file"; then
            case "$file" in
                "$migration"|resources/css/plyr-player.css|resources/js/player-ads.js|resources/js/plyr-player.js)
                    fail "Existing file differs from the new player source: $file" ;;
            esac
            base_url="repos/$repo/contents/$file?ref=$base"
            if [[ "$file" = *.css || "$file" = *.js || "$file" = package.json ]]; then
                [[ "$(git hash-object "$site/$file")" = "$(gh api "$base_url" --jq '.sha')" ]] \
                    || fail "The deployed frontend differs from the build base: $file"
            else
                gh api -H 'Accept: application/vnd.github.raw+json' "$base_url" > "$work/base-file"
                git merge-file -p "$site/$file" "$work/base-file" "$work/ready/$file" > "$work/merged-file" \
                    || fail "Overlapping local customization: $file"
                mv "$work/merged-file" "$work/ready/$file"
            fi
        fi
        chown --reference="$work/original/$file" "$work/ready/$file"
        chmod --reference="$work/original/$file" "$work/ready/$file"
    else
        chown root:www "$work/ready/$file"
        chmod 0644 "$work/ready/$file"
    fi
    if [[ "$file" = *.php ]]; then "$php" -l "$work/ready/$file" >/dev/null; fi
    echo "SOURCE_READY=$file"
done

phase=prepare-frontend-build
gh run download "$run" --repo "$repo" --name gomovie-frontend-build --dir "$work/artifact"
(cd "$work/artifact" && sha256sum -c frontend.tar.gz.sha256)
mkdir -p "$work/ready/public/build"
python3 - "$work/artifact/frontend.tar.gz" "$work/ready/public/build" <<'PY'
import json
import re
import sys
import tarfile
from pathlib import Path

archive, directory = sys.argv[1:]
root = Path(directory)
with tarfile.open(archive, 'r:gz') as tar:
    members = tar.getmembers()
    for member in members:
        valid = (
            member.isdir() and member.name == 'assets'
            or member.isfile() and (
                member.name in {'manifest.json', 'fonts-manifest.json'}
                or re.fullmatch(r'assets/[A-Za-z0-9_.-]+', member.name) is not None
            )
        )
        if not valid:
            raise ValueError(f'Unexpected build path {member.name}')
    tar.extractall(root, members=members)

manifest = json.loads((root / 'manifest.json').read_text())
for key in ('resources/js/app.js', 'resources/css/app.css', 'resources/js/plyr-player.js'):
    assert (root / manifest[key]['file']).is_file(), key
assert (root / 'fonts-manifest.json').is_file()
PY
for file in "${manifests[@]}"; do
    [[ ! -L "$site/$file" ]] || fail "Unexpected asset manifest link: $file"
    mkdir -p "$work/original/$(dirname "$file")"
    if [[ -e "$site/$file" ]]; then
        cp -p "$site/$file" "$work/original/$file"
    fi
    chown root:www "$work/ready/$file"
    chmod 0644 "$work/ready/$file"
done

phase=install-assets
for asset in "$work/ready/public/build/assets/"*; do
    target="$site/public/build/assets/$(basename "$asset")"
    [[ ! -L "$target" ]] || fail "Unexpected frontend asset link: $target"
    if [[ -e "$target" ]]; then
        cmp -s "$asset" "$target" || fail "Existing asset has different content: $target"
    else
        install -o root -g www -m 0644 "$asset" "$target"
    fi
done

phase=player-preference-migration
applied+=("$migration")
put_file "$work/ready/$migration" "$site/$migration"
(cd "$site" && runuser -u www -- "$php" artisan migrate --path="$migration" --force --no-interaction)

phase=install-player
for file in "${files[@]}" "${manifests[@]}"; do
    [[ "$file" != "$migration" ]] || continue
    applied+=("$file")
    put_file "$work/ready/$file" "$site/$file"
done
(cd "$site" && runuser -u www -- "$php" artisan view:cache --no-interaction)

phase=verify-live-routes
for route in / /login /dashboard/player-settings; do
    code="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 15 \
        --resolve "$host:443:127.0.0.1" -o /dev/null -w '%{http_code}' "https://$host$route")"
    echo "HTTP $code $route"
    if [[ "$route" = /dashboard/player-settings ]]; then
        [[ "$code" = 302 || "$code" = 303 ]]
    else
        [[ "$code" = 200 ]]
    fi
done
complete=1
echo 'GOMOVIE_PLAYER_SETTINGS_DEPLOYMENT=SUCCESS'
