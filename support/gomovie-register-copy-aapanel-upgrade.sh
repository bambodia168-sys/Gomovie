#!/usr/bin/env bash
# Go Movie: register page text only. Preserve all customized forms and layout.
set -Eeuo pipefail
umask 077
if [ "$#" -ne 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
  echo "Usage: bash gomovie-register-copy-aapanel-upgrade.sh --check|--apply" >&2; exit 2
fi
MODE="$1"
REPO="bambodia168-sys/Gomovie"
SOURCE="88b97af31ddc6617047dfae7d36987605619e43e"
APP="/www/wwwroot/gomovie.tonsamith.com"
HOST="gomovie.tonsamith.com"
PHP="/www/server/php/85/bin/php"
echo "========== GO MOVIE REGISTER COPY =========="
echo "MODE=$MODE"
test "$(id -u)" -eq 0
test -d "$APP" && test ! -L "$APP" && test "$(realpath -e "$APP")" = "$APP"
test -f "$APP/artisan" && test -f "$APP/vendor/autoload.php" && test -x "$PHP"
id www >/dev/null
for bin in git gh python3 curl runuser install cp cmp base64; do command -v "$bin" >/dev/null; done
for file in resources/views/auth/register.blade.php app/Support/StudioAppearance.php; do
  test -f "$APP/$file" && test ! -L "$APP/$file" || { echo "BLOCKED_SOURCE_TYPE=$file"; exit 1; }
done
TMP="$(mktemp -d /root/Gomovie-Register-Copy-XXXXXXXX)"
chmod 700 "$TMP"
mkdir -p "$TMP/new/resources/views/auth" "$TMP/new/app/Support" "$TMP/old/resources/views/auth" "$TMP/old/app/Support"
CHANGED=0
DB_CHANGED=0
COMPLETE=0

DB_CODE="$(cat <<'PHP'
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$mode=$argv[1]??"probe";
$fail=function($x){fwrite(STDERR,"BLOCKED_REGISTER_DB_".$x."\n");exit(2);};
if(!\Illuminate\Support\Facades\Schema::hasTable("studio_pages"))$fail("MISSING_TABLE");
$old=["heading"=>"Create your account","description"=>"Join Gomovie and build your own movie and series watchlist.","seo_title"=>"Create your account"];
$new=["heading"=>"Create Account","description"=>"Build your own movies and series watchlist.","seo_title"=>"Create Account"];
$check=function($row)use($old,$new,$fail){
 if(!$row)return;
 if($row->is_custom)$fail("CUSTOM_ROW");
 foreach(["heading","description"] as $key){
  $v=$row->$key;
  if($v!==null&&$v!==""&&$v!==$old[$key]&&$v!==$new[$key])$fail("CUSTOM_".$key);
 }
};
if($mode==="probe"){
 $row=\Illuminate\Support\Facades\DB::table("studio_pages")->where("slug","register")->first();
 $check($row);
 echo json_encode($row?["id"=>(int)$row->id,"heading"=>$row->heading,"description"=>$row->description,"seo_title"=>$row->seo_title]:["id"=>null],JSON_THROW_ON_ERROR);
 exit;
}
if($mode==="apply"){
 \Illuminate\Support\Facades\DB::transaction(function()use($old,$new,$check){
  $row=\Illuminate\Support\Facades\DB::table("studio_pages")->where("slug","register")->lockForUpdate()->first();
  $check($row);
  if(!$row){echo "REGISTER_DB_OVERRIDE=ABSENT\n";return;}
  $values=[];
  foreach($old as $key=>$value)if($row->$key===$value)$values[$key]=$new[$key];
  if($values){
   $values["updated_at"]=now();
   \Illuminate\Support\Facades\DB::table("studio_pages")->where("id",$row->id)->update($values);
  }
  echo "REGISTER_DB_COPY=VERIFIED\n";
 });
 exit;
}
if($mode==="restore"){
 $saved=json_decode(base64_decode($argv[2]??"",true),true,512,JSON_THROW_ON_ERROR);
 if(!array_key_exists("id",$saved))$fail("BAD_SNAPSHOT");
 if($saved["id"]===null){echo "REGISTER_DB_RESTORED=NO_ROW\n";exit;}
 \Illuminate\Support\Facades\DB::transaction(function()use($old,$new,$saved,$fail){
  $row=\Illuminate\Support\Facades\DB::table("studio_pages")->where("slug","register")->lockForUpdate()->first();
  if(!$row||(int)$row->id!==(int)$saved["id"])$fail("ROW_CHANGED");
  $values=[];
  foreach($old as $key=>$value){
   if(($saved[$key]??null)===$value&&$row->$key===$new[$key])$values[$key]=$value;
  }
  if($values)\Illuminate\Support\Facades\DB::table("studio_pages")->where("id",$row->id)->update($values+["updated_at"=>now()]);
  echo "REGISTER_DB_ROLLBACK=COMPLETE\n";
 });
 exit;
}
$fail("BAD_ACTION");
PHP
)"
db_action() {
  ( cd "$APP" && runuser -u www -- "$PHP" -r "$DB_CODE" "$1" "$2" )
}
cleanup() {
  code="$?"
  trap - EXIT
  if [ "$CHANGED" = 1 ] && [ "$COMPLETE" = 0 ]; then
    set +e
    echo "========== ROLLBACK REGISTER COPY =========="
    for file in resources/views/auth/register.blade.php app/Support/StudioAppearance.php; do
      cp -p -- "$TMP/old/$file" "$APP/$file"
    done
    if [ "$DB_CHANGED" = 1 ]; then
      snap="$(base64 -w 0 "$TMP/appearance-before.json")"
      db_action restore "$snap" || echo "REGISTER_DB_RESTORE_REQUIRES_REVIEW"
    fi
    ( cd "$APP" && runuser -u www -- "$PHP" artisan optimize:clear --no-interaction ) >/dev/null 2>&1
    echo "REGISTER_SOURCE_ROLLBACK=COMPLETE"
  fi
  if [ "$MODE" = "--check" ]; then rm -rf -- "$TMP"; else echo "BACKUP_DIRECTORY=$TMP"; fi
  exit "$code"
}
trap cleanup EXIT
echo "========== VERIFY PINNED SOURCE FILES =========="
test "$(gh api "repos/$REPO/commits/$SOURCE" --jq .sha)" = "$SOURCE"
for file in resources/views/auth/register.blade.php app/Support/StudioAppearance.php; do
  case "$file" in
    resources/views/auth/register.blade.php) expected="1ef69e0a913bac1741462702cafceb6aa604d9bb";;
    app/Support/StudioAppearance.php) expected="8708d2b3f624929ec48dc5783802ad98ae2da42b";;
  esac
  test "$(gh api "repos/$REPO/contents/$file?ref=$SOURCE" --jq .sha)" = "$expected"
  gh api -H 'Accept: application/vnd.github+json' "repos/$REPO/git/blobs/$expected" > "$TMP/blob.json"
  python3 - "$TMP/blob.json" "$expected" <<'PY_VERIFY'
import base64,binascii,hashlib,json,re,sys
obj=json.load(open(sys.argv[1]));expected=sys.argv[2]
if obj.get("sha")!=expected or obj.get("encoding")!="base64":raise SystemExit("BLOCKED_BLOB_METADATA")
try: data=base64.b64decode(re.sub(r"\s+","",obj["content"]),validate=True)
except (KeyError,TypeError,binascii.Error):raise SystemExit("BLOCKED_BLOB_BASE64")
actual=hashlib.sha1(("blob "+str(len(data))+"\0").encode()+data).hexdigest()
if actual!=expected:raise SystemExit("BLOCKED_BLOB_HASH")
print("GITHUB_REGISTER_SOURCE=VERIFIED:"+expected)
PY_VERIFY
done

echo "========== PREPARE SURGICAL LIVE TEXT REPLACEMENT =========="
python3 - "$APP" "$TMP/new" <<'PY_COPY'
from pathlib import Path
import sys
app=Path(sys.argv[1]);output=Path(sys.argv[2])
old_heading="Create your account";new_heading="Create Account"
old_desc="Join Gomovie and build your own movie and series watchlist."
new_desc="Build your own movies and series watchlist."
blade="resources/views/auth/register.blade.php"
appearance="app/Support/StudioAppearance.php"
for path in (blade,appearance):
 text=(app/path).read_text(encoding="utf8")
 if path==blade:
  replacements=[
   ("'register','seo_title','"+old_heading+"'","'register','seo_title','"+new_heading+"'"),
   ("'register','heading','"+old_heading+"'","'register','heading','"+new_heading+"'"),
   ("'register','description','"+old_desc+"'","'register','description','"+new_desc+"'")
  ]
 else:
  replacements=[
   ("'register' => ['label'=>'Registration','route'=>'register','eyebrow'=>null,'heading'=>'"+old_heading+"','description'=>'"+old_desc+"']",
    "'register' => ['label'=>'Registration','route'=>'register','eyebrow'=>null,'heading'=>'"+new_heading+"','description'=>'"+new_desc+"']")
  ]
 updated=text
 for before,after in replacements:
  if updated.count(before)==1 and updated.count(after)==0:updated=updated.replace(before,after,1)
  elif updated.count(before)==0 and updated.count(after)==1:pass
  else:raise SystemExit("BLOCKED_CUSTOM_REGISTER_COPY="+path)
 if path==blade:
  for needle in ("@csrf","route('register')","auth.partials.input"):
   if updated.count(needle)!=text.count(needle) or needle not in updated:raise SystemExit("BLOCKED_REGISTER_FORM="+needle)
 else:
  if text.count("public static function text(")!=updated.count("public static function text("):
   raise SystemExit("BLOCKED_APPEARANCE_FUNCTION")
 target=output/path;target.parent.mkdir(parents=True,exist_ok=True);target.write_text(updated,encoding="utf8")
 print("REGISTER_COPY_READY="+path)
print("REGISTER_COPY_SOURCE_PREFLIGHT=PASS")
PY_COPY
echo "========== INSPECT APPEARANCE OVERRIDE WITHOUT WRITING =========="
db_action probe "" > "$TMP/appearance-before.json"
python3 - "$TMP/appearance-before.json" <<'PY_CHECK'
import json,sys
r=json.load(open(sys.argv[1]))
print("REGISTER_DB_OVERRIDE="+("NONE" if r["id"] is None else "CHECKED"))
PY_CHECK
if [ "$MODE" = "--check" ]; then
  echo "GOMOVIE_REGISTER_COPY_PREFLIGHT=PASS"
  echo "LIVE_FILES_CHANGED=NO"
  echo "DATABASE_CHANGED=NO"
  exit 0
fi
echo "========== BACKUP REGISTER FILES =========="
for file in resources/views/auth/register.blade.php app/Support/StudioAppearance.php; do
  cp -p -- "$APP/$file" "$TMP/old/$file"
  cmp -s "$APP/$file" "$TMP/old/$file"
done
db_action probe "" > "$TMP/appearance-before.json"
echo "REGISTER_BACKUP=VERIFIED"
CHANGED=1
echo "========== INSTALL REGISTER COPY =========="
for file in resources/views/auth/register.blade.php app/Support/StudioAppearance.php; do
  install -o root -g www -m 0644 "$TMP/new/$file" "$APP/$file"
  cmp -s "$TMP/new/$file" "$APP/$file"
done
( cd "$APP" && runuser -u www -- "$PHP" artisan optimize:clear --no-interaction && runuser -u www -- "$PHP" artisan view:cache --no-interaction )
echo "========== UPDATE LEGACY REGISTRATION OVERRIDES IF PRESENT =========="
db_action apply ""
DB_CHANGED=1
echo "========== VERIFY REGISTER PAGE ONLY =========="
curl --noproxy '*' -fsS --connect-timeout 5 --max-time 20 --resolve "$HOST:443:127.0.0.1" \
  "https://$HOST/register" -o "$TMP/register.html"
python3 - "$TMP/register.html" <<'PY_HTTP'
from html.parser import HTMLParser
import sys
class Reader(HTMLParser):
 def __init__(self):super().__init__();self.field=None;self.heading="";self.description=""
 def handle_starttag(self,tag,attrs):
  classes=dict(attrs).get("class","").split()
  if tag=="h1" and "gm-auth-title" in classes:self.field="heading"
  if tag=="p" and "gm-auth-subtitle" in classes:self.field="description"
 def handle_endtag(self,tag):
  if tag=="h1" and self.field=="heading":self.field=None
  if tag=="p" and self.field=="description":self.field=None
 def handle_data(self,data):
  if self.field=="heading":self.heading+=data
  if self.field=="description":self.description+=data
r=Reader();r.feed(open(sys.argv[1],encoding="utf8").read())
if " ".join(r.heading.split())!="Create Account":raise SystemExit("BLOCKED_REGISTER_HEADING="+repr(r.heading))
if " ".join(r.description.split())!="Build your own movies and series watchlist.":
 raise SystemExit("BLOCKED_REGISTER_DESCRIPTION="+repr(r.description))
print("REGISTER_HEADING=Create Account")
print("REGISTER_DESCRIPTION=Build your own movies and series watchlist.")
print("REGISTER_COPY_HTTP=PASS")
PY_HTTP
COMPLETE=1
echo "GOMOVIE_REGISTER_COPY_DEPLOYMENT=SUCCESS"
echo "OTHER_PAGES_AND_FORMS=UNCHANGED"
