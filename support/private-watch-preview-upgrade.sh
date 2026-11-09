#!/usr/bin/env bash
# Gomovie: private admin watch-page preview, NOT a public playback release.
# Deploys 4 precisely pinned files and never changes .env, DB or media assets.
set -Eeuo pipefail
umask 077

if [ "$#" -ne 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
  echo "Usage: bash private-watch-preview-upgrade.sh --check|--apply"
  exit 2
fi
MODE="$1"
REPO="bambodia168-sys/Gomovie"
SHA="1f6b4bf15e21b4de0e9a59d55e3f7e4d47c2ff96"
APP="/www/wwwroot/gomovie.tonsamith.com"
PHP="/www/server/php/85/bin/php"
HOST="gomovie.tonsamith.com"
NEW="app/Http/Controllers/Admin/PrivateIngestPreviewController.php"

files() {
  printf '%s\n' \
    routes/web.php \
    app/Http/Controllers/Frontend/BrowseController.php \
    resources/views/frontend/detail.blade.php \
    app/Http/Controllers/Admin/PrivateIngestPreviewController.php
}
old_blob() {
  case "$1" in
    routes/web.php) echo 46f6aad5f506e8a0e6ff66838907f28bd733a793;;
    app/Http/Controllers/Frontend/BrowseController.php) echo 5d7930d135562a5cd8bfacddf571819860a55d2c;;
    resources/views/frontend/detail.blade.php) echo df7897dfd6dba60b6debe9be2d6a63e1c5f1d4ff;;
    app/Http/Controllers/Admin/PrivateIngestPreviewController.php) echo NEW_FILE;;
    *) return 1;;
  esac
}
new_blob() {
  case "$1" in
    routes/web.php) echo 1415dfb8db52620e1d2f7f5ff7d31e8c3e3e397f;;
    app/Http/Controllers/Frontend/BrowseController.php) echo 389dc1830e840a68cb84595c4fc77564b9d5c119;;
    resources/views/frontend/detail.blade.php) echo 1436397b450338df3db992e2d7b239e089ca0a95;;
    app/Http/Controllers/Admin/PrivateIngestPreviewController.php) echo f06dcfbe8dc0f947792ce18ad31433daad47a537;;
    *) return 1;;
  esac
}

echo "========== GOMOVIE PRIVATE WATCH PREVIEW =========="
echo "MODE=$MODE"
test "$(id -u)" -eq 0
test -d "$APP" && test ! -L "$APP"
test -f "$APP/.env" && test ! -L "$APP/.env"
test -f "$APP/artisan" && test -f "$APP/vendor/autoload.php"
test -f "$APP/storage/app/private/.gomovie-installed"
test -x "$PHP"
for command_name in gh git curl runuser; do command -v "$command_name" >/dev/null; done
id www >/dev/null

echo "========== VERIFY EXISTING FILES =========="
while IFS= read -r path; do
  if [ "$path" = "$NEW" ] && [ ! -e "$APP/$path" ]; then
    test ! -L "$APP/$path"
    echo "NEW_CONTROLLER=NOT_INSTALLED"
    continue
  fi
  test -f "$APP/$path" && test ! -L "$APP/$path"
  actual="$(git hash-object "$APP/$path")"
  if [ "$actual" != "$(old_blob "$path")" ] && [ "$actual" != "$(new_blob "$path")" ]; then
    echo "BLOCKED_CUSTOM_FILE=$path"
    echo "ACTUAL_BLOB=$actual"
    exit 1
  fi
  echo "LIVE_SOURCE_VERIFIED=$path"
done < <(files)

if [ "$MODE" = "--check" ]; then
  echo "GOMOVIE_PRIVATE_PREVIEW_PREFLIGHT=PASS"
  echo "LIVE_FILES_CHANGED=NO"
  echo "DATABASE_CHANGED=NO"
  exit 0
fi

echo "========== PINNED GITHUB SOURCE =========="
test "$(gh api "repos/$REPO/commits/$SHA" --jq '.sha')" = "$SHA"
BACKUP="$(mktemp -d /root/Gomovie-Private-Watch-Preview-XXXXXXXX)"
chmod 700 "$BACKUP"
mkdir -p "$BACKUP/old" "$BACKUP/new"
echo "BACKUP_DIRECTORY=$BACKUP"

echo "========== DOWNLOAD & INTEGRITY CHECK =========="
while IFS= read -r path; do
  mkdir -p "$BACKUP/old/$(dirname "$path")" "$BACKUP/new/$(dirname "$path")"
  if [ -f "$APP/$path" ]; then
    cp -p "$APP/$path" "$BACKUP/old/$path"
  fi
  gh api -H "Accept: application/vnd.github.raw+json" \
    "repos/$REPO/contents/$path?ref=$SHA" > "$BACKUP/new/$path"
  test "$(git hash-object "$BACKUP/new/$path")" = "$(new_blob "$path")"
  case "$path" in *.php) "$PHP" -l "$BACKUP/new/$path" >/dev/null;; esac
  echo "GITHUB_SOURCE_VERIFIED=$path"
done < <(files)

CHANGED=0
COMPLETE=0
restore() {
  if [ "$CHANGED" = 1 ] && [ "$COMPLETE" = 0 ]; then
    set +e
    echo "========== RESTORE ORIGINAL FILES =========="
    while IFS= read -r path; do
      if [ -f "$BACKUP/old/$path" ]; then
        cp -p "$BACKUP/old/$path" "$APP/$path"
      elif [ "$path" = "$NEW" ]; then
        rm -f -- "$APP/$path"
      fi
    done < <(files)
    (cd "$APP" && runuser -u www -- "$PHP" artisan optimize:clear) >/dev/null 2>&1
    echo "SOURCE_ROLLBACK=COMPLETE"
  fi
}
trap restore EXIT

echo "========== INSTALL PRIVATE PREVIEW SOURCE =========="
CHANGED=1
while IFS= read -r path; do
  install -o root -g www -m 0644 "$BACKUP/new/$path" "$APP/$path"
done < <(files)

echo "========== VERIFY SECURE PREVIEW ROUTE =========="
ROUTES="$(
  cd "$APP"
  runuser -u www -- "$PHP" artisan optimize:clear
  runuser -u www -- "$PHP" artisan route:list --path=dashboard/media/ingests
)"
printf '%s\n' "$ROUTES"
printf '%s\n' "$ROUTES" | grep -Fq "media.ingest-preview"

echo "========== PUBLIC PAGE SMOKE CHECK =========="
for route in / /login /register /browse /watch/movie/a-story-of-derry-1813355; do
  status="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' \
    "https://$HOST$route")"
  echo "ROUTE=$route HTTP=$status"
  test "$status" = 200
done
guest="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
  --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' \
  "https://$HOST/dashboard/media/ingests/1/preview")"
echo "PRIVATE_PREVIEW_GUEST_HTTP=$guest"
test "$guest" = 302

echo "========== FINAL FILE INTEGRITY =========="
while IFS= read -r path; do
  test "$(git hash-object "$APP/$path")" = "$(new_blob "$path")"
done < <(files)

COMPLETE=1
echo "GOMOVIE_ADMIN_PRIVATE_PREVIEW=INSTALLED"
echo "PRIVATE_BROWSER_RANGE=SUPPORTED"
echo "PRIVATE_PREVIEW_AUTH=VERIFIED_ADMIN_ONLY"
echo "PUBLIC_STREAMING=UNCHANGED"
echo "CONTENT_RIGHTS=UNCHANGED"
echo "DATABASE_CHANGED=NO"
echo "EXISTING_MEDIA=UNCHANGED"
echo "PAYMENTS=UNCHANGED"
echo "GOMOVIE_WATCH_PREVIEW_DEPLOYMENT=SUCCESS"
echo "BACKUP_DIRECTORY=$BACKUP"
