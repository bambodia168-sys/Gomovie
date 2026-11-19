#!/usr/bin/env bash
# Install the dashboard settings and the assets built by the release CI run.
set -Eeuo pipefail
umask 077

if [[ $# -ne 2 || ! "$1" =~ ^[0-9a-f]{40}$ || ! "$2" =~ ^[0-9]+$ ]]; then
    echo 'Usage: bash admin-settings-aapanel-upgrade.sh RELEASE_SHA CI_RUN_ID' >&2
    exit 2
fi

release="$1"
run="$2"
repo='bambodia168-sys/Gomovie'
base='a827494592f5b3d60357698d7bbbb7eec9854034'
player_base='8532f0474d03645528dd04a0690b7c18c17ef0e4'
artwork_base='d33b85449a928985e25dbc44014ada9968020bac'
host='gomovie.tonsamith.com'
site="/www/wwwroot/$host"
migration='database/migrations/2026_11_19_000200_create_site_settings_table.php'
files=(
    "$migration"
    app/Console/Commands/ImportPopularCatalog.php
    app/Console/Commands/MediaPipelinePreflight.php
    app/Http/Controllers/Admin/AppearanceController.php
    app/Http/Controllers/Admin/CatalogController.php
    app/Http/Controllers/Admin/DistributionRightsController.php
    app/Http/Controllers/Admin/EpisodeStudioController.php
    app/Http/Controllers/Admin/QuickUploadController.php
    app/Http/Controllers/Admin/SiteSettingsController.php
    app/Http/Controllers/Admin/TmdbController.php
    app/Http/Controllers/Api/V1/EdgeAuthorizationController.php
    app/Http/Controllers/Frontend/BrowseController.php
    app/Jobs/MirrorPrivateIngestToR2.php
    app/Providers/AppServiceProvider.php
    app/Services/Media/AutomaticPublicationService.php
    app/Services/Media/PrivateMediaStagingPublisher.php
    app/Services/Media/PrivateVideoIntake.php
    app/Services/Media/TranscodeGate.php
    app/Services/Media/TranscodingService.php
    app/Services/Operations/OperationalSnapshot.php
    app/Services/Streaming/PlaybackGrantService.php
    app/Support/SiteSettings.php
    bootstrap/app.php
    resources/css/studio.css
    resources/views/admin/appearance/index.blade.php
    resources/views/admin/catalog/form.blade.php
    resources/views/admin/distribution-rights/edit.blade.php
    resources/views/admin/distribution-rights/index.blade.php
    resources/views/admin/media.blade.php
    resources/views/admin/operations.blade.php
    resources/views/admin/player-settings.blade.php
    resources/views/admin/quick-upload.blade.php
    resources/views/admin/series/episodes.blade.php
    resources/views/admin/site-settings.blade.php
    resources/views/admin/site-settings/secret.blade.php
    resources/views/admin/site-settings/switch.blade.php
    resources/views/frontend/partials/guest-watch-signin.blade.php
    resources/views/layouts/account.blade.php
    resources/views/layouts/admin.blade.php
    resources/views/layouts/app.blade.php
    resources/views/layouts/auth.blade.php
    resources/views/partials/brand-head.blade.php
    resources/views/partials/brand-logo.blade.php
    resources/views/partials/header-actions.blade.php
    resources/views/partials/workspace-sidebar.blade.php
    routes/console.php
    routes/web.php
)
manifests=(public/build/fonts-manifest.json public/build/manifest.json)

fail() {
    printf 'GOMOVIE_ADMIN_SETTINGS_ERROR=%s\n' "$*" >&2
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

exec 9> /run/lock/gomovie-admin-settings.lock
flock -n 9 || fail 'A GoMovie settings deployment is active.'
mkdir -p /var/backups/gomovie
work="$(mktemp -d /var/backups/gomovie/admin-settings.XXXXXXXX)"
echo "BACKUP_DIRECTORY=$work"
phase=prepare-sources
applied=()
complete=0
worker_restarted=0

put_file() {
    local source="$1" target="$2" staged
    mkdir -p -- "$(dirname "$target")" || return 1
    staged="$(mktemp "$(dirname "$target")/.gm-admin-settings.XXXXXXXX")" || return 1
    if cp -p -- "$source" "$staged" && mv -fT -- "$staged" "$target"; then
        return 0
    fi
    rm -f -- "$staged"
    return 1
}

merge_custom_admin_title() {
    python3 - "$@" <<'PY_ADMIN_TITLE'
from pathlib import Path
import re
import sys

live, base, release, merged = map(Path, sys.argv[1:])
old, new, current = (path.read_bytes() for path in (base, release, live))
titles = re.compile(rb"(<title\b[^>]*>)(.*?)(</title\s*>)", re.I | re.S)
brand = re.compile(rb"(?<![\w/])Go[ \t]*Movie(?![\w.])", re.I)
expression = rb"{{ \App\Support\SiteSettings::get('site_name') }}"
title_prefix = re.compile(rb"""^\s*@yield\(\s*['"]title['"]\s*(?:,\s*['"][^'"\r\n]*['"]\s*)?\)""")

def title(source):
    matches = list(titles.finditer(source))
    if len(matches) != 1:
        raise SystemExit("Expected one admin layout title")
    return matches[0]

expected = old.replace(b"Go Movie Studio</title>", expression + b" Studio</title>", 1)
if expected != new or expected == old:
    raise SystemExit("The release changes more than the admin title branding")

match = title(current)
text = match.group(2)
if expression in text and text.count(expression) == 1:
    merged.write_bytes(current)
else:
    prefix = title_prefix.match(text)
    start = prefix.end() if prefix else 0
    suffix = text[start:]
    if re.search(rb"[@<>{}]", suffix) or len(brand.findall(suffix)) != 1:
        raise SystemExit("The live admin title has no unambiguous Go Movie brand")
    replacement = text[:start] + brand.sub(lambda _: expression, suffix)
    merged.write_bytes(current[:match.start(2)] + replacement + current[match.end(2):])
PY_ADMIN_TITLE
}

on_exit() {
    local result=$? restored=1 file
    trap - EXIT HUP INT TERM
    if (( complete )); then return; fi
    set +e
    printf 'GOMOVIE_ADMIN_SETTINGS_DEPLOYMENT=FAILED phase=%s exit=%s\n' "$phase" "$result" >&2
    if (( ${#applied[@]} > 0 )); then
        for file in "${applied[@]}"; do
            if [[ -f "$work/original/$file" ]]; then
                put_file "$work/original/$file" "$site/$file" || restored=0
            elif [[ "$file" != "$migration" ]]; then
                rm -f -- "$site/$file" || restored=0
            fi
        done
        (cd "$site" && runuser -u www -- "$php" artisan route:clear --no-interaction \
            && runuser -u www -- "$php" artisan view:cache --no-interaction) >/dev/null 2>&1 || restored=0
        if (( worker_restarted )); then
            systemctl restart gomovie-media-worker.service || restored=0
        fi
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
                "$migration"|app/Support/SiteSettings.php|app/Http/Controllers/Admin/SiteSettingsController.php|app/Http/Controllers/Admin/DistributionRightsController.php|resources/views/admin/site-settings*|resources/views/admin/distribution-rights/*)
                    fail "A new settings file already exists with different content: $file" ;;
                resources/css/studio.css|resources/views/admin/player-settings.blade.php) previous="$player_base" ;;
                app/Http/Controllers/Admin/CatalogController.php|resources/views/admin/catalog/form.blade.php) previous="$artwork_base" ;;
                *) previous="$base" ;;
            esac
            base_url="repos/$repo/contents/$file?ref=$previous"
            if [[ "$file" = *.css || "$file" = *.js || "$file" = package.json ]]; then
                [[ "$(git hash-object "$site/$file")" = "$(gh api "$base_url" --jq '.sha')" ]] \
                    || fail "The deployed frontend differs from the build base: $file"
            else
                gh api -H 'Accept: application/vnd.github.raw+json' "$base_url" > "$work/base-file"
                if ! git merge-file -p "$site/$file" "$work/base-file" "$work/ready/$file" > "$work/merged-file"; then
                    [[ "$file" = resources/views/layouts/admin.blade.php ]] \
                        && merge_custom_admin_title "$site/$file" "$work/base-file" "$work/ready/$file" "$work/merged-file" \
                        || fail "Overlapping local customization: $file"
                    echo 'CUSTOM_ADMIN_TITLE_MERGED=YES'
                fi
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

phase=site-settings-migration
applied+=("$migration")
put_file "$work/ready/$migration" "$site/$migration"
(cd "$site" && runuser -u www -- "$php" artisan migrate --path="$migration" --force --no-interaction)

phase=install-dashboard
for file in "${files[@]}" "${manifests[@]}"; do
    [[ "$file" != "$migration" ]] || continue
    applied+=("$file")
    put_file "$work/ready/$file" "$site/$file"
done
(
    cd "$site"
    runuser -u www -- "$php" artisan route:clear --no-interaction
    runuser -u www -- "$php" artisan view:cache --no-interaction
    runuser -u www -- "$php" artisan route:list --path=dashboard/site-settings --json | "$php" -r '
        $routes = array_column(json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR), "name");
        foreach (["edit", "update", "test-ffmpeg"] as $action) {
            if (!in_array("admin.site-settings.".$action, $routes, true)) exit(1);
        }
    '
)

phase=verify-live-routes
for route in / /login /dashboard/site-settings /dashboard/player-settings /dashboard/distribution-rights; do
    code="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 15 \
        --resolve "$host:443:127.0.0.1" -o /dev/null -w '%{http_code}' "https://$host$route")"
    echo "HTTP $code $route"
    if [[ "$route" == /dashboard/* ]]; then
        [[ "$code" = 302 || "$code" = 303 ]]
    else
        [[ "$code" = 200 ]]
    fi
done
phase=reload-media-worker
if systemctl is-active --quiet gomovie-media-worker.service; then
    worker_restarted=1
    systemctl restart gomovie-media-worker.service
    systemctl is-active --quiet gomovie-media-worker.service
fi
complete=1
echo 'GOMOVIE_ADMIN_SETTINGS_DEPLOYMENT=SUCCESS'
