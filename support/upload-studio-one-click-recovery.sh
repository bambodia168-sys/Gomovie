#!/usr/bin/env bash
# Gomovie: one-click upload success + controlled review-error recovery.
# Selective files only. Never change .env, DB, media, auth, player or billing.
set -Eeuo pipefail
umask 077

if [ "$#" -ne 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
  echo "Usage: bash upload-studio-one-click-recovery.sh --check|--apply"
  exit 2
fi

MODE="$1"
REPO="bambodia168-sys/Gomovie"
SHA="fb099b242bf46d62fd66f5c480efd8b2e1f0426b"
APP="/www/wwwroot/gomovie.tonsamith.com"
PHP="/www/server/php/85/bin/php"
HOST="gomovie.tonsamith.com"

files() {
  printf '%s\n' \
    app/Http/Controllers/Admin/UploadStudioController.php \
    resources/views/admin/upload-studio.blade.php \
    app/Services/Media/IntakeReviewService.php \
    app/Http/Controllers/Admin/MediaController.php \
    resources/views/admin/media.blade.php \
    resources/views/admin/upload-review.blade.php
}

old_blob() {
  case "$1" in
    app/Http/Controllers/Admin/UploadStudioController.php) echo dddcac7f33c3c94be0eacd3947df666480bbbdb0;;
    resources/views/admin/upload-studio.blade.php) echo b6cf3d3677121b89bbba599d68b7104f5bd35d47;;
    app/Services/Media/IntakeReviewService.php) echo a99734c500631c69c4ba5f57aa5db0876892978c;;
    app/Http/Controllers/Admin/MediaController.php) echo 663121af0db6bfc4917c99c2cf60885cc3ee28b4;;
    resources/views/admin/media.blade.php) echo d4dbb52f49d2b0b9628afb74f3050ed504850c30;;
    resources/views/admin/upload-review.blade.php) echo fb92bd9b611a3d25ee11ba0db369b540da7e8040;;
    *) return 1;;
  esac
}

new_blob() {
  case "$1" in
    app/Http/Controllers/Admin/UploadStudioController.php) echo a8c2895746679b94c928b14abbbe0aa9337141b0;;
    resources/views/admin/upload-studio.blade.php) echo 08aba8bac17486b33d9584f65b5b623eedcef4af;;
    app/Services/Media/IntakeReviewService.php) echo 7f03a85b670765784d0e43812f86ccbb8a161169;;
    app/Http/Controllers/Admin/MediaController.php) echo 8916cac8f3524c84185e918b57a9749399dceec9;;
    resources/views/admin/media.blade.php) echo ae102e2f9beeade8f092d92b23547cb8936fea67;;
    resources/views/admin/upload-review.blade.php) echo 0555ecd1999f466cb070924eb8785be6c7f0d348;;
    *) return 1;;
  esac
}

echo "========== GOMOVIE UPLOAD FLOW RECOVERY =========="
echo "MODE=$MODE"
test "$(id -u)" -eq 0
test -d "$APP" && test ! -L "$APP"
test -f "$APP/.env" && test ! -L "$APP/.env"
test -f "$APP/vendor/autoload.php"
test -f "$APP/artisan"
test -f "$APP/storage/app/private/.gomovie-installed"
test -x "$PHP"
for command_name in gh git curl runuser; do command -v "$command_name" >/dev/null; done
id www >/dev/null

echo "========== READ ONLY LIVE SOURCE PREFLIGHT =========="
while IFS= read -r relative; do
  test -f "$APP/$relative" && test ! -L "$APP/$relative"
  actual="$(git hash-object "$APP/$relative")"
  if [ "$actual" != "$(old_blob "$relative")" ] && [ "$actual" != "$(new_blob "$relative")" ]; then
    echo "BLOCKED_CUSTOM_FILE=$relative"
    echo "ACTUAL_BLOB=$actual"
    exit 1
  fi
  echo "LIVE_SOURCE_VERIFIED=$relative"
done < <(files)

if [ "$MODE" = "--check" ]; then
  echo "GOMOVIE_UPLOAD_RECOVERY_PREFLIGHT=PASS"
  echo "CHANGES=NONE"
  exit 0
fi

echo "========== PINNED GITHUB RELEASE =========="
test "$(gh api "repos/$REPO/commits/$SHA" --jq '.sha')" = "$SHA"
BACKUP="$(mktemp -d /root/Gomovie-Upload-Recovery-XXXXXXXX)"
chmod 700 "$BACKUP"
mkdir -p "$BACKUP/old" "$BACKUP/new"
echo "BACKUP_DIRECTORY=$BACKUP"

echo "========== BACKUP AND VERIFY SOURCE =========="
while IFS= read -r relative; do
  mkdir -p "$BACKUP/old/$(dirname "$relative")" "$BACKUP/new/$(dirname "$relative")"
  cp -p "$APP/$relative" "$BACKUP/old/$relative"
  gh api -H "Accept: application/vnd.github.raw+json" \
    "repos/$REPO/contents/$relative?ref=$SHA" > "$BACKUP/new/$relative"
  test "$(git hash-object "$BACKUP/new/$relative")" = "$(new_blob "$relative")"
  case "$relative" in
    *.php) "$PHP" -l "$BACKUP/new/$relative" >/dev/null;;
  esac
  echo "GITHUB_SOURCE_VERIFIED=$relative"
done < <(files)

CHANGED=0
COMPLETE=0
rollback() {
  if [ "$CHANGED" -eq 1 ] && [ "$COMPLETE" -ne 1 ]; then
    set +e
    echo "========== RESTORE PREVIOUS SOURCE =========="
    while IFS= read -r relative; do
      cp -p "$BACKUP/old/$relative" "$APP/$relative"
    done < <(files)
    (cd "$APP" && runuser -u www -- "$PHP" artisan optimize:clear) >/dev/null 2>&1
    echo "SOURCE_ROLLBACK=COMPLETE"
  fi
}
trap rollback EXIT

echo "========== APPLY SIX-FILE UPDATE =========="
CHANGED=1
while IFS= read -r relative; do
  install -o root -g www -m 0644 "$BACKUP/new/$relative" "$APP/$relative"
done < <(files)

echo "========== CLEAR LARAVEL CACHES =========="
(
  cd "$APP"
  runuser -u www -- "$PHP" artisan optimize:clear
  runuser -u www -- "$PHP" artisan route:list --path=upload-studio
)

echo "========== CHECK EXISTING PAGES =========="
for route in / /login /register /browse /watch/series/the-flash-60735; do
  status="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' \
    "https://$HOST$route")"
  echo "ROUTE=$route HTTP=$status"
  test "$status" = 200
done
guest="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
  --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' \
  "https://$HOST/upload-studio")"
echo "ROUTE=/upload-studio GUEST_HTTP=$guest"
test "$guest" = 302

while IFS= read -r relative; do
  test "$(git hash-object "$APP/$relative")" = "$(new_blob "$relative")"
done < <(files)

COMPLETE=1
echo "GOMOVIE_ONE_CLICK_UPLOAD=INSTALLED"
echo "POST_UPLOAD_APPROVAL_STEP=NOT_REQUIRED"
echo "REVIEW_ERRORS=CONTROLLED"
echo "MEDIA_INTAKE=PRIVATE"
echo "DATABASE_CHANGED=NO"
echo "EXISTING_MEDIA=UNCHANGED"
echo "STREAMING=UNCHANGED"
echo "PAYMENTS=UNCHANGED"
echo "GOMOVIE_UPLOAD_RECOVERY=SUCCESS"
echo "BACKUP_DIRECTORY=$BACKUP"
