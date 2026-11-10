#!/usr/bin/env bash
# Go Movie approved light/dark logos + favicon — guarded aaPanel deployment.
# Only brand assets, three shared Blade layouts, and two partials are changed.
set -Eeuo pipefail
umask 077

if [ "$#" -ne 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
  echo "Usage: bash GoMovie-brand-aapanel-upgrade.sh --check|--apply" >&2
  exit 2
fi
MODE="$1"
REPO="bambodia168-sys/Gomovie"
SOURCE="e932bb818cc1accfd4dc86e98807aad58b24a71c"
APP="/www/wwwroot/gomovie.tonsamith.com"
HOST="gomovie.tonsamith.com"
PHP="/www/server/php/85/bin/php"

paths() {
cat <<'PATHS'
resources/views/layouts/app.blade.php
resources/views/layouts/auth.blade.php
resources/views/layouts/admin.blade.php
resources/views/partials/brand-logo.blade.php
resources/views/partials/brand-head.blade.php
public/brand/gomovie-logo-dark.webp
public/brand/gomovie-logo-light.webp
public/favicon.ico
public/favicon-32x32.png
public/favicon-16x16.png
public/apple-touch-icon.png
PATHS
}

echo "========== GO MOVIE BRAND RELEASE =========="
echo "MODE=$MODE"
test "$(id -u)" -eq 0 || { echo "BLOCKED_NOT_ROOT"; exit 1; }
test -d "$APP" && test ! -L "$APP" && test "$(realpath -e "$APP")" = "$APP"
test -f "$APP/artisan" && test -f "$APP/.env" && test -f "$APP/vendor/autoload.php"
test -x "$PHP"
id www >/dev/null
for bin in gh git runuser python3 install stat cmp curl; do
  command -v "$bin" >/dev/null || { echo "MISSING_TOOL=$bin"; exit 1; }
done
TMP="$(mktemp -d /root/Gomovie-Brand-XXXXXXXX)"
chmod 700 "$TMP"
mkdir -p "$TMP/source" "$TMP/new" "$TMP/old"
paths > "$TMP/paths"
CHANGED=0
COMPLETE=0

cleanup() {
  code="$?"
  trap - EXIT
  if [ "$CHANGED" = 1 ] && [ "$COMPLETE" = 0 ]; then
    set +e
    echo "========== RESTORE PRE-DEPLOYMENT BRAND =========="
    while IFS= read -r relative; do
      if [ -f "$TMP/old/$relative" ]; then
        cp -p "$TMP/old/$relative" "$APP/$relative"
      else
        rm -f -- "$APP/$relative"
      fi
    done < "$TMP/paths"
    ( cd "$APP" && runuser -u www -- "$PHP" artisan optimize:clear --no-interaction ) >/dev/null 2>&1
    echo "BRAND_SOURCE_ROLLBACK=COMPLETE"
    echo "BACKUP_DIRECTORY=$TMP"
  fi
  if [ "$MODE" = "--check" ]; then rm -rf -- "$TMP"; fi
  exit "$code"
}
trap cleanup EXIT

echo "========== DOWNLOAD EXACT GITHUB BRAND RELEASE =========="
test "$(gh api "repos/$REPO/commits/$SOURCE" --jq '.sha')" = "$SOURCE"
gh api -H 'Accept: application/vnd.github+json' \
  "repos/$REPO/git/trees/$SOURCE?recursive=1" > "$TMP/tree.json"

# GitHub's raw contents API may fail to transform binary icon/WebP assets.
# Fetch JSON blob objects instead, then decode and validate exact Git bytes.
# This is entirely within the private temporary directory for both modes.
while IFS= read -r relative; do
  dest="$TMP/source/$relative"
  mkdir -p "$(dirname "$dest")"
  expected="$(python3 - "$TMP/tree.json" "$relative" <<'PY_LOOKUP'
import json,sys,re
tree=json.load(open(sys.argv[1],encoding='utf8'))
if tree.get('truncated'):raise SystemExit('BLOCKED_TRUNCATED_GITHUB_TREE')
matches=[x for x in tree.get('tree',[]) if x.get('path')==sys.argv[2] and x.get('type')=='blob']
if len(matches)!=1 or not re.fullmatch(r'[0-9a-f]{40}',matches[0].get('sha','')):
    raise SystemExit('BLOCKED_RELEASE_BLOB_PATH='+sys.argv[2])
print(matches[0]['sha'])
PY_LOOKUP
  )"
  echo "FETCH_VERIFIED_GITHUB_BLOB=$relative:$expected"
  gh api -H 'Accept: application/vnd.github+json' \
    "repos/$REPO/git/blobs/$expected" > "$TMP/blob-response.json" || {
      echo "BLOCKED_GITHUB_BLOB_DOWNLOAD=$relative"; exit 1;
    }
  python3 - "$TMP/blob-response.json" "$dest" "$expected" "$relative" <<'PY_DECODE'
import sys,pathlib,json,base64,hashlib,re,binascii
response,dest,expected,relative=sys.argv[1:]
try:
    obj=json.loads(pathlib.Path(response).read_text(encoding='utf8'))
except (UnicodeDecodeError,json.JSONDecodeError) as exc:
    raise SystemExit('BLOCKED_GITHUB_BLOB_JSON='+relative) from exc
if obj.get('encoding')!='base64' or obj.get('sha')!=expected:
    raise SystemExit('BLOCKED_GITHUB_BLOB_METADATA='+relative)
content=obj.get('content')
if not isinstance(content,str):
    raise SystemExit('BLOCKED_GITHUB_BLOB_CONTENT='+relative)
try:
    data=base64.b64decode(re.sub(r'\s+','',content),validate=True)
except (binascii.Error,ValueError) as exc:
    raise SystemExit('BLOCKED_GITHUB_BLOB_BASE64='+relative) from exc
got=hashlib.sha1(('blob '+str(len(data))+'\0').encode()+data).hexdigest()
if got!=expected or not data:
    raise SystemExit('BLOCKED_GITHUB_BLOB_HASH='+relative)
pathlib.Path(dest).write_bytes(data)
print('VERIFIED_DOWNLOADED_GIT_BLOB='+relative)
PY_DECODE
done < "$TMP/paths"
rm -f -- "$TMP/blob-response.json"
python3 - "$TMP" <<'PY_VERIFY'
import pathlib,sys,json,hashlib
root=pathlib.Path(sys.argv[1]); tree=json.loads((root/'tree.json').read_text())
if tree.get('truncated'): raise SystemExit('BLOCKED_TRUNCATED_GITHUB_TREE')
blobs={t['path']:t['sha'] for t in tree['tree'] if t.get('type')=='blob'}
for relative in (root/'paths').read_text().splitlines():
    data=(root/'source'/relative).read_bytes()
    got=hashlib.sha1(('blob '+str(len(data))+'\0').encode()+data).hexdigest()
    if got != blobs.get(relative): raise SystemExit('BLOCKED_RELEASE_HASH='+relative)
    if relative.endswith('.webp') and not (data.startswith(b'RIFF') and data[8:12]==b'WEBP'):
        raise SystemExit('BLOCKED_BRAND_WEBP='+relative)
    if relative.endswith('.png') and not data.startswith(b'\x89PNG\r\n\x1a\n'):
        raise SystemExit('BLOCKED_BRAND_PNG='+relative)
    if relative.endswith('.ico') and not data.startswith(b'\x00\x00\x01\x00'):
        raise SystemExit('BLOCKED_BRAND_ICO='+relative)
    print('VERIFIED_RELEASE_FILE='+relative)
print('GOMOVIE_BRAND_SOURCE=VERIFIED')
PY_VERIFY

echo "========== PREPARE CUSTOM-LAYOUT-PRESERVING MERGE =========="
python3 - "$APP" "$TMP" <<'PY_PREPARE'
import sys,pathlib,re
app=pathlib.Path(sys.argv[1]);tmp=pathlib.Path(sys.argv[2])
layouts={
'resources/views/layouts/app.blade.php':2,
'resources/views/layouts/auth.blade.php':1,
'resources/views/layouts/admin.blade.php':1,
}
old_brand=re.compile(r'<span class="gm-brand-mark" aria-hidden="true"><span>▶</span></span>\s*<span class="gm-brand-copy"><strong>GO<span>MOVIE</span></strong><small>MOVIES · SERIES · MORE</small></span>')
for relative in (tmp/'paths').read_text().splitlines():
    current=app/relative
    if current.is_symlink() or (current.exists() and not current.is_file()):
        raise SystemExit('BLOCKED_EXISTING_BRAND_FILE_TYPE='+relative)
    parent=current.parent
    while parent != app:
        if parent.is_symlink(): raise SystemExit('BLOCKED_PARENT_SYMLINK='+str(parent))
        if parent.exists() and not parent.is_dir(): raise SystemExit('BLOCKED_PARENT_NOT_DIR='+str(parent))
        parent=parent.parent
    prepared=tmp/'new'/relative
    prepared.parent.mkdir(parents=True,exist_ok=True)
    if relative not in layouts:
        prepared.write_bytes((tmp/'source'/relative).read_bytes())
        print(('BRAND_ASSET_REPLACES_LEGACY=' if current.exists() else 'NEW_BRAND_FILE=')+relative)
        continue
    if not current.is_file(): raise SystemExit('BLOCKED_MISSING_LIVE_LAYOUT='+relative)
    data=current.read_bytes()
    if len(data)>1048576 or b'\0' in data:raise SystemExit('BLOCKED_LIVE_LAYOUT_NOT_TEXT='+relative)
    try: html=data.decode('utf8')
    except UnicodeDecodeError:raise SystemExit('BLOCKED_LIVE_LAYOUT_NOT_UTF8='+relative)
    original=html
    if html.count("@include('partials.brand-head')") == 0:
        if html.count("@include('partials.feedback')") != 1:
            raise SystemExit('BLOCKED_BRAND_HEAD_INSERTION='+relative)
        html=html.replace("@include('partials.feedback')", "@include('partials.feedback')\n@include('partials.brand-head')", 1)
    elif html.count("@include('partials.brand-head')") != 1:
        raise SystemExit('BLOCKED_DUPLICATE_BRAND_HEAD='+relative)
    if relative.endswith('/admin.blade.php'):
        old='<strong>GO<span>MOVIE</span></strong>'
        if old in html:
            if html.count(old)!=1 or 'class="gm-studio-brand"' not in html:
                raise SystemExit('BLOCKED_ADMIN_BRAND_MARKUP='+relative)
            html=html.replace(old,"@include('partials.brand-logo')",1)
    else:
        html=old_brand.sub("@include('partials.brand-logo')",html)
    if relative.endswith('/auth.blade.php') and "@include('partials.brand-logo')" not in html:
        # Custom authentication layouts frequently remove the old header
        # entirely. Do not reintroduce it or overwrite a bespoke home link.
        # When there is one recognizably old brand anchor, change only the
        # children while preserving the existing anchor and its attributes.
        anchor=re.compile(
            r'(<a\b(?=[^>]*\bclass\s*=\s*["\'][^"\']*\bgm-brand\b)[^>]*>)(.*?)(</a\s*>)',
            re.I|re.S
        )
        matches=list(anchor.finditer(html))
        if len(matches)>1:
            raise SystemExit('BLOCKED_AUTH_MULTIPLE_BRAND_ANCHORS='+relative)
        if matches:
            match=matches[0]
            contents=match.group(2)
            recognized=('gm-brand-mark' in contents or
                        'gm-brand-copy' in contents or
                        bool(re.search(r'GO\s*(?:<span[^>]*>)?\s*MOVIE',contents,re.I)))
            if recognized:
                if ('<a' in contents.lower() or '@' in contents or
                    re.search(r'<(?:form|button|script|input)\b',contents,re.I)):
                    raise SystemExit('BLOCKED_AUTH_BRAND_COMPLEX_CONTENT='+relative)
                html=html[:match.start(2)]+"@include('partials.brand-logo')"+html[match.end(2):]
                print('AUTH_CUSTOM_BRAND_ANCHOR_REPLACED='+relative)
        if "@include('partials.brand-logo')" not in html:
            # No recognizable old logo exists. Preserve its intentional
            # removal while still installing the favicon in <head>.
            if re.search(r'gm-brand-(?:mark|copy)',html) or re.search(
                r'<strong>\s*GO\s*<span>\s*MOVIE\s*</span>',html,re.I):
                raise SystemExit('BLOCKED_AUTH_UNMATCHED_LEGACY_BRAND='+relative)
            print('AUTH_CUSTOM_LOGO_INTENTIONALLY_ABSENT='+relative)
    required=layouts[relative]
    if relative.endswith('/auth.blade.php') and "@include('partials.brand-logo')" not in html:
        required=0
    if html.count("@include('partials.brand-logo')") != required:
        raise SystemExit('BLOCKED_BRAND_POSITION_COUNT='+relative)
    if 'gm-brand-mark' in html or 'gm-brand-copy' in html:
        raise SystemExit('BLOCKED_LEGACY_BRAND_REMAINING='+relative)
    if html.count('</head>')!=1 or '@vite(' not in html:
        raise SystemExit('BLOCKED_LAYOUT_ASSET_INTEGRITY='+relative)
    if '@include(\'partials.brand-head\')' not in html:
        raise SystemExit('BLOCKED_BRAND_HEAD_MISSING='+relative)
    prepared.write_text(html,encoding='utf8')
    print(('LAYOUT_ALREADY_BRANDED=' if html==original else 'LAYOUT_CUSTOM_CONTENT_PRESERVED=')+relative)
print('GOMOVIE_BRAND_PREFLIGHT=PASS')
PY_PREPARE

if [ "$MODE" = "--check" ]; then
  echo "LIVE_FILES_CHANGED=NO"
  echo "DATABASE_CHANGED=NO"
  exit 0
fi

echo "========== BACKUP EXISTING BRAND FILES =========="
while IFS= read -r relative; do
  if [ -f "$APP/$relative" ]; then
    mkdir -p "$TMP/old/$(dirname "$relative")"
    cp -p "$APP/$relative" "$TMP/old/$relative"
  fi
done < "$TMP/paths"
echo "BRAND_FILE_BACKUP=VERIFIED"

echo "========== RECHECK LIVE FILES UNCHANGED =========="
while IFS= read -r relative; do
  if [ -f "$TMP/old/$relative" ]; then
    test -f "$APP/$relative" && test ! -L "$APP/$relative"
    cmp -s "$APP/$relative" "$TMP/old/$relative"
  else
    test ! -e "$APP/$relative" && test ! -L "$APP/$relative"
  fi
done < "$TMP/paths"

CHANGED=1
echo "========== INSTALL BRAND IMAGES AND VIEWS =========="
prepare_parent() {
  local relative="$1" parent="${1%/*}" segment walk="$APP"
  local -a components
  IFS='/' read -r -a components <<< "$parent"
  for segment in "${components[@]}"; do
    case "$segment" in ''|'.'|'..') echo "BLOCKED_PARENT_COMPONENT=$relative"; return 1;; esac
    walk="$walk/$segment"
    if [ -L "$walk" ]; then
      echo "BLOCKED_INSTALL_PARENT_SYMLINK=$walk"; return 1
    elif [ -e "$walk" ]; then
      test -d "$walk" || { echo "BLOCKED_INSTALL_PARENT_NOT_DIR=$walk"; return 1; }
    else
      install -d -o root -g www -m 0755 "$walk"
    fi
  done
}
while IFS= read -r relative; do
  dst="$APP/$relative"
  prepare_parent "$relative"
  install -o root -g www -m 0644 "$TMP/new/$relative" "$dst"
  echo "BRAND_INSTALLED=$relative"
done < "$TMP/paths"

echo "========== LARAVEL AND HTTP ACCEPTANCE =========="
(
 cd "$APP"
 runuser -u www -- "$PHP" artisan optimize:clear --no-interaction
 runuser -u www -- "$PHP" artisan view:cache --no-interaction
)
for route in / /login /register /favicon.ico /favicon-32x32.png /brand/gomovie-logo-dark.webp /brand/gomovie-logo-light.webp; do
  status="$(curl --noproxy '*' -fsS --connect-timeout 5 --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' "https://$HOST$route")"
  echo "BRAND_HTTP=$route:$status"
  test "$status" = 200 || { echo "BLOCKED_BRAND_HTTP=$route"; exit 1; }
done
for route in / /login; do
  html="$(curl --noproxy '*' -fsS --connect-timeout 5 --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" "https://$HOST$route")"
  [[ "$html" == *gomovie-logo-dark.webp* ]] || { echo "BLOCKED_BRAND_HTML=$route"; exit 1; }
  [[ "$html" == *gomovie-logo-light.webp* ]] || { echo "BLOCKED_BRAND_LIGHT_HTML=$route"; exit 1; }
done
while IFS= read -r relative; do
  cmp -s "$APP/$relative" "$TMP/new/$relative" || { echo "BLOCKED_BRAND_INSTALLED_HASH=$relative"; exit 1; }
done < "$TMP/paths"

COMPLETE=1
echo "GOMOVIE_BRAND_DEPLOYMENT=SUCCESS"
echo "GOMOVIE_FAVICON=INSTALLED"
echo "GOMOVIE_LIGHT_LOGO=INSTALLED"
echo "GOMOVIE_DARK_LOGO=INSTALLED"
echo "DATABASE_CHANGED=NO"
echo "UPLOADS_AND_VIDEOS=UNCHANGED"
echo "BACKUP_DIRECTORY=$TMP"