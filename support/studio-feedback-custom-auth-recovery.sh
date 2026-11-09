#!/usr/bin/env bash
# Gomovie Upload Studio toast/artwork recovery for a customized auth layout.
# Preserves the existing auth markup; never overwrites it with GitHub's version.
# Does not modify .env, database, private video, playback, streaming or billing.
set -Eeuo pipefail
umask 077

if [ "$#" -ne 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
  echo "Usage: bash studio-feedback-custom-auth-recovery.sh --check|--apply"
  exit 2
fi
MODE="$1"
APP="/www/wwwroot/gomovie.tonsamith.com"
PHP="/www/server/php/85/bin/php"
HOST="gomovie.tonsamith.com"
REPO="bambodia168-sys/Gomovie"
SOURCE="c7f5ae31c577b83e25f080927af99e63f6748cd3"
AUTH="resources/views/layouts/auth.blade.php"
PARTIAL="resources/views/partials/feedback.blade.php"

files() {
  printf '%s\n' \
    resources/views/partials/feedback.blade.php \
    app/Http/Controllers/Admin/UploadStudioController.php \
    resources/views/layouts/admin.blade.php \
    resources/views/layouts/app.blade.php \
    resources/views/admin/upload-studio.blade.php
}
old_blob() {
  case "$1" in
    resources/views/partials/feedback.blade.php) echo NEW;;
    app/Http/Controllers/Admin/UploadStudioController.php) echo 9b702c7e2a5bed3a2c156745c5583b92e86c7767;;
    resources/views/layouts/admin.blade.php) echo 23ed146c494703d71e993e6bc39cbd1a94059577;;
    resources/views/layouts/app.blade.php) echo 686241416399ff90fba3baaff3aa6312e6cea5f3;;
    resources/views/admin/upload-studio.blade.php) echo 78354f0927030675c82777bb22960e007f95a9bb;;
    *) return 1;;
  esac
}
new_blob() {
  case "$1" in
    resources/views/partials/feedback.blade.php) echo 1753be3765dcd3f95060506747670451aa1d1435;;
    app/Http/Controllers/Admin/UploadStudioController.php) echo d7ad7165e6ca85e76000fb9382a7aae90d67e5e3;;
    resources/views/layouts/admin.blade.php) echo 49be5e487d23e79f7c91e3cb4a19b68b916e9b4e;;
    resources/views/layouts/app.blade.php) echo a1af8f5afcd6ac7a8f7bcd237ff00d334197b7dd;;
    resources/views/admin/upload-studio.blade.php) echo dfec39a9af4b7f5812d3c090473d3322c964e614;;
    *) return 1;;
  esac
}

auth_plan() {
  python3 - "$1" "$2" <<'PY'
import pathlib, re, sys
source = pathlib.Path(sys.argv[1])
dest = None if sys.argv[2] == '--check' else pathlib.Path(sys.argv[2])
raw = source.read_bytes()
if b'\x00' in raw or len(raw) > 1024 * 1024:
    raise SystemExit('BLOCKED_AUTH_NOT_TEXT_OR_TOO_LARGE')
try:
    content = raw.decode('utf-8')
except UnicodeDecodeError:
    raise SystemExit('BLOCKED_AUTH_INVALID_UTF8')
include = re.compile(r"@include\s*\(\s*['\"]partials\.feedback['\"]\s*\)")
count = len(include.findall(content))
if count > 1:
    raise SystemExit('BLOCKED_DUPLICATED_FEEDBACK_INCLUDE')
if not re.search(r'<head(?:\s[^>]*)?>', content, re.I):
    raise SystemExit('BLOCKED_AUTH_HEAD_MISSING')
closings = list(re.finditer(r'</head\s*>', content, re.I))
if len(closings) != 1 or len(re.findall(r'<head(?:\s[^>]*)?>', content, re.I)) != 1:
    raise SystemExit('BLOCKED_AUTH_HEAD_NOT_UNIQUE')
if count == 1:
    print('AUTH_LAYOUT=FEEDBACK_ALREADY_INCLUDED')
    updated = content
else:
    print('AUTH_LAYOUT=CUSTOM_PRESERVED_ADD_INCLUDE_ONLY')
    at = closings[0].start()
    updated = content[:at] + "@include('partials.feedback')\n" + content[at:]
if dest is not None:
    dest.write_bytes(updated.encode('utf-8'))
PY
}

echo "========== GOMOVIE CUSTOM AUTH PRESERVATION =========="
echo "MODE=$MODE"
test "$(id -u)" -eq 0
test -d "$APP" && test ! -L "$APP"
test -f "$APP/artisan"
test -f "$APP/.env" && test ! -L "$APP/.env"
test -f "$APP/vendor/autoload.php"
test -x "$PHP"
for binary in git gh curl runuser python3; do command -v "$binary" >/dev/null; done
id www >/dev/null

echo "========== READ-ONLY KNOWN FILE PREFLIGHT =========="
while IFS= read -r relative; do
  old="$(old_blob "$relative")"
  new="$(new_blob "$relative")"
  if [ "$old" = NEW ] && [ ! -e "$APP/$relative" ]; then
    test ! -L "$APP/$relative"
    echo "NEW_FILE_NOT_YET_INSTALLED=$relative"
    continue
  fi
  test -f "$APP/$relative" && test ! -L "$APP/$relative"
  current="$(git hash-object "$APP/$relative")"
  if [ "$current" != "$old" ] && [ "$current" != "$new" ]; then
    echo "BLOCKED_CUSTOM_FILE=$relative"
    echo "CURRENT_BLOB=$current"
    exit 1
  fi
  echo "COMPATIBLE_FILE=$relative"
done < <(files)

test -f "$APP/$AUTH" && test ! -L "$APP/$AUTH"
ORIGINAL_AUTH_BLOB="$(git hash-object "$APP/$AUTH")"
echo "AUTH_EXISTING_BLOB=$ORIGINAL_AUTH_BLOB"
auth_plan "$APP/$AUTH" --check
if [ "$MODE" = "--check" ]; then
  echo "GOMOVIE_FEEDBACK_CUSTOM_AUTH_PREFLIGHT=PASS"
  echo "FILES_MODIFIED=NO"
  echo "DATABASE_MODIFIED=NO"
  exit 0
fi

echo "========== VERIFY IMMUTABLE GITHUB RELEASE =========="
test "$(gh api "repos/$REPO/commits/$SOURCE" --jq '.sha')" = "$SOURCE"
BACKUP="$(mktemp -d /root/Gomovie-Feedback-Custom-Auth-XXXXXXXX)"
chmod 700 "$BACKUP"
mkdir -p "$BACKUP/original" "$BACKUP/release"
echo "BACKUP_DIRECTORY=$BACKUP"
for relative in "$AUTH"; do
  mkdir -p "$BACKUP/original/$(dirname "$relative")" "$BACKUP/release/$(dirname "$relative")"
  cp -p "$APP/$relative" "$BACKUP/original/$relative"
done
while IFS= read -r relative; do
  mkdir -p "$BACKUP/original/$(dirname "$relative")" "$BACKUP/release/$(dirname "$relative")"
  if [ -e "$APP/$relative" ]; then cp -p "$APP/$relative" "$BACKUP/original/$relative"; fi
  gh api -H "Accept: application/vnd.github.raw+json" \
    "repos/$REPO/contents/$relative?ref=$SOURCE" > "$BACKUP/release/$relative"
  test "$(git hash-object "$BACKUP/release/$relative")" = "$(new_blob "$relative")"
  case "$relative" in *.php) "$PHP" -l "$BACKUP/release/$relative" >/dev/null;; esac
done < <(files)
echo "GITHUB_SOURCE_BLOBS=VERIFIED"

echo "========== PREPARE NON-DESTRUCTIVE AUTH PATCH =========="
auth_plan "$BACKUP/original/$AUTH" "$BACKUP/release/$AUTH"
test "$(git hash-object "$APP/$AUTH")" = "$ORIGINAL_AUTH_BLOB" || {
  echo "BLOCKED_AUTH_CHANGED_DURING_BACKUP"
  exit 1
}
# Reject any concurrent change to the other source files during download.
while IFS= read -r relative; do
  if [ -f "$BACKUP/original/$relative" ]; then
    test "$(git hash-object "$APP/$relative")" = "$(git hash-object "$BACKUP/original/$relative")"
  else
    test ! -e "$APP/$relative" && test ! -L "$APP/$relative"
  fi
done < <(files)

CHANGED=0
COMPLETE=0
rollback() {
  if [ "$CHANGED" -eq 1 ] && [ "$COMPLETE" -eq 0 ]; then
    set +e
    echo "========== RESTORE PRE-DEPLOY FILES =========="
    cp -p "$BACKUP/original/$AUTH" "$APP/$AUTH"
    while IFS= read -r relative; do
      if [ -f "$BACKUP/original/$relative" ]; then
        cp -p "$BACKUP/original/$relative" "$APP/$relative"
      elif [ "$relative" = "$PARTIAL" ]; then
        rm -f -- "$APP/$relative"
      fi
    done < <(files)
    (cd "$APP" && runuser -u www -- "$PHP" artisan view:clear) >/dev/null 2>&1
    echo "SOURCE_ROLLBACK=COMPLETE"
  fi
}
trap rollback EXIT

echo "========== APPLY SELECTIVE UPDATE =========="
CHANGED=1
while IFS= read -r relative; do
  install -o root -g www -m 0644 "$BACKUP/release/$relative" "$APP/$relative"
done < <(files)
# Preserve ALL existing auth content, ownership and mode; insert one Blade include.
cp -p "$BACKUP/release/$AUTH" "$APP/$AUTH"

echo "========== COMPILE BLADE AND TEST ROUTES =========="
(
  cd "$APP"
  runuser -u www -- "$PHP" artisan view:clear --no-interaction
  runuser -u www -- "$PHP" artisan view:cache --no-interaction
)
for path in / /about /login; do
  status="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' \
    "https://$HOST$path")"
  echo "ROUTE=$path HTTP=$status"
  test "$status" = 200
done

echo "========== VERIFY INSTALLED FILES =========="
while IFS= read -r relative; do
  test "$(git hash-object "$APP/$relative")" = "$(new_blob "$relative")"
done < <(files)
test "$(git hash-object "$APP/$AUTH")" = "$(git hash-object "$BACKUP/release/$AUTH")"
auth_plan "$APP/$AUTH" --check
COMPLETE=1
echo "AUTH_LAYOUT=CUSTOMIZATION_PRESERVED"
echo "GLOBAL_TOP_RIGHT_FEEDBACK=INSTALLED"
echo "EXISTING_ARTWORK_PREVIEW=INSTALLED"
echo "DATABASE_CHANGED=NO"
echo "PRIVATE_MEDIA_CHANGED=NO"
echo "STREAMING_CHANGED=NO"
echo "PUBLIC_PLAYBACK=UNCHANGED"
echo "GOMOVIE_FEEDBACK_RECOVERY=SUCCESS"
echo "BACKUP_DIRECTORY=$BACKUP"
