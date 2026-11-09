#!/usr/bin/env bash
# Gomovie: selective shared toast + existing artwork preview.
# Never changes private videos, the database, streaming, rights, billing or .env.
set -Eeuo pipefail
umask 077

if [ "$#" -ne 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
  echo "Usage: bash studio-feedback-artwork-upgrade.sh --check|--apply"
  exit 2
fi
MODE="$1"
REPO="bambodia168-sys/Gomovie"
SOURCE="6fbd6a9b7ca9138a380266aa9abcba4d9160f3f0"
APP="/www/wwwroot/gomovie.tonsamith.com"
PHP="/www/server/php/85/bin/php"
NEW_PARTIAL="resources/views/partials/feedback.blade.php"

files() {
  printf '%s\n' \
    resources/views/partials/feedback.blade.php \
    resources/views/layouts/admin.blade.php \
    resources/views/layouts/app.blade.php \
    resources/views/layouts/auth.blade.php \
    resources/views/admin/upload-studio.blade.php
}

old_blob() {
  case "$1" in
    resources/views/partials/feedback.blade.php) echo NEW;;
    resources/views/layouts/admin.blade.php) echo 23ed146c494703d71e993e6bc39cbd1a94059577;;
    resources/views/layouts/app.blade.php) echo 686241416399ff90fba3baaff3aa6312e6cea5f3;;
    resources/views/layouts/auth.blade.php) echo 724f61b452739b4a952b7113435e210a9fbccb34;;
    resources/views/admin/upload-studio.blade.php) echo 78354f0927030675c82777bb22960e007f95a9bb;;
    *) return 1;;
  esac
}

new_blob() {
  case "$1" in
    resources/views/partials/feedback.blade.php) echo 1753be3765dcd3f95060506747670451aa1d1435;;
    resources/views/layouts/admin.blade.php) echo 49be5e487d23e79f7c91e3cb4a19b68b916e9b4e;;
    resources/views/layouts/app.blade.php) echo a1af8f5afcd6ac7a8f7bcd237ff00d334197b7dd;;
    resources/views/layouts/auth.blade.php) echo 33518588ec4eaf1cd0ed6f3baff86de3501526c0;;
    resources/views/admin/upload-studio.blade.php) echo ac084e694581a2ee03d7e2000ae0f2ce772b6369;;
    *) return 1;;
  esac
}

echo "========== GOMOVIE FEEDBACK + ARTWORK =========="
echo "MODE=$MODE"
test "$(id -u)" -eq 0
test -d "$APP" && test ! -L "$APP"
test -f "$APP/.env" && test ! -L "$APP/.env"
test -f "$APP/vendor/autoload.php"
test -f "$APP/artisan"
test -x "$PHP"
for binary in gh git curl runuser; do command -v "$binary" >/dev/null; done
id www >/dev/null

echo "========== READ-ONLY FILE COMPATIBILITY =========="
while IFS= read -r relative; do
  expected_old="$(old_blob "$relative")"
  expected_new="$(new_blob "$relative")"
  if [ ! -e "$APP/$relative" ] && [ "$expected_old" = NEW ]; then
    echo "NEW_FILE_NOT_YET_INSTALLED=$relative"
    continue
  fi
  test -f "$APP/$relative" && test ! -L "$APP/$relative"
  actual="$(git hash-object "$APP/$relative")"
  if [ "$actual" != "$expected_old" ] && [ "$actual" != "$expected_new" ]; then
    echo "BLOCKED_CUSTOM_FILE=$relative"
    echo "ACTUAL_BLOB=$actual"
    exit 1
  fi
  echo "COMPATIBLE_FILE=$relative"
done < <(files)

if [ "$MODE" = "--check" ]; then
  echo "GOMOVIE_FEEDBACK_ARTWORK_PREFLIGHT=PASS"
  echo "LIVE_FILES_CHANGED=NO"
  echo "DATABASE_CHANGED=NO"
  exit 0
fi

echo "========== VERIFY PINNED GITHUB SOURCE =========="
test "$(gh api "repos/$REPO/commits/$SOURCE" --jq '.sha')" = "$SOURCE"
BACKUP="$(mktemp -d /root/Gomovie-Feedback-Artwork-XXXXXXXX)"
chmod 700 "$BACKUP"
mkdir -p "$BACKUP/original" "$BACKUP/release"
echo "BACKUP_DIRECTORY=$BACKUP"
while IFS= read -r relative; do
  mkdir -p "$BACKUP/original/$(dirname "$relative")" "$BACKUP/release/$(dirname "$relative")"
  if [ -f "$APP/$relative" ]; then
    cp -p "$APP/$relative" "$BACKUP/original/$relative"
  fi
  gh api -H "Accept: application/vnd.github.raw+json" \
    "repos/$REPO/contents/$relative?ref=$SOURCE" > "$BACKUP/release/$relative"
  test "$(git hash-object "$BACKUP/release/$relative")" = "$(new_blob "$relative")"
done < <(files)
echo "SOURCE_BLOBS=VERIFIED"

CHANGED=0
COMPLETE=0
rollback() {
  if [ "$CHANGED" -eq 1 ] && [ "$COMPLETE" -eq 0 ]; then
    set +e
    echo "========== RESTORE ALL ORIGINAL BLADE FILES =========="
    while IFS= read -r relative; do
      if [ -f "$BACKUP/original/$relative" ]; then
        cp -p "$BACKUP/original/$relative" "$APP/$relative"
      elif [ "$relative" = "$NEW_PARTIAL" ]; then
        rm -f -- "$APP/$relative"
      fi
    done < <(files)
    ( cd "$APP" && runuser -u www -- "$PHP" artisan view:clear ) >/dev/null 2>&1
    echo "BLADE_ROLLBACK=COMPLETE"
  fi
}
trap rollback EXIT

echo "========== INSTALL ONLY FIVE BLADE FILES =========="
CHANGED=1
while IFS= read -r relative; do
  install -o root -g www -m 0644 "$BACKUP/release/$relative" "$APP/$relative"
done < <(files)

echo "========== RECOMPILE VIEWS =========="
(
  cd "$APP"
  runuser -u www -- "$PHP" artisan view:clear --no-interaction
  runuser -u www -- "$PHP" artisan view:cache --no-interaction
)

echo "========== PUBLIC ROUTE SMOKE TESTS =========="
for path in / /about /login; do
  status="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
    --resolve gomovie.tonsamith.com:443:127.0.0.1 \
    -o /dev/null -w '%{http_code}' "https://gomovie.tonsamith.com$path")"
  echo "ROUTE=$path HTTP=$status"
  test "$status" = 200
done

echo "========== VERIFY INSTALLED CONTENT =========="
while IFS= read -r relative; do
  test "$(git hash-object "$APP/$relative")" = "$(new_blob "$relative")"
done < <(files)

COMPLETE=1
echo "GLOBAL_TOP_RIGHT_FEEDBACK=INSTALLED"
echo "ARTWORK_EXISTING_PREVIEW=INSTALLED"
echo "STUDIO_PRIVATE_UPLOAD=UNCHANGED"
echo "PUBLIC_PLAYBACK=UNCHANGED"
echo "DATABASE_CHANGED=NO"
echo "EXISTING_VIDEO_FILES=UNCHANGED"
echo "GOMOVIE_FEEDBACK_ARTWORK_DEPLOYMENT=SUCCESS"
echo "BACKUP_DIRECTORY=$BACKUP"
