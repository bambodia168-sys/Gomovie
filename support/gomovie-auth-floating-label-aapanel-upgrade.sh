#!/usr/bin/env bash
# Go Movie: center empty authentication labels, float on focus or filled/autofilled value
# across all customized authentication pages. Shared brand-head partial ONLY.
set -Eeuo pipefail
umask 077
if [ "$#" -ne 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
  echo "Usage: bash gomovie-auth-floating-label-aapanel-upgrade.sh --check|--apply" >&2
  exit 2
fi
MODE="$1"
REPO="bambodia168-sys/Gomovie"
SOURCE="e309e49110cb608e545362ba7e3a6a997f689098"
TARGET_BLOB="935182208427dfdfcf1b211f95faff01d8fe14c7"
BASE_BLOB="9cf0391f930eeee4d3a152ffae0a66e3e2669710"
APP="/www/wwwroot/gomovie.tonsamith.com"
LIVE="$APP/resources/views/partials/brand-head.blade.php"
HOST="gomovie.tonsamith.com"
PHP="/www/server/php/85/bin/php"
echo "========== GO MOVIE AUTH FLOATING LABEL RECOVERY =========="
echo "MODE=$MODE"
test "$(id -u)" -eq 0 || { echo "BLOCKED_NOT_ROOT"; exit 1; }
for bin in git gh python3 curl runuser cmp cp install; do
  command -v "$bin" >/dev/null || { echo "BLOCKED_MISSING_TOOL=$bin"; exit 1; }
done
test -d "$APP" && test ! -L "$APP" && test "$(realpath -e "$APP")" = "$APP"
test -f "$APP/artisan" && test -f "$APP/vendor/autoload.php" && test -x "$PHP"
id www >/dev/null
test -f "$LIVE" && test ! -L "$LIVE"
LIVE_BLOB="$(git hash-object "$LIVE")"
case "$LIVE_BLOB" in
  "$BASE_BLOB"|"$TARGET_BLOB") echo "SHARED_BRAND_PARTIAL=TRUSTED:$LIVE_BLOB" ;;
  *) echo "BLOCKED_UNEXPECTED_BRAND_PARTIAL=$LIVE_BLOB"; exit 1 ;;
esac
TMP="$(mktemp -d /root/Gomovie-Auth-Labels-XXXXXXXX)"
chmod 700 "$TMP"
CHANGED=0
COMPLETE=0
cleanup() {
  code="$?"
  trap - EXIT
  if [ "$CHANGED" = 1 ] && [ "$COMPLETE" = 0 ]; then
    set +e
    echo "========== RESTORE ORIGINAL BRAND PARTIAL =========="
    cp -p -- "$TMP/brand-head-before.blade.php" "$LIVE"
    (
      cd "$APP" &&
      runuser -u www -- "$PHP" artisan optimize:clear --no-interaction
    ) >/dev/null 2>&1
    echo "AUTH_FLOATING_LABEL_ROLLBACK=COMPLETE"
  fi
  if [ "$MODE" = "--check" ]; then
    rm -rf -- "$TMP"
  else
    echo "BACKUP_DIRECTORY=$TMP"
  fi
  exit "$code"
}
trap cleanup EXIT
echo "========== VERIFY PINNED GITHUB SOURCE =========="
test "$(gh api "repos/$REPO/commits/$SOURCE" --jq .sha)" = "$SOURCE"
test "$(gh api "repos/$REPO/contents/resources/views/partials/brand-head.blade.php?ref=$SOURCE" --jq .sha)" = "$TARGET_BLOB"
gh api -H 'Accept: application/vnd.github+json' \
  "repos/$REPO/git/blobs/$TARGET_BLOB" > "$TMP/target.json"
python3 - "$TMP/target.json" "$TMP/target.blade.php" "$TARGET_BLOB" <<'PY_SOURCE'
import base64,binascii,hashlib,json,pathlib,re,sys
source,dest,expected=sys.argv[1:]
obj=json.loads(pathlib.Path(source).read_text(encoding='utf8'))
if obj.get('sha')!=expected or obj.get('encoding')!='base64':
    raise SystemExit('BLOCKED_TARGET_BLOB_METADATA')
try:
    data=base64.b64decode(re.sub(r'\s+','',obj['content']),validate=True)
except (KeyError,TypeError,binascii.Error):
    raise SystemExit('BLOCKED_TARGET_BLOB_ENCODING')
got=hashlib.sha1(('blob '+str(len(data))+'\0').encode()+data).hexdigest()
if got!=expected: raise SystemExit('BLOCKED_TARGET_BLOB_HASH')
text=data.decode('utf8')
required=(
    'id="gomovie-auth-field-spacing"',
    '.gm-auth-card .gm-auth-input-icon',
    '.gm-auth-card .gm-auth-input label',
    'padding-left:3.25rem !important;',
    'padding-right:3.35rem !important;',
    'padding-top:1.65rem !important;',
    'input:-webkit-autofill',
    'input.gm-auth-input-control',
    'id="gomovie-auth-floating-labels"',
    'top:50% !important;',
    ':focus-within label',
    'gm-auth-has-value label',
    ':has(input:-webkit-autofill) label',
    'input.value.length > 0',
)
for needle in required:
    if needle not in text:
        raise SystemExit('BLOCKED_MISSING_INPUT_SPACING_RULE='+needle)
if text.count('id="gomovie-auth-field-spacing"')!=1:
    raise SystemExit('BLOCKED_DUPLICATE_INPUT_STYLE')
if text.count('id="gomovie-auth-floating-labels"')!=1:
    raise SystemExit('BLOCKED_DUPLICATE_FLOATING_LABEL_SCRIPT')
if "asset('apple-touch-icon.png')" not in text or 'gm-brand-logo--dark' not in text:
    raise SystemExit('BLOCKED_EXISTING_BRANDING_CHANGED')
pathlib.Path(dest).write_bytes(data)
print('AUTH_FLOATING_LABEL_SOURCE=VERIFIED')
PY_SOURCE

echo "========== READ-ONLY AUTH FIELD STRUCTURE CHECK =========="
for route in /login /register /forgot-password; do
  name="$(basename "$route")"
  html="$TMP/$name.html"
  status="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" -o "$html" -w '%{http_code}' "https://$HOST$route")"
  test "$status" = 200 || { echo "BLOCKED_AUTH_HTTP=$route:$status"; exit 1; }
  python3 - "$html" "$route" <<'PY_STRUCTURE'
import pathlib,re,sys
page=pathlib.Path(sys.argv[1]).read_text(encoding='utf8',errors='replace')
route=sys.argv[2]
classes=re.findall(r'\bclass\s*=\s*["\']([^"\']+)["\']',page,re.I)
has=lambda cls:sum(cls in s.split() for s in classes)
fields=has('gm-auth-input')
icons=has('gm-auth-input-icon')
controls=has('gm-auth-input-control')
if min(fields,icons,controls)<1:
    raise SystemExit('BLOCKED_AUTH_CUSTOM_FIELD_STRUCTURE='+route+
                     ':fields='+str(fields)+':icons='+str(icons)+':controls='+str(controls))
if 'favicon-32x32.png' not in page:
    raise SystemExit('BLOCKED_AUTH_FAVICON_UNEXPECTED='+route)
print('AUTH_FIELD_STRUCTURE='+route+':fields='+str(fields)+':icons='+str(icons)+':controls='+str(controls))
PY_STRUCTURE
done

if [ "$MODE" = "--check" ]; then
  echo "GOMOVIE_AUTH_FLOATING_LABEL_PREFLIGHT=PASS"
  echo "LIVE_FILES_CHANGED=NO"
  echo "DATABASE_CHANGED=NO"
  exit 0
fi

echo "========== BACKUP AND RECHECK LIVE SOURCE =========="
cp -p -- "$LIVE" "$TMP/brand-head-before.blade.php"
test "$(git hash-object "$LIVE")" = "$LIVE_BLOB"
cmp -s "$TMP/brand-head-before.blade.php" "$LIVE"
echo "AUTH_LABEL_PARTIAL_BACKUP=VERIFIED"
echo "========== INSTALL GUARDED AUTH FLOATING LABEL FIX =========="
CHANGED=1
install -o root -g www -m 0644 "$TMP/target.blade.php" "$LIVE"
cmp -s "$TMP/target.blade.php" "$LIVE"

echo "========== LARAVEL AND AUTH PAGE ACCEPTANCE =========="
(
  cd "$APP"
  runuser -u www -- "$PHP" artisan optimize:clear --no-interaction
  runuser -u www -- "$PHP" artisan view:cache --no-interaction
)
for route in /login /register /forgot-password; do
  name="$(basename "$route")"
  html="$TMP/$name-after.html"
  status="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" -o "$html" -w '%{http_code}' "https://$HOST$route")"
  test "$status" = 200 || { echo "BLOCKED_AUTH_HTTP_AFTER=$route:$status"; exit 1; }
  python3 - "$html" "$route" <<'PY_ACCEPT'
import pathlib,re,sys
page=pathlib.Path(sys.argv[1]).read_text(encoding='utf8',errors='replace')
route=sys.argv[2]
for needle in ('id="gomovie-auth-field-spacing"','padding-left:3.25rem !important;',
               'top:50% !important;','id="gomovie-auth-floating-labels"',
               'gm-auth-has-value label','input.value.length > 0',
               'gm-auth-input-icon','favicon-32x32.png'):
    if needle not in page:
        raise SystemExit('BLOCKED_AUTH_SPACING_HTML='+route+':'+needle)
print('AUTH_FLOATING_LABEL_HTML='+route+':PASS')
PY_ACCEPT
done
cmp -s "$TMP/target.blade.php" "$LIVE"
COMPLETE=1
echo "GOMOVIE_AUTH_FLOATING_LABEL_DEPLOYMENT=SUCCESS"
echo "AUTH_FORMS_AND_BUTTONS=UNCHANGED"
echo "DATABASE_CHANGED=NO"
echo "AUTH_FLOATING_LABEL_STYLE_AND_STATE=INSTALLED"
