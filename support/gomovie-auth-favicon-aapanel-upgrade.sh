#!/usr/bin/env bash
# Go Movie: apply approved favicon appearance to legacy red authentication-card marks.
# Only updates the existing shared brand-head partial; forms and layouts remain untouched.
set -Eeuo pipefail
umask 077

if [ "$#" -ne 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
  echo "Usage: bash gomovie-auth-favicon-aapanel-upgrade.sh --check|--apply" >&2
  exit 2
fi
MODE="$1"
REPO="bambodia168-sys/Gomovie"
SOURCE="502c7d4782fee628205fce1be93e94170347e23c"
SOURCE_BLOB="c2bd8d0c13c26a5b39cf3136850d93c5eeddf030"
PREVIOUS_BLOB="25052373307436b06ae795d1c85e32599d5a40a5"
BASE_BLOB="a7936ac10f8a08208beddc1f9edc9b301aa307d0"
FAVICON_BLOB="769cd796dacd4091df5dc1103e2030046ddb152f"
HOST="gomovie.tonsamith.com"
APP="/www/wwwroot/gomovie.tonsamith.com"
LIVE="$APP/resources/views/partials/brand-head.blade.php"
PHP="/www/server/php/85/bin/php"

echo "========== GO MOVIE AUTH FAVICON COMPATIBILITY =========="
echo "MODE=$MODE"
test "$(id -u)" -eq 0
for bin in gh git python3 runuser curl cmp install cp stat; do
  command -v "$bin" >/dev/null || { echo "MISSING_TOOL=$bin"; exit 1; }
done
test -d "$APP" && test ! -L "$APP" && test "$(realpath -e "$APP")" = "$APP"
test -f "$APP/artisan" && test -f "$APP/vendor/autoload.php"
test -x "$PHP"
id www >/dev/null
test -f "$LIVE" && test ! -L "$LIVE"
test -f "$APP/public/apple-touch-icon.png" && test ! -L "$APP/public/apple-touch-icon.png"
test "$(git hash-object "$APP/public/apple-touch-icon.png")" = "$FAVICON_BLOB" || {
  echo "BLOCKED_LIVE_FAVICON_ASSET_CHANGED"; exit 1;
}
LIVE_BLOB="$(git hash-object "$LIVE")"
case "$LIVE_BLOB" in
  "$BASE_BLOB"|"$PREVIOUS_BLOB"|"$SOURCE_BLOB") echo "LIVE_BRAND_PARTIAL=TRUSTED:$LIVE_BLOB" ;;
  *) echo "BLOCKED_CUSTOM_BRAND_PARTIAL=$LIVE_BLOB"; exit 1 ;;
esac

TMP="$(mktemp -d /root/Gomovie-Auth-Favicon-XXXXXXXX)"
chmod 700 "$TMP"
CHANGED=0
COMPLETE=0
on_exit() {
  status="$?"
  trap - EXIT
  if [ "$CHANGED" = 1 ] && [ "$COMPLETE" = 0 ]; then
    set +e
    echo "========== RESTORE PREVIOUS BRAND PARTIAL =========="
    cp -p "$TMP/original-brand-head.blade.php" "$LIVE"
    (cd "$APP" && runuser -u www -- "$PHP" artisan optimize:clear --no-interaction) >/dev/null 2>&1
    echo "AUTH_FAVICON_ROLLBACK=COMPLETE"
  fi
  if [ "$MODE" = "--check" ]; then
    rm -rf -- "$TMP"
  else
    echo "BACKUP_DIRECTORY=$TMP"
  fi
  exit "$status"
}
trap on_exit EXIT

echo "========== VERIFY PINNED GITHUB UPDATE =========="
test "$(gh api "repos/$REPO/commits/$SOURCE" --jq '.sha')" = "$SOURCE"
gh api -H 'Accept: application/vnd.github+json' \
  "repos/$REPO/git/blobs/$SOURCE_BLOB" > "$TMP/source.json"
python3 - "$TMP/source.json" "$TMP/source.blade.php" "$SOURCE_BLOB" <<'PY_SOURCE'
import base64,binascii,hashlib,json,pathlib,re,sys
payload,destination,expected=sys.argv[1:]
obj=json.loads(pathlib.Path(payload).read_text(encoding='utf-8'))
if obj.get('encoding')!='base64' or obj.get('sha')!=expected:
    raise SystemExit('BLOCKED_BRAND_SOURCE_METADATA')
try:
    data=base64.b64decode(re.sub(r'\s+','',obj['content']),validate=True)
except (binascii.Error,KeyError,TypeError):
    raise SystemExit('BLOCKED_BRAND_SOURCE_ENCODING')
actual=hashlib.sha1(('blob '+str(len(data))+'\0').encode()+data).hexdigest()
if actual!=expected: raise SystemExit('BLOCKED_BRAND_SOURCE_HASH')
text=data.decode('utf-8')
for required in ('id="gomovie-auth-favicon-card"', '.gm-auth-card .gm-auth-card-icon',
                 "asset('apple-touch-icon.png')",'gm-brand-logo--dark'):
    if required not in text: raise SystemExit('BLOCKED_BRAND_SOURCE_CONTENT='+required)
pathlib.Path(destination).write_bytes(data)
print('AUTH_FAVICON_SOURCE=VERIFIED')
PY_SOURCE

echo "========== READ-ONLY AUTH PAGES INSPECTION =========="
for route in /login /register /forgot-password; do
  slug="${route#/}"
  html="$TMP/${slug}.html"
  status="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" -o "$html" -w '%{http_code}' \
    "https://$HOST$route")"
  test "$status" = 200 || { echo "BLOCKED_AUTH_HTTP=$route:$status"; exit 1; }
  python3 - "$html" "$route" <<'PY_INSPECT'
import pathlib,re,sys
page=pathlib.Path(sys.argv[1]).read_text(encoding='utf-8',errors='replace')
route=sys.argv[2]
classes=re.findall(r'\bclass\s*=\s*["\']([^"\']+)["\']',page,re.I)
marks=[value for value in classes if 'gm-auth-card-mark' in value.split() or
       'gm-brand-mark' in value.split() or 'gm-auth-card-icon' in value.split()]
if len(marks)!=1:
    related=[c for c in classes if any(x in c.lower() for x in ('auth','logo','brand','mark','play','badge'))]
    print('AUTH_ICON_CANDIDATE_COUNT='+route+':'+str(len(marks)))
    for candidate in related[:12]:print('AUTH_ICON_CLASS_CANDIDATE='+route+':'+candidate[:130])
    raise SystemExit('BLOCKED_UNKNOWN_CUSTOM_AUTH_ICON='+route)
if 'favicon-32x32.png' not in page:
    raise SystemExit('BLOCKED_BROWSER_FAVICON_MISSING='+route)
print('AUTH_ICON_CLASS_VERIFIED='+route+':'+marks[0])
PY_INSPECT
done

if [ "$MODE" = "--check" ]; then
  echo "AUTH_FAVICON_PREFLIGHT=PASS"
  echo "LIVE_FILES_CHANGED=NO"
  echo "DATABASE_CHANGED=NO"
  exit 0
fi

echo "========== BACKUP AND INSTALL SHARED BRAND STYLE =========="
cp -p "$LIVE" "$TMP/original-brand-head.blade.php"
test "$(git hash-object "$LIVE")" = "$LIVE_BLOB"
echo "AUTH_BRAND_PARTIAL_BACKUP=VERIFIED"
CHANGED=1
install -o root -g www -m 0644 "$TMP/source.blade.php" "$LIVE"
cmp -s "$TMP/source.blade.php" "$LIVE"

(
  cd "$APP"
  runuser -u www -- "$PHP" artisan optimize:clear --no-interaction
  runuser -u www -- "$PHP" artisan view:cache --no-interaction
)
for route in /login /register /forgot-password; do
  slug="${route#/}"
  html="$TMP/${slug}-after.html"
  status="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" -o "$html" -w '%{http_code}' \
    "https://$HOST$route")"
  test "$status" = 200 || { echo "BLOCKED_AUTH_HTTP_AFTER=$route:$status"; exit 1; }
  python3 - "$html" "$route" <<'PY_ACCEPT'
import pathlib,re,sys
page=pathlib.Path(sys.argv[1]).read_text(encoding='utf8',errors='replace')
route=sys.argv[2]
for needle in ('favicon-32x32.png','gomovie-auth-favicon-card','apple-touch-icon.png'):
    if needle not in page:raise SystemExit('BLOCKED_AUTH_FAVICON_HTML='+route+':'+needle)
if not re.search(r'class\s*=\s*["\'][^"\']*(?:gm-auth-card-icon|gm-auth-card-mark|gm-brand-mark)[^"\']*["\']',page,re.I):
    raise SystemExit('BLOCKED_AUTH_ICON_TARGET_HTML='+route)
if '.gm-auth-card .gm-auth-card-icon' not in page:
    raise SystemExit('BLOCKED_AUTH_ICON_CSS_NOT_RENDERED='+route)
print('AUTH_FAVICON_HTML='+route+':PASS')
PY_ACCEPT
done
cmp -s "$TMP/source.blade.php" "$LIVE"
COMPLETE=1
echo "GOMOVIE_AUTH_FAVICON_DEPLOYMENT=SUCCESS"
echo "AUTH_CARD_LEGACY_RED_ICON=VISUALLY_REPLACED"
echo "AUTH_TEMPLATES_AND_FORMS=UNCHANGED"
echo "DATABASE_CHANGED=NO"