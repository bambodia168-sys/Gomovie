#!/usr/bin/env bash
# Gomovie Upload Studio: five-stage sample-inspired UI wired to existing features.
# Deploys only six known files, including one new private workflow controller.
# Does NOT modify database, .env, uploads, content rights, streaming or payments.
set -Eeuo pipefail
umask 077

if [ "$#" -ne 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
  echo "Usage: bash sample-inspired-real-upload-upgrade.sh --check|--apply"
  exit 2
fi
MODE="$1"
REPO="bambodia168-sys/Gomovie"
SHA="427ed88cfd705a97e1d64df902328f67d586fc63"
APP="/www/wwwroot/gomovie.tonsamith.com"
PHP="/www/server/php/85/bin/php"
HOST="gomovie.tonsamith.com"
NEW_CONTROLLER="app/Http/Controllers/Admin/UploadStudioWorkflowController.php"
files() {
  printf '%s\n' \
    routes/web.php \
    app/Http/Controllers/Admin/UploadStudioController.php \
    app/Http/Controllers/Admin/UploadStudioWorkflowController.php \
    resources/views/admin/upload-studio.blade.php \
    app/Http/Controllers/Admin/MediaController.php \
    resources/views/admin/media.blade.php
}
old_blob() {
  case "$1" in
    routes/web.php) echo d55041091d6a7142cb0cf6dbe0e47cffcd50af37;;
    app/Http/Controllers/Admin/UploadStudioController.php) echo 20e12b56554eb104a09f8478a7fe9be035350654;;
    app/Http/Controllers/Admin/UploadStudioWorkflowController.php) echo NEW_FILE;;
    resources/views/admin/upload-studio.blade.php) echo 08aba8bac17486b33d9584f65b5b623eedcef4af;;
    app/Http/Controllers/Admin/MediaController.php) echo 4204397a354773ceaf0fd6d1e8b98ae1abb26784;;
    resources/views/admin/media.blade.php) echo 1b690f0be109e24f9695f7fd9d21089f2b8db358;;
    *) return 1;;
  esac
}
new_blob() {
  case "$1" in
    routes/web.php) echo 46f6aad5f506e8a0e6ff66838907f28bd733a793;;
    app/Http/Controllers/Admin/UploadStudioController.php) echo 9b702c7e2a5bed3a2c156745c5583b92e86c7767;;
    app/Http/Controllers/Admin/UploadStudioWorkflowController.php) echo e6d3180ade2f1c48c324734baea643e798eef635;;
    resources/views/admin/upload-studio.blade.php) echo 78354f0927030675c82777bb22960e007f95a9bb;;
    app/Http/Controllers/Admin/MediaController.php) echo 02af15a694a8ad41dd055560705c10c23e914086;;
    resources/views/admin/media.blade.php) echo 162d36ec7271711f1f2aaac7a97440a6e6974073;;
    *) return 1;;
  esac
}
echo "========== GOMOVIE REAL FIVE-STAGE UPLOAD STUDIO =========="
echo "MODE=$MODE"
test "$(id -u)" -eq 0
test -d "$APP" && test ! -L "$APP"
test -f "$APP/.env" && test ! -L "$APP/.env"
test -f "$APP/artisan"
test -f "$APP/vendor/autoload.php"
test -f "$APP/storage/app/private/.gomovie-installed"
test -x "$PHP"
for cmd in gh git curl runuser; do command -v "$cmd" >/dev/null; done
id www >/dev/null

echo "========== READ-ONLY LIVE SOURCE PREFLIGHT =========="
while IFS= read -r file; do
  if [ "$file" = "$NEW_CONTROLLER" ] && [ ! -e "$APP/$file" ]; then
    test ! -L "$APP/$file"
    echo "NEW_CONTROLLER=NOT_INSTALLED"
    continue
  fi
  test -f "$APP/$file" && test ! -L "$APP/$file"
  hash="$(git hash-object "$APP/$file")"
  if [ "$hash" != "$(old_blob "$file")" ] && [ "$hash" != "$(new_blob "$file")" ]; then
    echo "BLOCKED_CUSTOM_SOURCE=$file"
    echo "CURRENT_BLOB=$hash"
    exit 1
  fi
  echo "SOURCE_VERIFIED=$file"
done < <(files)

if [ "$MODE" = "--check" ]; then
  echo "GOMOVIE_REAL_STUDIO_PREFLIGHT=PASS"
  echo "LIVE_FILES_CHANGED=NO"
  echo "DATABASE_CHANGED=NO"
  exit 0
fi

echo "========== VERIFY EXACT GITHUB COMMIT =========="
test "$(gh api "repos/$REPO/commits/$SHA" --jq '.sha')" = "$SHA"
BACKUP="$(mktemp -d /root/Gomovie-Real-Upload-Studio-XXXXXXXX)"
chmod 700 "$BACKUP"
mkdir -p "$BACKUP/old" "$BACKUP/new"
echo "BACKUP_DIRECTORY=$BACKUP"
ORIGINAL_NEW_CONTROLLER=NO
test -f "$APP/$NEW_CONTROLLER" && ORIGINAL_NEW_CONTROLLER=YES

echo "========== DOWNLOAD AND VERIFY ALL FILES =========="
while IFS= read -r file; do
  mkdir -p "$BACKUP/old/$(dirname "$file")" "$BACKUP/new/$(dirname "$file")"
  if [ -f "$APP/$file" ]; then
    cp -p "$APP/$file" "$BACKUP/old/$file"
  fi
  gh api -H "Accept: application/vnd.github.raw+json" \
    "repos/$REPO/contents/$file?ref=$SHA" > "$BACKUP/new/$file"
  test "$(git hash-object "$BACKUP/new/$file")" = "$(new_blob "$file")"
  case "$file" in *.php) "$PHP" -l "$BACKUP/new/$file" >/dev/null;; esac
  echo "GITHUB_SOURCE_VERIFIED=$file"
done < <(files)

CHANGED=0
COMPLETE=0
rollback() {
  if [ "$CHANGED" -eq 1 ] && [ "$COMPLETE" -ne 1 ]; then
    set +e
    echo "========== ROLLBACK SOURCE FILES =========="
    while IFS= read -r file; do
      if [ -f "$BACKUP/old/$file" ]; then
        cp -p "$BACKUP/old/$file" "$APP/$file"
      elif [ "$file" = "$NEW_CONTROLLER" ] && [ "$ORIGINAL_NEW_CONTROLLER" = NO ]; then
        rm -f -- "$APP/$file"
      fi
    done < <(files)
    (cd "$APP" && runuser -u www -- "$PHP" artisan optimize:clear) >/dev/null 2>&1
    echo "SOURCE_ROLLBACK=COMPLETE"
  fi
}
trap rollback EXIT

echo "========== APPLY SELECTIVE STUDIO FILES =========="
CHANGED=1
while IFS= read -r file; do
  install -o root -g www -m 0644 "$BACKUP/new/$file" "$APP/$file"
done < <(files)

echo "========== CLEAR CACHES AND VERIFY ROUTES =========="
ROUTES="$(
  cd "$APP"
  runuser -u www -- "$PHP" artisan optimize:clear
  runuser -u www -- "$PHP" artisan route:list --path=upload-studio
)"
printf '%s\n' "$ROUTES"
for required in upload-studio.index upload-studio.upload upload-studio.status \
  upload-studio.details upload-studio.artwork upload-studio.access; do
  printf '%s\n' "$ROUTES" | grep -Fq "$required"
done
if printf '%s\n' "$ROUTES" | grep -Eq 'upload-studio[.]approve|upload-studio[.]reject'; then
  echo "UNEXPECTED_REVIEW_ROUTE=FOUND"
  exit 1
fi

echo "========== READ-ONLY WEB SMOKE TESTS =========="
for route in / /login /register /browse /watch/series/the-flash-60735; do
  status="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' "https://$HOST$route")"
  echo "ROUTE=$route HTTP=$status"
  test "$status" = 200
done
guest="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
  --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' \
  "https://$HOST/upload-studio")"
echo "ROUTE=/upload-studio GUEST_HTTP=$guest"
test "$guest" = 302

echo "========== VERIFY EXACT INSTALLED FILES =========="
while IFS= read -r file; do
  test "$(git hash-object "$APP/$file")" = "$(new_blob "$file")"
done < <(files)
COMPLETE=1
echo "GOMOVIE_REAL_STUDIO=INSTALLED"
echo "STUDIO_ROUTE=/upload-studio"
echo "STUDIO_STAGES=5"
echo "REAL_FUNCTIONS=UPLOAD_DETAILS_ARTWORK_CATALOG_ACCESS_RESUME"
echo "POST_UPLOAD_APPROVAL=NOT_REQUIRED"
echo "VIDEO_STORAGE=PRIVATE"
echo "PUBLIC_VIDEO_LAUNCH=UNCHANGED_DISABLED"
echo "DATABASE_CHANGED=NO"
echo "EXISTING_MEDIA=UNCHANGED"
echo "STREAMING=UNCHANGED"
echo "PAYMENTS=UNCHANGED"
echo "GOMOVIE_REAL_STUDIO_DEPLOYMENT=SUCCESS"
echo "BACKUP_DIRECTORY=$BACKUP"
