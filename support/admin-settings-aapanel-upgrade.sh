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
view_directories=(resources/views/admin/distribution-rights resources/views/admin/site-settings)

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
conflicts=()
applied=()
created_view_directories=()
complete=0
worker_restarted=0

put_file() {
    local source="$1" target="$2" staged
    staged="$(mktemp "$(dirname "$target")/.gm-admin-settings.XXXXXXXX")" || return 1
    if cp -p -- "$source" "$staged" && mv -fT -- "$staged" "$target"; then
        return 0
    fi
    rm -f -- "$staged"
    return 1
}

prepare_view_directories() {
    local relative directory mode
    for relative in "${view_directories[@]}"; do
        directory="$site/$relative"
        [[ -d "$(dirname "$directory")" && ! -L "$directory" ]] \
            || fail "Unexpected view directory path: $relative"
        if [[ -e "$directory" ]]; then
            [[ -d "$directory" ]] || fail "Expected a view directory: $relative"
            if runuser -u www -- test -r "$directory" && runuser -u www -- test -x "$directory"; then
                continue
            fi
            # Older installers created empty root-only directories under umask 077.
            mode="$(stat -c '%u:%a' -- "$directory")"
            [[ "$mode" = '0:700' || "$mode" = '0:2700' ]] \
                || fail "Unexpected permissions for view directory: $relative ($mode)"
            rmdir -- "$directory" || fail "Inaccessible view directory contains files: $relative"
            echo "STALE_VIEW_DIRECTORY_REMOVED=$relative"
        fi
        created_view_directories+=("$directory")
        install -d -o root -g www -m 0750 -- "$directory"
        runuser -u www -- test -r "$directory" && runuser -u www -- test -x "$directory" \
            || fail "The PHP user cannot read and traverse view directory: $relative"
        echo "VIEW_DIRECTORY_READY=$relative"
    done
}

merge_custom_branding() {
    python3 - "$@" <<'PY_CUSTOM_BRANDING'
import hashlib
from pathlib import Path
import re
import sys

relative, live, base, release, merged = sys.argv[1:]
# Only the known branding changes can use this fallback.
blobs = {
    'layouts/admin.blade.php': ('00c49ce9b6a7c7ab4193a032749a23d608cc23a9', 'b261c15f107aad597d50699c5bd84b2d50dedbc6'),
    'layouts/account.blade.php': ('401b8acbdf677cb73eea0aa8d7737b405b00608a', 'cd7588fac606a2a5d32d2112a3c310ae7f349411'),
    'layouts/app.blade.php': ('6bc9f4d9458fd9b574a9be16711a1a7531742339', '3366a990ef151cefb21978385ef1facaa944f026'),
    'layouts/auth.blade.php': ('c6f5c2cac43e27460c77b5fab133c3c555034301', '5d616a95313e4ae21403bc0036d55c38c2fed706'),
    'partials/brand-head.blade.php': ('935182208427dfdfcf1b211f95faff01d8fe14c7', 'a5ef5146679044c71a97de4e0c7e3198a7b67da4'),
    'partials/brand-logo.blade.php': ('3b87e24faf08149628c577f1e9652e5e2b083785', '132d1041d59e9756a405ceeadd81f84a9d961014'),
    'partials/header-actions.blade.php': ('f4f0091db5fe7c0eeccaf0e875f7eb5413a9e3bf', '20e25aaa242b44c39f911e5351496dc89504964c'),
}
key = relative.removeprefix('resources/views/')

def blob(source):
    data = Path(source).read_bytes()
    return hashlib.sha1(f'blob {len(data)}\0'.encode() + data).hexdigest()

if blobs.get(key) != (blob(base), blob(release)):
    raise SystemExit("No supported branding merge for these source revisions")

current = Path(live).read_bytes().decode('utf-8')
text = current
if re.search(r'(?m)^(?:<<<<<<< |\|{7} |={7}\s*$|>>>>>>> )', text):
    raise SystemExit("Live source contains unresolved merge markers")

site_name = r"{{ \App\Support\SiteSettings::get('site_name') }}"
brand = re.compile(r"(?<![\w/])Go[ \t]*Movie(?![\w.])", re.I)
notes = []

def title(match):
    opening, label, closing = match.groups()
    if site_name in label:
        return match.group()
    prefix = re.match(r"""^\s*@yield\(\s*['"]title['"]\s*(?:,\s*['"][^'"\r\n]*['"]\s*)?\)""", label)
    end = prefix.end() if prefix else 0
    suffix = label[end:]
    if re.search(r'[@<>{}]', suffix) or len(brand.findall(suffix)) != 1:
        notes.append('custom title retained')
        return match.group()
    return opening + label[:end] + brand.sub(lambda _: site_name, suffix) + closing

def asset(source, key, path):
    pattern = re.compile(r"""\{\{\s*asset\(\s*(['"])""" + re.escape(path) + r"""\1\s*\)\s*\}\}""")
    replacement = r"{{ \App\Support\SiteSettings::brandAsset('" + key + "', '" + path + "') }}"
    return pattern.sub(lambda _: replacement, source)

if key.startswith('layouts/'):
    titles = re.compile(r'(<title\b[^>]*>)(.*?)(</title\s*>)', re.I | re.S)
    if len(titles.findall(text)) == 1:
        text = titles.sub(title, text)
    else:
        notes.append('custom title retained')
    text = re.sub(
        r"""(?<![-\w])(aria-label\s*=\s*['"])Go[ \t]*Movie( homepage['"])""",
        lambda m: m[1] + site_name + m[2], text, flags=re.I,
    )
    text = re.sub(
        r"""((?:©|&copy;)\s*\{\{\s*date\(\s*['"]Y['"]\s*\)\s*\}\}\s*)Go[ \t]*Movie(?!\w)""",
        lambda m: m[1] + site_name, text, flags=re.I,
    )

if key == 'layouts/app.blade.php':
    # The publisher slot identifies the Appearance-controlled link.
    setting = r"\App\Support\SiteSettings::get('footer_url')"
    link = re.compile(r'(<a\b[^>]*>)((?:(?!</?a\b).)*)(</a\s*>)', re.I | re.S)
    slot = re.compile(r"""StudioAppearance::slot\(\s*(['"])footer-publisher-label\1\s*\)""")
    default_url = re.compile(r"""(?<=\s)href\s*=\s*(['"])https://tonsamith\.com/?\1""", re.I)
    anchors = [match for match in link.finditer(text) if slot.search(match[2])]
    if setting not in text and len(anchors) == 1 and default_url.search(anchors[0][1]):
        anchor = anchors[0]
        opening = default_url.sub('href="{{ $footerUrl }}"', anchor[1], count=1)
        conditional = '@if($footerUrl = ' + setting + ') ' + opening + anchor[2] + anchor[3] + ' @endif'
        text = text[:anchor.start()] + conditional + text[anchor.end():]
    elif setting not in text:
        notes.append('custom publisher link retained')

if key in ('layouts/auth.blade.php', 'partials/brand-head.blade.php'):
    if key == 'partials/brand-head.blade.php':
        original = Path(base).read_text()
        incoming = Path(release).read_text()
        icons = '\n'.join(line for line in original.splitlines() if line.startswith('<link rel="icon"'))
        new_icons = incoming[incoming.index('@if('):incoming.index('@endif') + len('@endif')]
        if icons and text.count(icons) == 1 and new_icons not in text:
            text = text.replace(icons, new_icons, 1)
        elif new_icons not in text:
            notes.append('custom favicon markup retained')
    text = asset(text, 'site_icon', 'apple-touch-icon.png')
elif key == 'partials/brand-logo.blade.php':
    text = asset(text, 'logo_light', 'brand/gomovie-logo-light.webp')
    text = asset(text, 'logo_dark', 'brand/gomovie-logo-dark.webp')
elif key == 'partials/header-actions.blade.php':
    text = text.replace('>Welcome to Go Movie</div>', '>Welcome to ' + site_name + '</div>')

Path(merged).write_bytes(text.encode('utf-8'))
for note in notes:
    print(f'CUSTOM_BRANDING_NOTE={relative}: {note}')
PY_CUSTOM_BRANDING
}

on_exit() {
    local result=$? restored=1 file directory
    trap - EXIT HUP INT TERM
    if (( complete )); then return; fi
    set +e
    printf 'GOMOVIE_ADMIN_SETTINGS_DEPLOYMENT=FAILED phase=%s exit=%s\n' "$phase" "$result" >&2
    if (( ${#applied[@]} > 0 || ${#created_view_directories[@]} > 0 )); then
        for file in "${applied[@]}"; do
            if [[ -f "$work/original/$file" ]]; then
                put_file "$work/original/$file" "$site/$file" || restored=0
            elif [[ "$file" != "$migration" ]]; then
                rm -f -- "$site/$file" || restored=0
            fi
        done
        for directory in "${created_view_directories[@]}"; do
            if [[ -e "$directory" || -L "$directory" ]]; then
                rmdir -- "$directory" || restored=0
            fi
        done
        if (( ${#applied[@]} > 0 )); then
            if ! (cd "$site" && runuser -u www -- "$php" artisan route:clear --no-interaction \
                && runuser -u www -- "$php" artisan view:cache --no-interaction) >"$work/rollback-cache.log" 2>&1; then
                echo "ROLLBACK_CACHE_LOG=$work/rollback-cache.log" >&2
                restored=0
            fi
        fi
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
                    conflicts+=("$file")
                    echo "SOURCE_CONFLICT=$file: a new settings file already exists with different content" >&2
                    continue ;;
                resources/css/studio.css|resources/views/admin/player-settings.blade.php) previous="$player_base" ;;
                app/Http/Controllers/Admin/CatalogController.php|resources/views/admin/catalog/form.blade.php) previous="$artwork_base" ;;
                *) previous="$base" ;;
            esac
            base_url="repos/$repo/contents/$file?ref=$previous"
            if [[ "$file" = *.css || "$file" = *.js || "$file" = package.json ]]; then
                previous_sha="$(gh api "$base_url" --jq '.sha')"
                if [[ "$(git hash-object "$work/original/$file")" != "$previous_sha" ]]; then
                    conflicts+=("$file")
                    echo "SOURCE_CONFLICT=$file: the deployed frontend differs from the build base" >&2
                    continue
                fi
            else
                gh api -H 'Accept: application/vnd.github.raw+json' "$base_url" > "$work/base-file"
                if git merge-file -p --diff3 "$work/original/$file" "$work/base-file" "$work/ready/$file" > "$work/merged-file"; then
                    mv "$work/merged-file" "$work/ready/$file"
                elif [[ "$file" = resources/views/layouts/*.blade.php || "$file" = resources/views/partials/brand-*.blade.php || "$file" = resources/views/partials/header-actions.blade.php ]] \
                    && merge_custom_branding "$file" "$work/original/$file" "$work/base-file" "$work/ready/$file" "$work/merged-file"; then
                    echo "CUSTOM_BRANDING_MERGED=$file"
                    mv "$work/merged-file" "$work/ready/$file"
                else
                    conflicts+=("$file")
                    echo "SOURCE_CONFLICT=$file" >&2
                    continue
                fi
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
if (( ${#conflicts[@]} > 0 )); then
    phase=source-conflicts
    fail "Overlapping local customizations: ${conflicts[*]}"
fi

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

phase=prepare-view-directories
prepare_view_directories

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
