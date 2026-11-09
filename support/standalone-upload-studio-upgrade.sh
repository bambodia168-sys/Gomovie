#!/usr/bin/env bash
# Gomovie standalone Upload Studio: selective aaPanel source deployment.
# Never touch .env, private uploads, existing media, DB, playback, or payments.
set -Eeuo pipefail
umask 077

if [ "$#" -ne 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
  echo "Usage: bash standalone-upload-studio-upgrade.sh --check|--apply"
  exit 2
fi

MODE="$1"
APP="/www/wwwroot/gomovie.tonsamith.com"
PHP="/www/server/php/85/bin/php"
REPO="bambodia168-sys/Gomovie"
SHA="a7219073446a358c32d8edcc8b416710e5b500a1"

files() {
  printf '%s\n' \
    routes/web.php \
    app/Http/Controllers/Admin/UploadStudioController.php \
    resources/views/admin/upload-studio.blade.php \
    resources/views/layouts/admin.blade.php \
    resources/views/admin/media.blade.php \
    resources/views/admin/upload-review.blade.php
}
old_blob() {
  case "$1" in
    routes/web.php) echo 7c926837a05d4e27cf3f411adcfec08af9baf98e;;
    app/Http/Controllers/Admin/UploadStudioController.php) echo ec67e470dc4be266acec7a1be3a7b563c10cfea3;;
    resources/views/admin/upload-studio.blade.php) echo a3553b62ee4c3e0c4ec741f1787a952a852abdcb;;
    resources/views/layouts/admin.blade.php) echo b4f206d1a0397547822f63bcea50029e237b08c0;;
    resources/views/admin/media.blade.php) echo 77c22ae2396c4b95d515f5677c81593f2df83ae1;;
    resources/views/admin/upload-review.blade.php) echo 311115d9d5e0ead52c323bc46507654928a90d24;;
    *) return 1;;
  esac
}
new_blob() {
  case "$1" in
    routes/web.php) echo f2edff6ae975fb3367907d0d61b2d084f8a5bee9;;
    app/Http/Controllers/Admin/UploadStudioController.php) echo dddcac7f33c3c94be0eacd3947df666480bbbdb0;;
    resources/views/admin/upload-studio.blade.php) echo b6cf3d3677121b89bbba599d68b7104f5bd35d47;;
    resources/views/layouts/admin.blade.php) echo a85983f4c4aa6a661044593b1a06700d5cc8a7cf;;
    resources/views/admin/media.blade.php) echo d4dbb52f49d2b0b9628afb74f3050ed504850c30;;
    resources/views/admin/upload-review.blade.php) echo fb92bd9b611a3d25ee11ba0db369b540da7e8040;;
    *) return 1;;
  esac
}

echo "========== GOMOVIE STANDALONE UPLOAD STUDIO =========="
echo "MODE=$MODE"
test "$(id -u)" = 0
test -d "$APP" && test ! -L "$APP"
test -f "$APP/.env" && test ! -L "$APP/.env"
test -f "$APP/artisan"
test -f "$APP/vendor/autoload.php"
test -f "$APP/storage/app/private/.gomovie-installed"
test -x "$PHP"
for cmd in gh git curl runuser; do command -v "$cmd" >/dev/null; done
id www >/dev/null

echo "========== VERIFY LIVE SOURCES =========="
while IFS= read -r file; do
  test -f "$APP/$file" && test ! -L "$APP/$file"
  current="$(git hash-object "$APP/$file")"
  if [ "$current" != "$(old_blob "$file")" ] && [ "$current" != "$(new_blob "$file")" ]; then
    echo "BLOCKED_CUSTOM_LIVE_FILE=$file"
    echo "CURRENT_BLOB=$current"
    exit 1
  fi
  echo "LIVE_FILE_VERIFIED=$file"
done < <(files)

if [ "$MODE" = "--check" ]; then
  echo "STANDALONE_UPLOAD_PREFLIGHT=PASS"
  echo "LIVE_FILES_CHANGED=NO"
  echo "DATABASE_CHANGED=NO"
  exit 0
fi

echo "========== VERIFY PINNED GITHUB SOURCE =========="
test "$(gh api "repos/$REPO/commits/$SHA" --jq '.sha')" = "$SHA"
BACKUP="$(mktemp -d /root/Gomovie-Standalone-Upload-XXXXXXXX)"
chmod 700 "$BACKUP"
echo "BACKUP_DIRECTORY=$BACKUP"
mkdir -p "$BACKUP/old" "$BACKUP/new"
while IFS= read -r file; do
  mkdir -p "$BACKUP/old/$(dirname "$file")" "$BACKUP/new/$(dirname "$file")"
  cp -p "$APP/$file" "$BACKUP/old/$file"
  gh api -H "Accept: application/vnd.github.raw+json" \
    "repos/$REPO/contents/$file?ref=$SHA" > "$BACKUP/new/$file"
  test "$(git hash-object "$BACKUP/new/$file")" = "$(new_blob "$file")"
  case "$file" in
    *.php) "$PHP" -l "$BACKUP/new/$file" >/dev/null;;
  esac
  echo "GITHUB_SOURCE_VERIFIED=$file"
done < <(files)

CHANGED=0
COMPLETE=0
rollback() {
  if [ "$CHANGED" = 1 ] && [ "$COMPLETE" = 0 ]; then
    set +e
    echo "========== RESTORE ORIGINAL SOURCE FILES =========="
    while IFS= read -r file; do
      cp -p "$BACKUP/old/$file" "$APP/$file"
    done < <(files)
    (cd "$APP" && runuser -u www -- "$PHP" artisan optimize:clear) >/dev/null 2>&1
    echo "SOURCE_ROLLBACK=COMPLETE"
  fi
}
trap rollback EXIT

echo "========== INSTALL STANDALONE UPLOAD FILES =========="
CHANGED=1
while IFS= read -r file; do
  install -o root -g www -m 0644 "$BACKUP/new/$file" "$APP/$file"
done < <(files)

echo "========== VERIFY LARAVEL ROUTES =========="
(
  cd "$APP"
  runuser -u www -- "$PHP" artisan optimize:clear
  runuser -u www -- "$PHP" artisan route:list --path=upload-studio
  runuser -u www -- "$PHP" artisan route:list --path=dashboard/upload-studio
)

echo "========== VERIFY WEBSITE RESPONSES =========="
for path in / /login /register /browse /watch/series/the-flash-60735; do
  code="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
    --resolve gomovie.tonsamith.com:443:127.0.0.1 \
    -o /dev/null -w '%{http_code}' "https://gomovie.tonsamith.com$path")"
  echo "ROUTE=$path HTTP=$code"
  test "$code" = 200
done
# An unauthenticated visitor must be redirected to login, never shown the upload form.
code="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
  --resolve gomovie.tonsamith.com:443:127.0.0.1 \
  -o /dev/null -w '%{http_code}' "https://gomovie.tonsamith.com/upload-studio")"
echo "ROUTE=/upload-studio GUEST_HTTP=$code"
test "$code" = 302

echo "========== VERIFY INSTALLED FILE INTEGRITY =========="
while IFS= read -r file; do
  test "$(git hash-object "$APP/$file")" = "$(new_blob "$file")"
done < <(files)
COMPLETE=1

echo "STANDALONE_UPLOAD_STUDIO=INSTALLED"
echo "STANDALONE_ROUTE=/upload-studio"
echo "SECURITY=ADMIN_AUTH_REQUIRED"
echo "VIDEO_INTAKE=PRIVATE_QUARANTINE"
echo "DATABASE_CHANGED=NO"
echo "TRANSCODING=UNCHANGED"
echo "PAYMENTS=UNCHANGED"
echo "STREAMING=UNCHANGED"
echo "GOMOVIE_STANDALONE_DEPLOYMENT=SUCCESS"
echo "BACKUP_DIRECTORY=$BACKUP"
