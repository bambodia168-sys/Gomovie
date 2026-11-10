#!/usr/bin/env bash
# Go Movie: replace the legacy red auth-card play symbol with the approved favicon.
# Preserves the production auth layout, forms, and all account functionality.
set -Eeuo pipefail
umask 077

if [ "$#" -ne 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
  echo "Usage: bash gomovie-auth-favicon-aapanel-upgrade.sh --check|--apply" >&2
  exit 2
fi

MODE="$1"
REPO="bambodia168-sys/Gomovie"
SOURCE="0448777dd589246bab8869b1998be5434d5fe4d3"
TARGET_BLOB="c6f5c2cac43e27460c77b5fab133c3c555034301"
FAVICON_BLOB="769cd796dacd4091df5dc1103e2030046ddb152f"
HOST="gomovie.tonsamith.com"
APP="/www/wwwroot/gomovie.tonsamith.com"
LIVE="$APP/resources/views/layouts/auth.blade.php"
PHP="/www/server/php/85/bin/php"

echo "========== GO MOVIE AUTH FAVICON =========="
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
  echo "BLOCKED_LIVE_FAVICON_NOT_APPROVED"; exit 1;
}

TMP="$(mktemp -d /root/Gomovie-Auth-Favicon-XXXXXXXX)"
chmod 700 "$TMP"
CHANGED=0
COMPLETE=0
on_exit() {
  status="$?"
  trap - EXIT
  if [ "$CHANGED" = 1 ] && [ "$COMPLETE" = 0 ]; then
    set +e
    echo "========== RESTORE AUTH LAYOUT =========="
    cp -p "$TMP/original-auth.blade.php" "$LIVE"
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

echo "========== VERIFY PINNED GITHUB RELEASE =========="
test "$(gh api "repos/$REPO/commits/$SOURCE" --jq '.sha')" = "$SOURCE"
gh api -H 'Accept: application/vnd.github+json' \
  "repos/$REPO/git/blobs/$TARGET_BLOB" > "$TMP/target-blob.json"
python3 - "$TMP/target-blob.json" "$TMP/target-auth.blade.php" "$TARGET_BLOB" <<'PY_SOURCE'
import base64,binascii,hashlib,json,pathlib,re,sys
payload,destination,expected=sys.argv[1:]
obj=json.loads(pathlib.Path(payload).read_text(encoding='utf-8'))
if obj.get('sha')!=expected or obj.get('encoding')!='base64':
    raise SystemExit('BLOCKED_SOURCE_BLOB_METADATA')
try:
    data=base64.b64decode(re.sub(r'\s+','',obj['content']),validate=True)
except (binascii.Error,KeyError,TypeError):
    raise SystemExit('BLOCKED_SOURCE_BLOB_ENCODING')
actual=hashlib.sha1(('blob '+str(len(data))+'\0').encode()+data).hexdigest()
if actual!=expected:
    raise SystemExit('BLOCKED_SOURCE_BLOB_HASH')
pathlib.Path(destination).write_bytes(data)
print('AUTH_FAVICON_GITHUB_SOURCE=VERIFIED')
PY_SOURCE

echo "========== PREPARE SAFE CUSTOM AUTH LAYOUT UPDATE =========="
python3 - "$LIVE" "$TMP/target-auth.blade.php" "$TMP/new-auth.blade.php" <<'PY_PREPARE'
import html,pathlib,re,sys
live_path,target_path,result_path=map(pathlib.Path,sys.argv[1:])
original=live_path.read_text(encoding='utf-8')
target=target_path.read_text(encoding='utf-8')
if not original or len(original)>1048576 or '\0' in original:
    raise SystemExit('BLOCKED_AUTH_LAYOUT_TEXT')
if original.count("@include('partials.brand-head')")!=1:
    raise SystemExit('BLOCKED_AUTH_FAVICON_HEAD_MISSING')
if original.count('@yield(\'content\')')!=1 or '@vite(' not in original:
    raise SystemExit('BLOCKED_AUTH_CONTENT_LAYOUT')
if original.count('</head>')!=1:
    raise SystemExit('BLOCKED_AUTH_HEAD_STRUCTURE')

mark=re.compile(
    r'<div\b[^>]*\bclass\s*=\s*["\'][^"\']*\bgm-auth-card-mark\b[^"\']*["\'][^>]*>.*?</div\s*>',
    re.I|re.S
)
css=re.compile(r'<style\s+id="gomovie-auth-brand-mark">.*?</style>',re.I|re.S)
new_marks=mark.findall(target)
new_styles=css.findall(target)
old_marks=mark.findall(original)
if len(new_marks)!=1 or len(new_styles)!=1 or len(old_marks)!=1:
    raise SystemExit('BLOCKED_AUTH_CARD_MARK_COUNT')
desired_mark=new_marks[0]
desired_style=new_styles[0]
if 'gm-auth-card-mark--favicon' not in desired_mark or 'apple-touch-icon.png' not in desired_mark:
    raise SystemExit('BLOCKED_TARGET_FAVICON_MARKUP')
current_mark=old_marks[0]
if 'gm-auth-card-mark--favicon' in current_mark:
    if current_mark!=desired_mark:
        raise SystemExit('BLOCKED_UNEXPECTED_CUSTOM_FAVICON_MARKUP')
    updated=original
    print('AUTH_CARD_ICON=ALREADY_BRANDED')
else:
    inside=re.sub(r'^<div\b[^>]*>|</div\s*>$','',current_mark,flags=re.I|re.S)
    inside=re.sub(r'</?span\b[^>]*>','',inside,flags=re.I|re.S)
    plain=html.unescape(inside).strip()
    if plain not in ('▶','►','▷'):
        raise SystemExit('BLOCKED_UNRECOGNIZED_LEGACY_CARD_ICON')
    updated=original.replace(current_mark,desired_mark,1)
    print('AUTH_CARD_ICON=REPLACE_LEGACY_PLAY')
present_styles=css.findall(updated)
if len(present_styles)>1:
    raise SystemExit('BLOCKED_DUPLICATE_FAVICON_STYLE')
if present_styles:
    if present_styles[0]!=desired_style:
        raise SystemExit('BLOCKED_UNEXPECTED_FAVICON_STYLE')
else:
    if 'gomovie-auth-brand-mark' in updated:
        raise SystemExit('BLOCKED_CONFLICTING_FAVICON_STYLE')
    updated=updated.replace('</head>',desired_style+'\n</head>',1)
    print('AUTH_CARD_STYLE=PREPARED')
if updated.count('gm-auth-card-mark--favicon')<2:
    raise SystemExit('BLOCKED_NEW_AUTH_CARD_STYLE')
if updated.count("@include('partials.brand-head')")!=1 or updated.count('@yield(\'content\')')!=1:
    raise SystemExit('BLOCKED_AUTH_LAYOUT_STRUCTURE')
if updated.count('@csrf')!=original.count('@csrf') or updated.count('<form')!=original.count('<form'):
    raise SystemExit('BLOCKED_AUTH_FORM_MUTATION')
if updated==original:
    print('AUTH_CARD_BRANDING=ALREADY_INSTALLED')
result_path.write_text(updated,encoding='utf-8')
print('AUTH_FAVICON_PREFLIGHT=PASS')
PY_PREPARE

if [ "$MODE" = "--check" ]; then
  echo "LIVE_FILES_CHANGED=NO"
  echo "DATABASE_CHANGED=NO"
  exit 0
fi

echo "========== BACKUP AND RECHECK PRODUCTION =========="
cp -p "$LIVE" "$TMP/original-auth.blade.php"
cmp -s "$TMP/original-auth.blade.php" "$LIVE"
echo "AUTH_LAYOUT_BACKUP=VERIFIED"

echo "========== INSTALL AUTH CARD FAVICON =========="
CHANGED=1
install -o root -g www -m 0644 "$TMP/new-auth.blade.php" "$LIVE"
cmp -s "$TMP/new-auth.blade.php" "$LIVE"

echo "========== LARAVEL COMPILE AND HTTP ACCEPTANCE =========="
(
  cd "$APP"
  runuser -u www -- "$PHP" artisan optimize:clear --no-interaction
  runuser -u www -- "$PHP" artisan view:cache --no-interaction
)
for route in /login /register /forgot-password; do
  output="$TMP/route.html"
  status="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" -o "$output" -w '%{http_code}' \
    "https://$HOST$route")"
  test "$status" = 200 || { echo "BLOCKED_AUTH_HTTP=$route:$status"; exit 1; }
  python3 - "$output" "$route" <<'PY_HTML'
import pathlib,re,sys
page=pathlib.Path(sys.argv[1]).read_text(encoding='utf-8',errors='replace')
route=sys.argv[2]
if 'favicon-32x32.png' not in page:
    raise SystemExit('BLOCKED_AUTH_FAVICON_HEAD='+route)
pattern=r'<div\b[^>]*class=["\'][^"\']*gm-auth-card-mark--favicon[^"\']*["\'][^>]*>\s*<img\b[^>]*apple-touch-icon\.png'
if not re.search(pattern,page,re.S|re.I):
    raise SystemExit('BLOCKED_AUTH_FAVICON_CARD='+route)
print('AUTH_FAVICON_HTML='+route+':PASS')
PY_HTML
done
cmp -s "$TMP/new-auth.blade.php" "$LIVE"
COMPLETE=1
echo "GOMOVIE_AUTH_FAVICON_DEPLOYMENT=SUCCESS"
echo "AUTH_CARD_LEGACY_RED_ICON=REPLACED"
echo "AUTH_FAVICON_ALL_PAGES=SHARED_LAYOUT"
echo "DATABASE_CHANGED=NO"
echo "AUTH_FORMS=UNCHANGED"
