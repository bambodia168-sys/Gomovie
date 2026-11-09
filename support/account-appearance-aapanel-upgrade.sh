#!/usr/bin/env bash
# Gomovie Account + Appearance deployment for aaPanel.
# Pins source and CI artifact; blocks unknown modifications.
set -Eeuo pipefail
umask 077

if [ "$#" -ne 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
  echo "Usage: bash account-appearance-aapanel-upgrade.sh --check|--apply" >&2
  exit 2
fi
MODE="$1"
REPO="bambodia168-sys/Gomovie"
SOURCE="1d5a613294f683bcdd5300e071cb4c72ac61e859"
BASE="11eddadf605588ac8b7d30f7edd4c379c3868fba"
MID="b895bf75ba1ac32bea13f83dce26909e81e3d509"
RUN="42658912663"
APP="/www/wwwroot/gomovie.tonsamith.com"
PHP="/www/server/php/85/bin/php"
HOST="gomovie.tonsamith.com"
DUMP="/www/server/mysql/bin/mysqldump"
AUTH="resources/views/layouts/auth.blade.php"

paths() {
cat <<'PATHS'
app/Http/Controllers/Account/LibraryController.php
app/Http/Controllers/Account/SettingsController.php
app/Http/Controllers/Admin/AppearanceController.php
app/Http/Controllers/Admin/DashboardController.php
app/Http/Controllers/Admin/EpisodeStudioController.php
app/Http/Controllers/Admin/MediaController.php
app/Http/Controllers/Admin/OperationsController.php
app/Http/Controllers/Admin/UserController.php
app/Support/StudioAppearance.php
database/migrations/2026_11_09_000400_create_studio_appearance_tables.php
database/migrations/2026_11_09_000500_create_studio_content_slots_table.php
database/migrations/2026_11_09_000600_add_studio_typography_variants.php
resources/css/app.css
resources/css/studio.css
resources/js/app.js
resources/js/studio.js
resources/views/account/library.blade.php
resources/views/account/overview.blade.php
resources/views/account/settings.blade.php
resources/views/admin/appearance/index.blade.php
resources/views/admin/catalog/form.blade.php
resources/views/admin/catalog/index.blade.php
resources/views/admin/dashboard.blade.php
resources/views/admin/media.blade.php
resources/views/admin/operations.blade.php
resources/views/admin/player-settings.blade.php
resources/views/admin/series/episodes.blade.php
resources/views/admin/users.blade.php
resources/views/auth/forgot.blade.php
resources/views/auth/login.blade.php
resources/views/auth/register.blade.php
resources/views/auth/reset.blade.php
resources/views/auth/verify-email.blade.php
resources/views/frontend/about.blade.php
resources/views/frontend/browse.blade.php
resources/views/frontend/custom-page.blade.php
resources/views/frontend/detail.blade.php
resources/views/frontend/home.blade.php
resources/views/layouts/account.blade.php
resources/views/layouts/admin.blade.php
resources/views/layouts/app.blade.php
resources/views/layouts/auth.blade.php
resources/views/partials/feedback.blade.php
resources/views/partials/studio-icon.blade.php
resources/views/partials/studio-pager.blade.php
routes/web.php
PATHS
}

echo "========== GOMOVIE AA PANEL ACCOUNT + APPEARANCE =========="
echo "MODE=$MODE"
test "$(id -u)" = 0
test -d "$APP" && test ! -L "$APP" && test "$(realpath -e "$APP")" = "$APP"
test -x "$PHP" && test -x "$DUMP"
id www >/dev/null
for f in artisan .env vendor/autoload.php public/build/manifest.json; do
  test -f "$APP/$f" && test ! -L "$APP/$f" || { echo "BLOCKED_LIVE_PATH=$f"; exit 1; }
done
test ! -e "$APP/public/hot"
for bin in gh git curl runuser python3 unzip sha256sum gzip find cmp; do
  command -v "$bin" >/dev/null || { echo "MISSING_TOOL=$bin"; exit 1; }
done
TMP="$(mktemp -d /root/Gomovie-Account-Appearance-XXXXXXXX)"
chmod 700 "$TMP"
mkdir -p "$TMP/old" "$TMP/new" "$TMP/package" "$TMP/extract"
paths > "$TMP/paths"
CHANGED=0
COMPLETE=0

on_exit() {
  code="$?"
  trap - EXIT
  if [ "$CHANGED" -eq 1 ] && [ "$COMPLETE" -eq 0 ]; then
    set +e
    echo "========== RESTORING ORIGINAL SOURCES =========="
    while IFS= read -r f; do
      if [ -f "$TMP/old/$f" ]; then cp -p "$TMP/old/$f" "$APP/$f"; else rm -f -- "$APP/$f"; fi
    done < "$TMP/paths"
    cp -p "$TMP/old/manifest.json" "$APP/public/build/manifest.json"
    if [ -f "$TMP/old/fonts-manifest.json" ]; then
      cp -p "$TMP/old/fonts-manifest.json" "$APP/public/build/fonts-manifest.json"
    else
      rm -f -- "$APP/public/build/fonts-manifest.json"
    fi
    (cd "$APP" && runuser -u www -- "$PHP" artisan optimize:clear --no-interaction) >/dev/null 2>&1
    echo "SOURCE_ROLLBACK=COMPLETE"
    echo "DATABASE_NOTE=Additive schema migrations may remain; database backup is retained."
    echo "BACKUP_DIRECTORY=$TMP"
  fi
  # Ensure DB credentials are never retained even on backup failures.
  rm -f -- "$TMP/mysql.cnf" "$TMP/db-name"
  if [ "$MODE" = "--check" ]; then rm -rf -- "$TMP"; fi
  exit "$code"
}
trap on_exit EXIT

echo "========== VERIFY EXACT RELEASE AND CI =========="
test "$(gh api "repos/$REPO/commits/$SOURCE" --jq '.sha')" = "$SOURCE"
test "$(gh api "repos/$REPO/actions/runs/$RUN" --jq '.head_sha')" = "$SOURCE"
test "$(gh api "repos/$REPO/actions/runs/$RUN" --jq '.conclusion')" = success
for revision in "$BASE" "$MID" "$SOURCE"; do
  gh api "repos/$REPO/git/trees/$revision?recursive=1" > "$TMP/tree-$revision.json"
done

echo "========== READ-ONLY LIVE FILE COMPATIBILITY =========="
python3 - "$APP" "$TMP" "$BASE" "$MID" "$SOURCE" "$AUTH" <<'PY'
import pathlib,sys,json,hashlib,re
app,tmp=map(pathlib.Path,sys.argv[1:3])
base,mid,target,auth=sys.argv[3:]
def tree(sha):
    d=json.loads((tmp/('tree-'+sha+'.json')).read_text())
    if d.get('truncated'): raise SystemExit('BLOCKED_GITHUB_TREE_TRUNCATED')
    return {f['path']:f['sha'] for f in d['tree'] if f.get('type')=='blob'}
trees=[tree(k) for k in (base,mid,target)]
for rel in (tmp/'paths').read_text().splitlines():
    if rel not in trees[-1]:raise SystemExit('BLOCKED_TARGET_SOURCE='+rel)
    f=app/rel
    exists=f.exists() or f.is_symlink()
    if exists and (f.is_symlink() or not f.is_file()):
        raise SystemExit('BLOCKED_SYMLINK_OR_WRONG_TYPE='+rel)
    if rel==auth:
        if not exists:raise SystemExit('BLOCKED_AUTH_MISSING')
        b=f.read_bytes()
        if len(b)>1048576 or b'\x00' in b:raise SystemExit('BLOCKED_AUTH_NOT_TEXT')
        try:t=b.decode('utf8')
        except UnicodeDecodeError:raise SystemExit('BLOCKED_AUTH_NOT_UTF8')
        if len(re.findall(r'<head(?:\s[^>]*)?>',t,re.I))!=1 or len(re.findall(r'</head\s*>',t,re.I))!=1:
            raise SystemExit('BLOCKED_AUTH_HEAD')
        if 'partials.feedback' not in t or '@vite(' not in t:
            raise SystemExit('BLOCKED_AUTH_MISSING_REQUIRED_ASSETS')
        x=''
        if '<meta name="description"' not in t:
            x += '<meta name="description" content="@yield(' + "'description','Sign in or manage your Gomovie account.'" + ')">\n'
        if 'StudioAppearance::css()' not in t:
            x += '<style>{!! \\App\\Support\\StudioAppearance::css() !!}</style>\n'
        t=re.sub(r'</head\s*>',lambda m:x+m.group(0),t,count=1,flags=re.I)
        dest=tmp/'new'/rel
        dest.parent.mkdir(parents=True,exist_ok=True)
        dest.write_text(t,encoding='utf8')
        print('AUTH_LAYOUT=SAFE_CUSTOM_PRESERVATION')
        continue
    if not exists:
        if rel in trees[0]:raise SystemExit('BLOCKED_EXISTING_FILE_MISSING='+rel)
        print('NEW_FILE='+rel)
        continue
    raw=f.read_bytes()
    blob=hashlib.sha1(('blob '+str(len(raw))+'\x00').encode()+raw).hexdigest()
    allowed={d.get(rel) for d in trees}
    if blob not in allowed:
        if rel.startswith('resources/views/auth/') and rel.endswith('.blade.php'):
            if len(raw)>1048576 or b'\x00' in raw:
                raise SystemExit('BLOCKED_CUSTOM_AUTH_NOT_TEXT='+rel)
            try: raw.decode('utf-8')
            except UnicodeDecodeError: raise SystemExit('BLOCKED_CUSTOM_AUTH_NOT_UTF8='+rel)
            with (tmp/'custom-auth-merges').open('a') as f_list:
                f_list.write(rel+'\n')
            print('CUSTOM_AUTH_REQUIRES_SAFE_MERGE='+rel)
            continue
        print('BLOCKED_CUSTOM_FILE='+rel)
        print('CURRENT_BLOB='+blob)
        raise SystemExit(1)
    print('KNOWN_SOURCE='+rel)
print('GOMOVIE_AAPANEL_PREFLIGHT=PASS')
PY

# A customized authentication page must be merged with the pinned baseline
# and target without discarding local changes. The merge is prepared entirely
# in the private temporary directory, including during --check.
if [ -s "$TMP/custom-auth-merges" ]; then
  echo "========== VERIFY AND PREPARE CUSTOM AUTHENTICATION MERGES =========="
  while IFS= read -r f; do
    for rev in "$BASE" "$SOURCE"; do
      if [ "$rev" = "$BASE" ]; then dest="$TMP/auth-base/$f"; else dest="$TMP/auth-target/$f"; fi
      mkdir -p "$(dirname "$dest")"
      gh api -H "Accept: application/vnd.github.raw+json" \
        "repos/$REPO/contents/$f?ref=$rev" > "$dest"
      test -s "$dest"
    done
    python3 - "$TMP" "$BASE" "$SOURCE" "$f" <<'PY_AUTH_HASH'
import hashlib,json,pathlib,sys
tmp=pathlib.Path(sys.argv[1])
base,target,rel=sys.argv[2:]
for rev,folder in ((base,'auth-base'),(target,'auth-target')):
    data=(tmp/folder/rel).read_bytes()
    got=hashlib.sha1(('blob '+str(len(data))+'\0').encode()+data).hexdigest()
    tree=json.loads((tmp/('tree-'+rev+'.json')).read_text())
    expected=next((x['sha'] for x in tree['tree'] if x.get('path')==rel and x.get('type')=='blob'),None)
    if got != expected: raise SystemExit('BLOCKED_AUTH_SOURCE_HASH='+rel)
PY_AUTH_HASH
    mkdir -p "$TMP/new/$(dirname "$f")"
    if ! git merge-file -p -- "$APP/$f" "$TMP/auth-base/$f" "$TMP/auth-target/$f" > "$TMP/new/$f"; then
      echo "BLOCKED_AUTH_MERGE_CONFLICT=$f"
      echo "AUTH_MERGE_ACTION=Inspect live changes; no production files modified"
      exit 1
    fi
    python3 - "$TMP" "$f" <<'PY_AUTH_VERIFY'
import pathlib,re,sys
tmp=pathlib.Path(sys.argv[1]); rel=sys.argv[2]
merged=(tmp/'new'/rel).read_text(encoding='utf8')
target=(tmp/'auth-target'/rel).read_text(encoding='utf8')
if not merged or len(merged)>1048576:
    raise SystemExit('BLOCKED_AUTH_MERGE_SIZE='+rel)
if re.search(r'^(<<<<<<< |=======|>>>>>>> )',merged,re.M):
    raise SystemExit('BLOCKED_AUTH_MERGE_MARKERS='+rel)
if not re.search(r"@extends\s*\(\s*['\"]layouts\.auth['\"]",merged):
    raise SystemExit('BLOCKED_AUTH_MERGE_LAYOUT='+rel)
if not re.search(r"@section\s*\(\s*['\"]content['\"]",merged):
    raise SystemExit('BLOCKED_AUTH_MERGE_CONTENT='+rel)
slots=lambda txt:set(re.findall(r"StudioAppearance::text\(\s*'([^']+)'\s*,\s*'([^']+)'",txt))
required=slots(target)
if 'StudioAppearance::text' in target and not required:
    raise SystemExit('BLOCKED_AUTH_MERGE_SLOT_PATTERN='+rel)
if required-slots(merged):
    raise SystemExit('BLOCKED_AUTH_MERGE_MISSING_APPEARANCE_SLOTS='+rel)
if target.count('@csrf')>merged.count('@csrf'):
    raise SystemExit('BLOCKED_AUTH_MERGE_CSRF='+rel)
print('SAFE_AUTH_MERGE_READY='+rel)
PY_AUTH_VERIFY
  done < "$TMP/custom-auth-merges"
fi

if [ "$MODE" = "--check" ]; then
  echo "LIVE_FILES_CHANGED=NO"
  echo "DATABASE_CHANGED=NO"
  exit 0
fi

echo "========== DOWNLOAD VERIFIED COMPILED CI PACKAGE =========="
gh run download "$RUN" --repo "$REPO" --name gomovie-installer-qa-package --dir "$TMP/package"
ZIP="$(find "$TMP/package" -maxdepth 1 -type f -name 'Gomovie-Installer-QA-*.zip' -print -quit)"
test -n "$ZIP" && test -f "$ZIP.sha256"
test "$(find "$TMP/package" -maxdepth 1 -type f -name 'Gomovie-Installer-QA-*.zip' | wc -l)" -eq 1
EXPECTED_ZIP_SHA="$(awk 'NR==1 {print $1}' "$ZIP.sha256")"
test "$EXPECTED_ZIP_SHA" = "$(sha256sum "$ZIP" | awk '{print $1}')" || { echo "BLOCKED_ZIP_CHECKSUM"; exit 1; }
echo "CI_PACKAGE_SHA256=VERIFIED"
unzip -tqq "$ZIP"
while IFS= read -r f; do
  if [ "$f" = "$AUTH" ]; then continue; fi
  mkdir -p "$TMP/ci-source/$(dirname "$f")" "$TMP/new/$(dirname "$f")"
  unzip -p "$ZIP" "$f" > "$TMP/ci-source/$f"
  test -s "$TMP/ci-source/$f"
  case "$f" in *.blade.php) ;; *.php) "$PHP" -l "$TMP/ci-source/$f" >/dev/null ;; esac
  if [ ! -f "$TMP/new/$f" ]; then
    cp "$TMP/ci-source/$f" "$TMP/new/$f"
  fi
done < "$TMP/paths"
python3 - "$TMP" "$SOURCE" "$AUTH" <<'PY'
import sys,pathlib,json,hashlib
tmp=pathlib.Path(sys.argv[1])
tree=json.loads((tmp/('tree-'+sys.argv[2]+'.json')).read_text())
refs={x['path']:x['sha'] for x in tree['tree'] if x.get('type')=='blob'}
for p in (tmp/'paths').read_text().splitlines():
    if p==sys.argv[3]:continue
    content=(tmp/'ci-source'/p).read_bytes()
    sha=hashlib.sha1(('blob '+str(len(content))+'\x00').encode()+content).hexdigest()
    if refs.get(p)!=sha:raise SystemExit('BLOCKED_CI_BLOB_MISMATCH='+p)
print('CI_SOURCE_BLOBS=VERIFIED')
PY

echo "========== VERIFY COMPILED FRONTEND ASSETS =========="
unzip -q "$ZIP" 'public/build/*' -d "$TMP/extract"
BUILD="$TMP/extract/public/build"
test -f "$BUILD/manifest.json" && test ! -L "$BUILD/manifest.json"
test -z "$(find "$BUILD" -type l -print -quit)"
"$PHP" -r '
$m=json_decode(file_get_contents($argv[1]."/manifest.json"),true,512,JSON_THROW_ON_ERROR);
foreach (["resources/css/app.css","resources/js/app.js"] as $name) {
 $f=$m[$name]["file"]??null;
 if (!$f || !is_file($argv[1]."/".$f)) exit(3);
}
' "$BUILD"
while IFS= read -r -d '' src; do
  rel="$(realpath --relative-to="$BUILD" "$src")"
  case "$rel" in manifest.json|fonts-manifest.json|assets/*) ;; *) echo "BLOCKED_ASSET_PATH=$rel"; exit 1;; esac
  dst="$APP/public/build/$rel"
  case "$rel" in
    assets/*)
      if [ -e "$dst" ] || [ -L "$dst" ]; then
        test -f "$dst" && test ! -L "$dst" && cmp -s "$src" "$dst" || { echo "BLOCKED_CHANGED_ASSET=$rel"; exit 1; }
      fi ;;
    fonts-manifest.json)
      if [ -e "$dst" ] || [ -L "$dst" ]; then test -f "$dst" && test ! -L "$dst"; fi ;;
  esac
done < <(find "$BUILD" -type f -print0)
echo "CI_FRONTEND_BUILD=VERIFIED"

echo "========== BACKUP LIVE SOURCES =========="
while IFS= read -r f; do
  if [ -f "$APP/$f" ]; then
    mkdir -p "$TMP/old/$(dirname "$f")"
    cp -p "$APP/$f" "$TMP/old/$f"
  fi
done < "$TMP/paths"
cp -p "$APP/public/build/manifest.json" "$TMP/old/manifest.json"
if [ -e "$APP/public/build/fonts-manifest.json" ]; then
  test -f "$APP/public/build/fonts-manifest.json" && test ! -L "$APP/public/build/fonts-manifest.json"
  cp -p "$APP/public/build/fonts-manifest.json" "$TMP/old/fonts-manifest.json"
fi

echo "========== PRIVATE MYSQL DATABASE BACKUP =========="
APP_PATH="$APP" BACKUP_PATH="$TMP" "$PHP" <<'PHP'
<?php
require_once getenv('APP_PATH').'/vendor/autoload.php';
$app=require getenv('APP_PATH').'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c=config('database.connections.'.config('database.default'));
if (($c['driver']??null)!=='mysql') {fwrite(STDERR,"BLOCKED_DATABASE_DRIVER\n");exit(8);}
$name=(string)($c['database']??'');
if (!preg_match('/\A[A-Za-z0-9_]+\z/D',$name)) exit(9);
$q=static fn($v):string=>'"'.str_replace(["\\",'"',"\n","\r"],["\\\\",'\\"','',''],(string)$v).'"';
$cfg="[client]\n";
foreach (['host','port','username'=>'user','password','unix_socket'=>'socket'] as $k=>$v) {
 $field=is_int($k)?$v:$k;
 $out=is_int($k)?$v:$v;
 if (isset($c[$field]) && (string)$c[$field]!=='') $cfg.=$out.'='.$q($c[$field])."\n";
}
$root=getenv('BACKUP_PATH');
file_put_contents($root.'/mysql.cnf',$cfg);chmod($root.'/mysql.cnf',0600);
file_put_contents($root.'/db-name',$name);chmod($root.'/db-name',0600);
PHP
NAME="$(cat "$TMP/db-name")"
"$DUMP" --defaults-extra-file="$TMP/mysql.cnf" --single-transaction --quick \
  --no-tablespaces --set-gtid-purged=OFF "$NAME" | gzip -1 > "$TMP/database.sql.gz"
test -s "$TMP/database.sql.gz" && gzip -t "$TMP/database.sql.gz"
rm -f -- "$TMP/mysql.cnf" "$TMP/db-name"
echo "DATABASE_BACKUP=VERIFIED"

echo "========== RECHECK ORIGINAL FILES =========="
while IFS= read -r f; do
  if [ -f "$TMP/old/$f" ]; then
    test -f "$APP/$f" && test ! -L "$APP/$f"
    cmp -s "$TMP/old/$f" "$APP/$f"
  else
    test ! -e "$APP/$f" && test ! -L "$APP/$f"
  fi
done < "$TMP/paths"
cmp -s "$TMP/old/manifest.json" "$APP/public/build/manifest.json"

echo "========== APPLY THREE ADDITIVE APPEARANCE MIGRATIONS =========="
CHANGED=1
for migration in \
  database/migrations/2026_11_09_000400_create_studio_appearance_tables.php \
  database/migrations/2026_11_09_000500_create_studio_content_slots_table.php \
  database/migrations/2026_11_09_000600_add_studio_typography_variants.php; do
  mkdir -p "$APP/$(dirname "$migration")"
  install -o root -g www -m 0644 "$TMP/new/$migration" "$APP/$migration"
  ( cd "$APP" && runuser -u www -- "$PHP" artisan migrate --path="$migration" --force --no-interaction )
done
echo "APPEARANCE_MIGRATIONS=APPLIED"

echo "========== INSTALL VITE ASSETS =========="
while IFS= read -r -d '' src; do
  rel="$(realpath --relative-to="$BUILD" "$src")"
  case "$rel" in
    manifest.json) continue ;;
    assets/*|fonts-manifest.json)
      dst="$APP/public/build/$rel"
      install -d -o root -g www -m 0755 "$(dirname "$dst")"
      if [ "$rel" = fonts-manifest.json ] || [ ! -e "$dst" ]; then
        install -o root -g www -m 0644 "$src" "$dst"
      fi ;;
  esac
done < <(find "$BUILD" -type f -print0)

echo "========== INSTALL SELECTIVE STUDIO SOURCE =========="
while IFS= read -r f; do
  case "$f" in database/migrations/*) continue;; esac
  mkdir -p "$APP/$(dirname "$f")"
  install -o root -g www -m 0644 "$TMP/new/$f" "$APP/$f"
done < "$TMP/paths"
test ! -e "$APP/public/build/manifest.json.gomovie-next"
install -o root -g www -m 0644 "$BUILD/manifest.json" "$APP/public/build/manifest.json.gomovie-next"
mv -- "$APP/public/build/manifest.json.gomovie-next" "$APP/public/build/manifest.json"

echo "========== LARAVEL AND WEBSITE SMOKE TESTS =========="
(
 cd "$APP"
 runuser -u www -- "$PHP" artisan optimize:clear --no-interaction
 runuser -u www -- "$PHP" artisan view:cache --no-interaction
 runuser -u www -- "$PHP" artisan route:list --path=appearance
 runuser -u www -- "$PHP" artisan route:list --path=account
)
for uri in / /about /browse /login /register; do
  status="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' "https://$HOST$uri")"
  echo "PUBLIC_ROUTE=$uri HTTP=$status"
  test "$status" = 200
done
for uri in /dashboard/appearance /account; do
  status="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' "https://$HOST$uri")"
  echo "PROTECTED_ROUTE=$uri GUEST_HTTP=$status"
  test "$status" = 302
done

echo "========== VERIFY FINAL FILE INTEGRITY =========="
while IFS= read -r f; do
  cmp -s "$APP/$f" "$TMP/new/$f" || { echo "BLOCKED_INSTALLED_MISMATCH=$f"; exit 1; }
done < "$TMP/paths"
cmp -s "$APP/public/build/manifest.json" "$BUILD/manifest.json"
COMPLETE=1
echo "GOMOVIE_ACCOUNT_APPEARANCE=INSTALLED"
echo "CUSTOM_AUTH=CONTENT_PRESERVED"
echo "DATABASE_CHANGE=APPEARANCE_ONLY"
echo "PRIVATE_VIDEOS=UNCHANGED"
echo "PUBLIC_STREAMING=UNCHANGED"
echo "PAYMENTS=UNCHANGED"
echo "GOMOVIE_AAPANEL_DEPLOYMENT=SUCCESS"
echo "BACKUP_DIRECTORY=$TMP"
