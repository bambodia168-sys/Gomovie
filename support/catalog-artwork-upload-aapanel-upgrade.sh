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

test "$(id -u)" -eq 0
for command in curl gh git flock runuser; do command -v "$command" >/dev/null; done
test -d "$site" && test ! -L "$site"
test -f "$site/vendor/autoload.php"
test -f "$site/artisan"
id www >/dev/null

php_version="$(sed -nE 's/^[[:space:]]*include[[:space:]]+[^;]*enable-php-(84|85)[.]conf[[:space:]]*;.*$/\1/p' \
    "/www/server/panel/vhost/nginx/$host.conf" 2>/dev/null | head -n1 || true)"
php="/www/server/php/${php_version:-85}/bin/php"
test -x "$php"
test "$(gh api "repos/$repo/commits/$release" --jq '.sha')" = "$release"

if [[ "$mode" = --apply ]]; then
    exec 9> /run/lock/gomovie-catalog-artwork-upload.lock
    flock -n 9
    install -d -m 0700 /var/backups/gomovie
    work="$(mktemp -d /var/backups/gomovie/catalog-artwork-upload.XXXXXXXX)"
else
    work="$(mktemp -d)"
fi
applied=()
complete=0

put_file() {
    local source="$1" target="$2" staged
    staged="$(mktemp "$(dirname "$target")/.gm-catalog-artwork.XXXXXXXX")"
    if cp -p -- "$source" "$staged" && mv -fT -- "$staged" "$target"; then
        return
    fi
    rm -f -- "$staged"
    return 1
}

on_exit() {
    local result=$? file restored=1
    trap - EXIT HUP INT TERM
    set +e
    if [[ "$complete" = 0 && "${#applied[@]}" -gt 0 ]]; then
        for file in "${applied[@]}"; do
            put_file "$work/original/$file" "$site/$file" || restored=0
        done
        (cd "$site" && runuser -u www -- "$php" artisan view:clear --no-interaction) >/dev/null 2>&1 || restored=0
        (( restored )) && echo 'SOURCE_ROLLBACK=COMPLETE' || echo 'SOURCE_ROLLBACK=INCOMPLETE'
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
    test -f "$site/$file" && test ! -L "$site/$file"
    for dir in original base release ready; do mkdir -p "$work/$dir/$(dirname "$file")"; done
    cp -p "$site/$file" "$work/original/$file"
    gh api -H 'Accept: application/vnd.github.raw+json' "repos/$repo/contents/$file?ref=$release" > "$work/release/$file"
    test "$(git hash-object "$work/release/$file")" = "${expected[$index]}"

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
    "$php" -l "$work/ready/$file" >/dev/null
done

runuser -u www -- test -w "$site/storage/app/public"
test "$(readlink -f "$site/public/storage")" = "$(readlink -f "$site/storage/app/public")"
echo 'GOMOVIE_CATALOG_ARTWORK_PREFLIGHT=PASS'
[[ "$mode" = --apply ]] || exit 0

for file in "${files[@]}"; do
    cmp -s "$work/original/$file" "$site/$file"
    if ! cmp -s "$work/original/$file" "$work/ready/$file"; then
        applied+=("$file")
        put_file "$work/ready/$file" "$site/$file"
    fi
done

(
    cd "$site"
    runuser -u www -- "$php" artisan view:cache --no-interaction
)

for path in / /login /dashboard/catalog/movies/create; do
    status="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 15 \
        --resolve "$host:443:127.0.0.1" -o /dev/null -w '%{http_code}' "https://$host$path")"
    echo "HTTP $status $path"
    if [[ "$path" = /dashboard/catalog/movies/create ]]; then
        [[ "$status" = 302 || "$status" = 303 ]]
    else
        [[ "$status" = 200 ]]
    fi
done

for file in "${files[@]}"; do cmp -s "$work/ready/$file" "$site/$file"; done
complete=1
echo 'GOMOVIE_CATALOG_ARTWORK_DEPLOYMENT=SUCCESS'
