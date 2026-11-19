#!/usr/bin/env bash
# Apply the scoped catalog artwork update to the GoMovie aaPanel installation.
set -Eeuo pipefail
umask 077

if [[ $# -ne 2 || ! "$1" =~ ^[0-9a-f]{40}$ || ( "$2" != --check && "$2" != --apply ) ]]; then
    echo "Usage: bash catalog-artwork-upload-aapanel-upgrade.sh FULL_RELEASE_SHA --check|--apply"
    exit 2
fi

release="$1"
mode="$2"
repo='bambodia168-sys/Gomovie'
base='a827494592f5b3d60357698d7bbbb7eec9854034'
host='gomovie.tonsamith.com'
site="/www/wwwroot/$host"
files=(
    app/Support/CatalogArtwork.php
    app/Http/Controllers/Admin/CatalogController.php
    resources/views/admin/catalog/form.blade.php
)
expected=(
    35188f4140f5aac8ceb33ede7a6720bba440dc64
    e044fe17e4ae970ca797a0701fe9d454665d8955
    164636065583cf329f1057f016f0f4e8acb9f6a5
)

fail() {
    printf 'GOMOVIE_CATALOG_ARTWORK_ERROR=%s\n' "$*" >&2
    exit 1
}

test "$(id -u)" -eq 0 || fail 'Run from the aaPanel root terminal.'
for command in curl gh git flock runuser; do
    command -v "$command" >/dev/null || fail "Required command unavailable: $command"
done
[[ -d "$site" && ! -L "$site" ]] || fail "GoMovie installation not found at $site"
[[ -f "$site/vendor/autoload.php" && -f "$site/artisan" ]] || fail "Laravel installation is incomplete at $site"
id www >/dev/null || fail "The aaPanel PHP user www is unavailable."

php_version="$(sed -nE 's/^[[:space:]]*include[[:space:]]+[^;]*enable-php-(84|85)[.]conf[[:space:]]*;.*$/\1/p' \
    "/www/server/panel/vhost/nginx/$host.conf" 2>/dev/null | head -n1 || true)"
php="/www/server/php/${php_version:-85}/bin/php"
test -x "$php" || fail "The configured aaPanel PHP executable was not found: $php"
test "$(gh api "repos/$repo/commits/$release" --jq '.sha')" = "$release" || fail 'Could not verify the pinned GitHub commit.'

if [[ "$mode" = --apply ]]; then
    exec 9> /run/lock/gomovie-catalog-artwork-upload.lock
    flock -n 9 || fail 'Another GoMovie catalog artwork deployment is active.'
    mkdir -p /var/backups/gomovie
    work="$(mktemp -d /var/backups/gomovie/catalog-artwork-upload.XXXXXXXX)"
else
    work="$(mktemp -d)"
fi
applied=()
created_storage_dirs=()
created_storage_link=0
storage_root="$site/storage/app/public"
storage_link="$site/public/storage"
probe=''
phase=source-preparation
complete=0

put_file() {
    local source="$1" target="$2" staged
    staged="$(mktemp "$(dirname "$target")/.gm-catalog-artwork.XXXXXXXX")"
    if cp -p -- "$source" "$staged" && mv -fT -- "$staged" "$target"; then
        return 0
    fi
    rm -f -- "$staged"
    return 1
}

on_exit() {
    local result=$? file i restored=1
    trap - EXIT HUP INT TERM
    set +e
    if (( result != 0 )); then
        printf 'GOMOVIE_CATALOG_ARTWORK_DEPLOYMENT=FAILED phase=%s exit=%s\n' "$phase" "$result" >&2
    fi
    if [[ -n "$probe" ]]; then
        runuser -u www -- rm -f -- "$probe"
    fi
    if [[ "$complete" = 0 && "${#applied[@]}" -gt 0 ]]; then
        for file in "${applied[@]}"; do
            put_file "$work/original/$file" "$site/$file" || restored=0
        done
        (cd "$site" && runuser -u www -- "$php" artisan view:clear --no-interaction) >/dev/null 2>&1 || restored=0
        (( restored )) && echo 'SOURCE_ROLLBACK=COMPLETE' || echo 'SOURCE_ROLLBACK=INCOMPLETE'
    fi
    if (( complete == 0 )); then
        if (( created_storage_link )) && [[ -L "$storage_link" && "$(readlink "$storage_link")" = "$storage_root" ]]; then
            rm -f -- "$storage_link"
        fi
        for (( i = ${#created_storage_dirs[@]} - 1; i >= 0; i-- )); do
            rmdir -- "${created_storage_dirs[i]}" 2>/dev/null
        done
    fi
    if [[ "$mode" = --check ]]; then
        rm -rf -- "$work"
    else
        echo "BACKUP_DIRECTORY=$work"
    fi
    exit "$result"
}
trap on_exit EXIT
trap 'exit 130' HUP INT TERM

for index in "${!files[@]}"; do
    file="${files[$index]}"
    phase="prepare:$file"
    [[ -f "$site/$file" && ! -L "$site/$file" ]] || fail "Expected a regular deployed file: $file"
    for dir in original base release ready; do mkdir -p "$work/$dir/$(dirname "$file")"; done
    cp -p "$site/$file" "$work/original/$file"
    gh api -H 'Accept: application/vnd.github.raw+json' "repos/$repo/contents/$file?ref=$release" > "$work/release/$file"
    test "$(git hash-object "$work/release/$file")" = "${expected[$index]}" || fail "GitHub source checksum mismatch: $file"

    if cmp -s "$work/original/$file" "$work/release/$file"; then
        cp "$work/release/$file" "$work/ready/$file"
    else
        gh api -H 'Accept: application/vnd.github.raw+json' "repos/$repo/contents/$file?ref=$base" > "$work/base/$file"
        if ! git merge-file -p -- "$work/original/$file" "$work/base/$file" "$work/release/$file" > "$work/ready/$file"; then
            echo "OVERLAPPING_LOCAL_CUSTOMIZATION=$file"
            exit 1
        fi
    fi
    chmod --reference="$work/original/$file" "$work/ready/$file"
    chown --reference="$work/original/$file" "$work/ready/$file"
    "$php" -l "$work/ready/$file" >/dev/null || fail "PHP syntax check failed: $file"
    echo "SOURCE_READY=$file"
done

phase=public-artwork-storage
if [[ ! -d "$site/storage/app" || ! -d "$site/public" ]]; then
    fail 'The Laravel storage/app or public directory is missing.'
fi
if [[ -L "$storage_root" || "$(realpath -m "$storage_root")" != "$(realpath -e "$site")/storage/app/public" ]]; then
    fail 'The public storage root is outside the expected Laravel directory. Check the storage mapping.'
fi
for folder in "$storage_root" "$storage_root/artwork" "$storage_root/artwork/movie" "$storage_root/artwork/series"; do
    if [[ -L "$folder" || ( -e "$folder" && ! -d "$folder" ) ]]; then
        fail "Public artwork folder is not a regular directory: $folder"
    fi
    if [[ ! -d "$folder" ]]; then
        [[ "$mode" = --apply ]] || fail "Missing public artwork folder (created by --apply): $folder"
        install -d -o www -g "$(id -gn www)" -m 0755 -- "$folder" || fail "Unable to create public artwork folder: $folder"
        created_storage_dirs+=("$folder")
        echo "ARTWORK_DIRECTORY_CREATED=$folder"
    fi
done
for slot in movie series; do
    runuser -u www -- test -w "$storage_root/artwork/$slot" \
        || fail "The aaPanel PHP user cannot write $storage_root/artwork/$slot"
done

if [[ ! -e "$storage_link" && ! -L "$storage_link" ]]; then
    [[ "$mode" = --apply ]] || fail 'The public/storage link is missing (created by --apply).'
    ln -sT -- "$storage_root" "$storage_link"
    created_storage_link=1
    chown -h --reference="$storage_root" "$storage_link"
    echo 'PUBLIC_STORAGE_LINK=CREATED'
fi
if [[ "$(realpath -e "$storage_link")" != "$storage_root" ]]; then
    fail "The existing public/storage path does not resolve to $storage_root. Verify the existing mapping before changing it."
fi
echo 'GOMOVIE_CATALOG_ARTWORK_PREFLIGHT=PASS'
if [[ "$mode" = --check ]]; then
    complete=1
    exit 0
fi

phase=public-artwork-http-probe
probe="$(runuser -u www -- mktemp --suffix=.png "$storage_root/artwork/movie/gm-catalog-check.XXXXXXXX")"
printf '%s' 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGMQkDMCAACiAGE2bf1hAAAAAElFTkSuQmCC' \
    | runuser -u www -- bash -c 'base64 -d > "$1" && chmod 0644 -- "$1"' _ "$probe"
probe_status="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 15 \
    --resolve "$host:443:127.0.0.1" -o "$work/artwork-probe.png" -w '%{http_code}' \
    "https://$host/storage/artwork/movie/$(basename "$probe")")"
[[ "$probe_status" = 200 ]] && cmp -s "$probe" "$work/artwork-probe.png" \
    || fail "The web server did not serve the temporary public artwork (HTTP $probe_status). Check the /storage mapping."
runuser -u www -- rm -f -- "$probe" || fail 'Unable to remove the temporary public artwork.'
probe=''
echo 'PUBLIC_ARTWORK_HTTP=PASS'

phase=source-installation
for file in "${files[@]}"; do
    cmp -s "$work/original/$file" "$site/$file"
    if ! cmp -s "$work/original/$file" "$work/ready/$file"; then
        applied+=("$file")
        put_file "$work/ready/$file" "$site/$file"
    fi
done

phase=blade-cache
(
    cd "$site"
    runuser -u www -- "$php" artisan view:cache --no-interaction
)

for path in / /login /dashboard/catalog/movies/create; do
    phase="verify-route:$path"
    status="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 15 \
        --resolve "$host:443:127.0.0.1" -o /dev/null -w '%{http_code}' "https://$host$path")"
    echo "HTTP $status $path"
    if [[ "$path" = /dashboard/catalog/movies/create ]]; then
        [[ "$status" = 302 || "$status" = 303 ]]
    else
        [[ "$status" = 200 ]]
    fi
done

phase=verify-installed-sources
for file in "${files[@]}"; do cmp -s "$work/ready/$file" "$site/$file"; done
complete=1
echo 'GOMOVIE_CATALOG_ARTWORK_DEPLOYMENT=SUCCESS'
