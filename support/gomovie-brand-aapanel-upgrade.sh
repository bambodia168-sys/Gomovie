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
gh api "repos/$REPO/git/trees/$SOURCE?recursive=1" > "$TMP/tree.json"
while IFS= read -r relative; do
  dest="$TMP/source/$relative"
  mkdir -p "$(dirname "$dest")"
  gh api -H 'Accept: application/vnd.github.raw+json' \
    "repos/$REPO/contents/$relative?ref=$SOURCE" > "$dest"
  test -s "$dest" || { echo "BLOCKED_EMPTY_RELEASE_ASSET=$relative"; exit 1; }
done < "$TMP/paths"
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
    if html.count("@include('partials.brand-logo')") != layouts[relative]:
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