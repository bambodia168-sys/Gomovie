#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
if [ "$#" != 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
  echo "Usage: bash quick-upload-upgrade.sh --check|--apply"; exit 2
fi
MODE="$1"
APP="/www/wwwroot/gomovie.tonsamith.com"
PHP="/www/server/php/85/bin/php"
REPO="bambodia168-sys/Gomovie"
SOURCE="38ef8c407beda31201b10bc11bfce56933566868"
FILES=(
  app/Http/Controllers/Admin/QuickUploadController.php
  resources/views/admin/quick-upload.blade.php
  app/Services/Media/PrivateVideoIntake.php
  routes/web.php
  app/Http/Controllers/Admin/MediaController.php
  resources/views/admin/media.blade.php
)
OLD=(
  ABSENT
  ABSENT
  b59937b1d14763ac6f04da1b0a62fa0ace07e551
  784d13f564db5dacc3625a9e34b186446dc42957
  4d526d60650f0dc8a4b262b5ba622f10c56585ca
  b3deafa3eee6e539e7cea5e7d7b3fe8b3183d239
)
NEW=(
  2bfde453259537bb081d67c4d5612c582832bd12
  6dcf49d3be93a2717a3e9f66b096a0f198371896
  d1443fa797ddb4716af4d070e9221b6d91ffd443
  ef3507ba2e0b956e793865fdc4bd34a233efc0a1
  ee46869680763decf86a393aadeb40e98380236d
  2c01eca5a75c0a1f17b2a301830319ccd8bab895
)
echo "========== GOMOVIE QUICK UPLOAD $MODE =========="
test "$(id -u)" -eq 0
test -d "$APP" && test ! -L "$APP"
test -f "$APP/.env" && test ! -L "$APP/.env"
test -f "$APP/vendor/autoload.php" && test -x "$PHP"
for cmd in gh git runuser mktemp cp install curl systemctl; do command -v "$cmd" >/dev/null; done
id www >/dev/null
systemctl is-active --quiet gomovie-media-worker.service
for i in "${!FILES[@]}"; do
  f="$APP/${FILES[$i]}"
  if [ -e "$f" ] || [ -L "$f" ]; then
    test -f "$f" && test ! -L "$f"
    hash="$(git hash-object "$f")"
  else
    hash=ABSENT
  fi
  if [ "$hash" = "${OLD[$i]}" ]; then
    echo "BASELINE=${FILES[$i]}"
  elif [ "$hash" = "${NEW[$i]}" ]; then
    echo "ALREADY_INSTALLED=${FILES[$i]}"
  else
    echo "BLOCKED_CUSTOM_FILE=${FILES[$i]}"
    echo "CURRENT_BLOB=$hash"
    exit 30
  fi
done
if [ "$MODE" = --check ]; then
  echo "QUICK_UPLOAD_PREFLIGHT=PASS"
  echo "PRODUCTION_UNCHANGED=YES"
  exit 0
fi
BACKUP="$(mktemp -d /root/Gomovie-QuickUpload-XXXXXXXX)"
chmod 0700 "$BACKUP"
mkdir -p "$BACKUP/old" "$BACKUP/new"
for i in "${!FILES[@]}"; do
  rel="${FILES[$i]}"
  mkdir -p "$BACKUP/old/$(dirname "$rel")" "$BACKUP/new/$(dirname "$rel")"
  if [ -f "$APP/$rel" ]; then cp -p "$APP/$rel" "$BACKUP/old/$rel"; fi
done
echo "ROLLBACK_BACKUP=$BACKUP"
CHANGED=0
COMPLETE=0
restore() {
  code=$?
  if [ "$CHANGED" -eq 1 ] && [ "$COMPLETE" -eq 0 ]; then
    set +e
    echo "========== ROLL BACK QUICK UPLOAD =========="
    for rel in "${FILES[@]}"; do
      if [ -f "$BACKUP/old/$rel" ]; then
        cp -p "$BACKUP/old/$rel" "$APP/$rel"
      else
        rm -f -- "$APP/$rel"
      fi
    done
    (cd "$APP"; runuser -u www -- "$PHP" artisan route:clear --no-interaction; runuser -u www -- "$PHP" artisan view:clear --no-interaction) >/dev/null 2>&1
    echo "QUICK_UPLOAD_ROLLBACK=ATTEMPTED"
    code=1
  fi
  exit "$code"
}
trap restore EXIT
echo "========== DOWNLOAD PINNED RELEASE =========="
for i in "${!FILES[@]}"; do
  rel="${FILES[$i]}"
  gh api -H "Accept: application/vnd.github.raw+json" \
    "repos/$REPO/contents/$rel?ref=$SOURCE" > "$BACKUP/new/$rel"
  test "$(git hash-object "$BACKUP/new/$rel")" = "${NEW[$i]}"
  case "$rel" in *.php) "$PHP" -l "$BACKUP/new/$rel" >/dev/null ;; esac
  echo "PINNED_FILE=$rel"
done
CHANGED=1
echo "========== INSTALL ONLY SIX APPLICATION FILES =========="
for i in "${!FILES[@]}"; do
  rel="${FILES[$i]}"
  install -o www -g www -m 0644 "$BACKUP/new/$rel" "$APP/$rel"
  test "$(git hash-object "$APP/$rel")" = "${NEW[$i]}"
done
echo "========== VERIFY ROUTES AND TEMPLATES =========="
(
  cd "$APP"
  runuser -u www -- "$PHP" artisan route:clear --no-interaction
  runuser -u www -- "$PHP" artisan view:clear --no-interaction
  runuser -u www -- "$PHP" artisan view:cache --no-interaction
  runuser -u www -- "$PHP" artisan route:list --path=upload-studio
)
for endpoint in / /browse /login; do
  code="$(curl --noproxy '*' -ksS --connect-timeout 5 --max-time 20 \
    --resolve gomovie.tonsamith.com:443:127.0.0.1 \
    -o /dev/null -w '%{http_code}' "https://gomovie.tonsamith.com$endpoint")"
  echo "PAGE=$endpoint HTTP=$code"
  test "$code" = 200
done
systemctl is-active --quiet gomovie-media-worker.service
COMPLETE=1
echo "QUICK_UPLOAD_DEPLOYMENT=SUCCESS"
echo "DATABASE_CHANGED=NO"
echo "EXISTING_MEDIA_CHANGED=NO"
echo "PUBLICATION_CONFIG=UNCHANGED"
echo "ROLLBACK_BACKUP=$BACKUP"
