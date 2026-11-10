#!/usr/bin/env bash
# Go Movie: guarded navigation, roles, languages, avatar and watch-gate release.
set -Eeuo pipefail
umask 077
if [ "$#" -ne 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
 echo "Usage: bash gomovie-navigation-account-watch-aapanel-upgrade.sh --check|--apply"; exit 2
fi
MODE="$1"
REPO="bambodia168-sys/Gomovie"
BASE="22115263a57504a6e6753ebc4891c7efaa08bab8"
SOURCE="f26b8bcb3879be68e08b4c8c36f1c3ded059b3fb"
APP="/www/wwwroot/gomovie.tonsamith.com"
HOST="gomovie.tonsamith.com"
PHP="/www/server/php/85/bin/php"
DUMP="/www/server/mysql/bin/mysqldump"
MIGRATION="database/migrations/2026_11_10_000700_add_account_avatar_to_users.php"

paths(){
cat <<'PATHS'
app/Support/StudioNavigation.php
app/Http/Controllers/LocaleController.php
app/Http/Middleware/ApplyGoMovieLocale.php
app/Http/Controllers/Account/SettingsController.php
app/Http/Controllers/Auth/SessionController.php
bootstrap/app.php
database/migrations/2026_11_10_000700_add_account_avatar_to_users.php
routes/web.php
resources/views/partials/account-avatar.blade.php
resources/views/partials/header-actions.blade.php
resources/views/partials/header-ui.blade.php
resources/views/frontend/partials/guest-watch-signin.blade.php
resources/views/frontend/detail.blade.php
resources/views/layouts/app.blade.php
resources/views/layouts/admin.blade.php
resources/views/layouts/account.blade.php
resources/views/account/settings.blade.php
PATHS
}

echo "========== GOMOVIE NAVIGATION ACCOUNT WATCH =========="
echo "MODE=$MODE"
test "$(id -u)" -eq 0
for tool in gh git python3 curl cmp cp runuser install gzip; do
 command -v "$tool" >/dev/null || { echo "BLOCKED_MISSING_TOOL=$tool"; exit 1; }
done
test -x "$PHP" && test -x "$DUMP"
test -d "$APP" && test ! -L "$APP" && test "$(realpath -e "$APP")" = "$APP"
test -f "$APP/artisan" && test -f "$APP/vendor/autoload.php"
id www >/dev/null

TMP="$(mktemp -d /root/Gomovie-Navigation-Account-XXXXXXXX)"
chmod 700 "$TMP"
mkdir -p "$TMP/new" "$TMP/base" "$TMP/target" "$TMP/old"
paths > "$TMP/paths"
CHANGED=0
COMPLETE=0

cleanup(){
 status="$?"
 trap - EXIT
 if [ "$CHANGED" = 1 ] && [ "$COMPLETE" = 0 ]; then
   set +e
   echo "========== RESTORE SOURCE FILES =========="
   while IFS= read -r file; do
     if [ -f "$TMP/old/$file" ]; then
       cp -p -- "$TMP/old/$file" "$APP/$file"
     else
       rm -f -- "$APP/$file"
     fi
   done < "$TMP/paths"
   (cd "$APP" && runuser -u www -- "$PHP" artisan optimize:clear --no-interaction) >/dev/null 2>&1
   echo "NAVIGATION_SOURCE_ROLLBACK=COMPLETE"
   echo "DATABASE_NOTE=Additive avatar column may remain; full backup retained."
 fi
 rm -f -- "$TMP/mysql.cnf" "$TMP/db-name" "$TMP/blob.json"
 if [ "$MODE" = "--check" ]; then
   rm -rf -- "$TMP"
 else
   echo "BACKUP_DIRECTORY=$TMP"
 fi
 exit "$status"
}
trap cleanup EXIT

echo "========== VERIFY EXACT SOURCE AND CI ACCEPTANCE =========="
test "$(gh api "repos/$REPO/commits/$SOURCE" --jq .sha)" = "$SOURCE"
for run in 42836838933 42836838702 42836838704; do
 result="$(gh api "repos/$REPO/actions/runs/$run" --jq '[.head_sha,.conclusion] | join(":")')"
 if [ "$result" != "$SOURCE:success" ]; then
   echo "BLOCKED_GITHUB_CI=$run:$result"; exit 1
 fi
 echo "VERIFIED_GITHUB_CI=$run:success"
done

echo "========== PREPARE PINNED RELEASE BLOBS =========="
gh api -H 'Accept: application/vnd.github+json' \
 "repos/$REPO/git/trees/$BASE?recursive=1" > "$TMP/base-tree.json"
gh api -H 'Accept: application/vnd.github+json' \
 "repos/$REPO/git/trees/$SOURCE?recursive=1" > "$TMP/target-tree.json"
python3 - "$TMP/base-tree.json" "$TMP/target-tree.json" "$TMP/paths" > "$TMP/manifest" <<'PY_MANIFEST'
import json,pathlib,re,sys
base,target,path_file=map(pathlib.Path,sys.argv[1:])
b=json.loads(base.read_text(encoding="utf8"))
t=json.loads(target.read_text(encoding="utf8"))
if b.get("truncated") or t.get("truncated"):raise SystemExit("BLOCKED_TREE_TRUNCATED")
bmap={r["path"]:r for r in b["tree"] if r.get("type")=="blob"}
tmap={r["path"]:r for r in t["tree"] if r.get("type")=="blob"}
paths=path_file.read_text(encoding="utf8").splitlines()
if len(paths)!=17 or len(set(paths))!=17:raise SystemExit("BLOCKED_PATH_COUNT")
for path in paths:
 if not re.fullmatch(r"[A-Za-z0-9_./-]+",path) or ".." in path.split("/"):
  raise SystemExit("BLOCKED_RELEASE_PATH")
 item=tmap.get(path)
 if not item or not re.fullmatch(r"[0-9a-f]{40}",item.get("sha","")):
  raise SystemExit("BLOCKED_GITHUB_PATH="+path)
 previous=bmap.get(path,{}).get("sha","-")
 if previous!="-" and not re.fullmatch(r"[0-9a-f]{40}",previous):
  raise SystemExit("BLOCKED_BASE_BLOB")
 print(path+"\t"+previous+"\t"+item["sha"])
PY_MANIFEST
download(){
 sha="$1"
 dest="$2"
 gh api -H 'Accept: application/vnd.github+json' "repos/$REPO/git/blobs/$sha" > "$TMP/blob.json"
 python3 - "$TMP/blob.json" "$dest" "$sha" <<'PY_BLOB'
import base64,binascii,hashlib,json,pathlib,re,sys
response,dest,expected=sys.argv[1:]
obj=json.loads(pathlib.Path(response).read_text(encoding="utf8"))
if obj.get("sha")!=expected or obj.get("encoding")!="base64":
 raise SystemExit("BLOCKED_BLOB_METADATA")
try:data=base64.b64decode(re.sub(r"\s+","",obj["content"]),validate=True)
except (KeyError,TypeError,binascii.Error):raise SystemExit("BLOCKED_BLOB_ENCODING")
actual=hashlib.sha1(("blob "+str(len(data))+"\0").encode()+data).hexdigest()
if not data or actual!=expected:raise SystemExit("BLOCKED_BLOB_INTEGRITY")
file=pathlib.Path(dest)
file.parent.mkdir(parents=True,exist_ok=True)
file.write_bytes(data)
PY_BLOB
}

echo "========== PRESERVE CUSTOM PRODUCTION SOURCE =========="
while IFS=$'\t' read -r file base_sha target_sha; do
 live="$APP/$file"
 target="$TMP/target/$file"
 prepared="$TMP/new/$file"
 download "$target_sha" "$target"
 mkdir -p "$(dirname "$prepared")"
 if [ "$base_sha" = "-" ]; then
   if [ -e "$live" ] || [ -L "$live" ]; then
     test -f "$live" && test ! -L "$live" || { echo "BLOCKED_NEW_FILE_TYPE=$file"; exit 1; }
     test "$(git hash-object "$live")" = "$target_sha" || { echo "BLOCKED_NEW_FILE_COLLISION=$file"; exit 1; }
   fi
   cp "$target" "$prepared"
   echo "NEW_FILE=$file"
 else
   test -f "$live" && test ! -L "$live" || { echo "BLOCKED_LOCAL_FILE=$file"; exit 1; }
   current="$(git hash-object "$live")"
   if [ "$current" = "$target_sha" ] || [ "$current" = "$base_sha" ]; then
     cp "$target" "$prepared"
     echo "KNOWN_SOURCE=$file"
   else
     baseline="$TMP/base/$file"
     download "$base_sha" "$baseline"
     if ! git merge-file -p -- "$live" "$baseline" "$target" > "$prepared"; then
       echo "BLOCKED_CUSTOM_MERGE_CONFLICT=$file"; echo "LIVE_BLOB=$current"; exit 1
     fi
     if grep -Eq '^(<<<<<<<|=======|>>>>>>>)' "$prepared"; then
       echo "BLOCKED_MERGE_MARKERS=$file"; exit 1
     fi
     echo "CUSTOM_SOURCE_MERGED=$file"
   fi
 fi
done < "$TMP/manifest"

echo "========== VERIFY MERGED SECURITY CONTRACTS =========="
python3 - "$TMP/new" <<'PY_CONTRACT'
from pathlib import Path
import sys
root=Path(sys.argv[1])
checks={
 "routes/web.php":["name('locale.change')","name('account.avatar')","name('account.avatar.update')","'auth','active','verified','throttle:240,1'"],
 "bootstrap/app.php":["ApplyGoMovieLocale::class"],
 "app/Http/Controllers/Auth/SessionController.php":["StudioNavigation::landing","StudioNavigation::watchReturn"],
 "app/Support/StudioNavigation.php":["public static function landing","public const LANGUAGES","public static function watchReturn"],
 "app/Http/Controllers/Account/SettingsController.php":["public function updateAvatar","public function showAvatar","Storage::disk('local')"],
 "resources/views/layouts/app.blade.php":["partials.header-actions","partials.header-ui"],
 "resources/views/layouts/admin.blade.php":["partials.header-actions","partials.header-ui"],
 "resources/views/layouts/account.blade.php":["partials.account-avatar"],
 "resources/views/account/settings.blade.php":["account.avatar.update"],
 "resources/views/frontend/detail.blade.php":["$publicAsset && $viewerAuthorized","frontend.partials.guest-watch-signin"],
 "resources/views/frontend/partials/guest-watch-signin.blade.php":["gm-watch-login-dialog","route('login')"],
 "resources/views/partials/header-actions.blade.php":["gm-profile-popover","gm-locale-popover","locale.change"],
 "resources/views/partials/header-ui.blade.php":["gomovie-theme-mode"],
 "database/migrations/2026_11_10_000700_add_account_avatar_to_users.php":["avatar_path"],
}
for file,needles in checks.items():
 txt=(root/file).read_text(encoding="utf8")
 for marker in needles:
  if marker not in txt:raise SystemExit("BLOCKED_SOURCE_CONTRACT="+file+":"+marker)
print("SOURCE_CONTRACTS=VERIFIED")
PY_CONTRACT
while IFS= read -r file; do
 case "$file" in
   *.php) "$PHP" -l "$TMP/new/$file" >/dev/null || { echo "BLOCKED_PHP_LINT=$file"; exit 1; };;
 esac
done < "$TMP/paths"
echo "RELEASE_PHP_LINT=PASS"

if [ "$MODE" = "--check" ]; then
 echo "GOMOVIE_NAVIGATION_PREFLIGHT=PASS"
 echo "LIVE_FILES_CHANGED=NO"
 echo "DATABASE_CHANGED=NO"
 exit 0
fi

echo "========== BACKUP LIVE SOURCE FILES =========="
while IFS= read -r file; do
 if [ -f "$APP/$file" ]; then
   mkdir -p "$TMP/old/$(dirname "$file")"
   cp -p -- "$APP/$file" "$TMP/old/$file"
 fi
done < "$TMP/paths"

echo "========== PRIVATE MYSQL BACKUP =========="
APP_PATH="$APP" BACKUP_PATH="$TMP" "$PHP" <<'PHP_BACKUP'
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
PHP_BACKUP
NAME="$(cat "$TMP/db-name")"
"$DUMP" --defaults-extra-file="$TMP/mysql.cnf" --single-transaction --quick \
 --no-tablespaces --set-gtid-purged=OFF "$NAME" | gzip -1 > "$TMP/database.sql.gz"
test -s "$TMP/database.sql.gz" && gzip -t "$TMP/database.sql.gz"
rm -f -- "$TMP/mysql.cnf" "$TMP/db-name"
echo "DATABASE_BACKUP=VERIFIED"

echo "========== RECHECK ORIGINAL FILES =========="
while IFS= read -r file; do
 if [ -f "$TMP/old/$file" ]; then
   test -f "$APP/$file" && test ! -L "$APP/$file"
   cmp -s "$TMP/old/$file" "$APP/$file"
 else
   test ! -e "$APP/$file" && test ! -L "$APP/$file"
 fi
done < "$TMP/paths"

prepare_parent(){
 file="$1"
 parent="$(dirname "$file")"
 current="$APP"
 original_ifs="$IFS"
 IFS="/"
 for part in $parent; do
   case "$part" in ""|"."|"..") echo "BLOCKED_DIRECTORY_COMPONENT=$file"; return 1;; esac
   current="$current/$part"
   if [ -L "$current" ]; then
     echo "BLOCKED_DIRECTORY_SYMLINK=$current"; return 1
   fi
   if [ ! -d "$current" ]; then
     test ! -e "$current" || { echo "BLOCKED_DIRECTORY_TYPE=$current"; return 1; }
     install -d -o root -g www -m 0755 "$current"
   fi
 done
 IFS="$original_ifs"
}

echo "========== INSTALL BACKED-UP GOMOVIE RELEASE =========="
CHANGED=1
while IFS= read -r file; do
 prepare_parent "$file"
 install -o root -g www -m 0644 "$TMP/new/$file" "$APP/$file"
 cmp -s "$TMP/new/$file" "$APP/$file"
 echo "INSTALLED=$file"
done < "$TMP/paths"

echo "========== APPLY ADDITIVE AVATAR SCHEMA =========="
(cd "$APP" && runuser -u www -- "$PHP" artisan migrate \
  --path="$MIGRATION" --force --no-interaction)
echo "AVATAR_SCHEMA=READY"

echo "========== VERIFY LARAVEL =========="
(
 cd "$APP"
 runuser -u www -- "$PHP" artisan optimize:clear --no-interaction
 runuser -u www -- "$PHP" artisan view:cache --no-interaction
 runuser -u www -- "$PHP" artisan route:list --path=account >/dev/null
 runuser -u www -- "$PHP" artisan route:list --path=hls >/dev/null
)
echo "LARAVEL_VIEWS_ROUTES=PASS"

echo "========== LOCAL HTTPS SMOKE TESTS =========="
for route in / /login /register; do
 output="$TMP/http-$(echo "$route" | tr / -).html"
 status="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
   --resolve "$HOST:443:127.0.0.1" -o "$output" \
   -w '%{http_code}' "https://$HOST$route")"
 test "$status" = 200 || { echo "BLOCKED_HTTP=$route:$status"; exit 1; }
 echo "NAVIGATION_HTTP=$route:200"
done
python3 - "$TMP/http--.html" <<'PY_HTTP'
from pathlib import Path
import sys
txt=Path(sys.argv[1]).read_text(encoding="utf8",errors="replace")
for word in ("gm-profile-popover","gm-locale-popover","gomovie-theme-mode"):
 if word not in txt:raise SystemExit("BLOCKED_HEADER_HTML="+word)
print("HEADER_GUEST_UI=VERIFIED")
PY_HTTP
status="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
 --resolve "$HOST:443:127.0.0.1" -o /dev/null \
 -w '%{http_code}' "https://$HOST/hls/1/master.m3u8")"
test "$status" = 302 || { echo "BLOCKED_GUEST_HLS_ACCESS=$status"; exit 1; }
echo "GUEST_HLS_AUTH_GATE=PASS"
while IFS= read -r file; do
 cmp -s "$TMP/new/$file" "$APP/$file"
done < "$TMP/paths"
COMPLETE=1
echo "GOMOVIE_NAVIGATION_RELEASE=SUCCESS"
echo "LOCAL_CUSTOMIZATIONS=PRESERVED"
echo "UPLOADS_AND_EXISTING_MEDIA=UNCHANGED"
