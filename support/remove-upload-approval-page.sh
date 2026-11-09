#!/usr/bin/env bash
# Gomovie aaPanel: remove the obsolete Upload Studio approval page.
# Selective source update only; never alter .env, database, private videos,
# content rights, published media, accounts, payments, or streaming.
set -Eeuo pipefail
umask 077

if [ "$#" -ne 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
  echo "Usage: bash remove-upload-approval-page.sh --check|--apply"
  exit 2
fi
MODE="$1"
REPO="bambodia168-sys/Gomovie"
SHA="2ded7fdbb04489ece1106e3f1d6906f56c658f46"
APP="/www/wwwroot/gomovie.tonsamith.com"
PHP="/www/server/php/85/bin/php"
HOST="gomovie.tonsamith.com"
REVIEW="resources/views/admin/upload-review.blade.php"

files() {
  printf '%s\n' \
    routes/web.php \
    app/Http/Controllers/Admin/UploadStudioController.php \
    app/Http/Controllers/Admin/MediaController.php \
    resources/views/admin/media.blade.php
}
old_blob() {
  case "$1" in
    routes/web.php) echo f2edff6ae975fb3367907d0d61b2d084f8a5bee9;;
    app/Http/Controllers/Admin/UploadStudioController.php) echo a8c2895746679b94c928b14abbbe0aa9337141b0;;
    app/Http/Controllers/Admin/MediaController.php) echo 8916cac8f3524c84185e918b57a9749399dceec9;;
    resources/views/admin/media.blade.php) echo ae102e2f9beeade8f092d92b23547cb8936fea67;;
    *) return 1;;
  esac
}
new_blob() {
  case "$1" in
    routes/web.php) echo d55041091d6a7142cb0cf6dbe0e47cffcd50af37;;
    app/Http/Controllers/Admin/UploadStudioController.php) echo 20e12b56554eb104a09f8478a7fe9be035350654;;
    app/Http/Controllers/Admin/MediaController.php) echo 4204397a354773ceaf0fd6d1e8b98ae1abb26784;;
    resources/views/admin/media.blade.php) echo 1b690f0be109e24f9695f7fd9d21089f2b8db358;;
    *) return 1;;
  esac
}

echo "========== REMOVE OBSOLETE UPLOAD REVIEW PAGE =========="
echo "MODE=$MODE"
test "$(id -u)" -eq 0
test -d "$APP" && test ! -L "$APP"
test -f "$APP/.env" && test ! -L "$APP/.env"
test -f "$APP/artisan"
test -f "$APP/vendor/autoload.php"
test -f "$APP/storage/app/private/.gomovie-installed"
test -x "$PHP"
for cmd in gh git curl runuser grep; do command -v "$cmd" >/dev/null; done
id www >/dev/null

echo "========== READ-ONLY PREFLIGHT =========="
while IFS= read -r file; do
  test -f "$APP/$file" && test ! -L "$APP/$file"
  actual="$(git hash-object "$APP/$file")"
  if [ "$actual" != "$(old_blob "$file")" ] && [ "$actual" != "$(new_blob "$file")" ]; then
    echo "BLOCKED_CUSTOM_FILE=$file"
    echo "ACTUAL_BLOB=$actual"
    exit 1
  fi
  echo "LIVE_FILE_VERIFIED=$file"
done < <(files)

test ! -L "$APP/$REVIEW"
if [ -e "$APP/$REVIEW" ]; then
  test -f "$APP/$REVIEW"
  test "$(git hash-object "$APP/$REVIEW")" = "0555ecd1999f466cb070924eb8785be6c7f0d348" || {
    echo "BLOCKED_CUSTOM_REVIEW_PAGE=$REVIEW"
    exit 1
  }
  echo "OLD_REVIEW_PAGE=VERIFIED"
else
  echo "OLD_REVIEW_PAGE=ALREADY_REMOVED"
fi

if [ "$MODE" = "--check" ]; then
  echo "REMOVE_REVIEW_PAGE_PREFLIGHT=PASS"
  echo "FILES_CHANGED=NO"
  echo "DATABASE_CHANGED=NO"
  exit 0
fi

echo "========== VERIFY PINNED RELEASE =========="
test "$(gh api "repos/$REPO/commits/$SHA" --jq '.sha')" = "$SHA"
BACKUP="$(mktemp -d /root/Gomovie-Review-Page-Removal-XXXXXXXX)"
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
  echo "NEW_SOURCE_VERIFIED=$file"
done < <(files)

mkdir -p "$BACKUP/old/$(dirname "$REVIEW")"
if [ -f "$APP/$REVIEW" ]; then
  cp -p "$APP/$REVIEW" "$BACKUP/old/$REVIEW"
fi

CHANGED=0
COMPLETE=0
rollback() {
  if [ "$CHANGED" -eq 1 ] && [ "$COMPLETE" -ne 1 ]; then
    set +e
    echo "========== RESTORE PREVIOUS SOURCE =========="
    while IFS= read -r file; do
      cp -p "$BACKUP/old/$file" "$APP/$file"
    done < <(files)
    if [ -f "$BACKUP/old/$REVIEW" ]; then
      cp -p "$BACKUP/old/$REVIEW" "$APP/$REVIEW"
    else
      rm -f -- "$APP/$REVIEW"
    fi
    (cd "$APP" && runuser -u www -- "$PHP" artisan optimize:clear) >/dev/null 2>&1
    echo "SOURCE_ROLLBACK=COMPLETE"
  fi
}
trap rollback EXIT

echo "========== INSTALL SIMPLE UPLOADER ONLY =========="
CHANGED=1
while IFS= read -r file; do
  install -o root -g www -m 0644 "$BACKUP/new/$file" "$APP/$file"
done < <(files)
rm -f -- "$APP/$REVIEW"

echo "========== CLEAR CACHES AND VERIFY ROUTES =========="
ROUTES="$(
  cd "$APP"
  runuser -u www -- "$PHP" artisan optimize:clear
  runuser -u www -- "$PHP" artisan route:list --path=upload-studio
)"
printf '%s\n' "$ROUTES"
printf '%s\n' "$ROUTES" | grep -Fq "upload-studio.index"
printf '%s\n' "$ROUTES" | grep -Fq "upload-studio.upload"
if printf '%s\n' "$ROUTES" | grep -Eq 'upload-studio[.]approve|upload-studio[.]reject|upload-studio[.]review'; then
  echo "BLOCKED_REVIEW_ACTION_STILL_REGISTERED"
  exit 1
fi

echo "========== WEBSITE SMOKE CHECKS =========="
for route in / /login /register /browse /watch/series/the-flash-60735; do
  status="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' "https://$HOST$route")"
  echo "ROUTE=$route HTTP=$status"
  test "$status" = 200
done
guest="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' "https://$HOST/upload-studio")"
echo "ROUTE=/upload-studio GUEST_HTTP=$guest"
test "$guest" = 302

while IFS= read -r file; do
  test "$(git hash-object "$APP/$file")" = "$(new_blob "$file")"
done < <(files)
test ! -e "$APP/$REVIEW"
test ! -L "$APP/$REVIEW"

COMPLETE=1
echo "GOMOVIE_APPROVAL_PAGE=REMOVED"
echo "GOMOVIE_UPLOAD_STUDIO=/upload-studio"
echo "OLD_REVIEW_LINKS=MEDIA_LIBRARY_REDIRECT"
echo "UPLOAD_APPROVAL_ROUTES=REMOVED"
echo "VIDEO_SECURITY=PRIVATE_QUARANTINE"
echo "DATABASE_CHANGED=NO"
echo "EXISTING_MEDIA=UNCHANGED"
echo "STREAMING=UNCHANGED"
echo "PAYMENTS=UNCHANGED"
echo "GOMOVIE_REVIEW_PAGE_REMOVAL=SUCCESS"
echo "BACKUP_DIRECTORY=$BACKUP"
