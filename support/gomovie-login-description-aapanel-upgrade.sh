#!/usr/bin/env bash
# Go Movie: update only the login-page Appearance description.
# No Blade replacements, form edits, migrations, or changes to other pages.
set -Eeuo pipefail
umask 077
if [ "$#" -ne 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
  echo "Usage: bash gomovie-login-description-aapanel-upgrade.sh --check|--apply" >&2
  exit 2
fi

MODE="$1"
REPO="bambodia168-sys/Gomovie"
SOURCE="e26ac26e2cd112da64ff94af6f0b4894dcb9c7d1"
APP="/www/wwwroot/gomovie.tonsamith.com"
HOST="gomovie.tonsamith.com"
PHP="/www/server/php/85/bin/php"
VIEW="$APP/resources/views/auth/login.blade.php"
APPEARANCE="$APP/app/Support/StudioAppearance.php"
echo "========== GO MOVIE LOGIN DESCRIPTION =========="
echo "MODE=$MODE"
test "$(id -u)" -eq 0
test -d "$APP" && test ! -L "$APP" && test "$(realpath -e "$APP")" = "$APP"
test -f "$APP/artisan" && test -f "$APP/vendor/autoload.php" && test -x "$PHP"
test -f "$VIEW" && test ! -L "$VIEW"
test -f "$APPEARANCE" && test ! -L "$APPEARANCE"
id www >/dev/null
for bin in git gh python3 curl runuser base64; do command -v "$bin" >/dev/null; done

TMP="$(mktemp -d /root/Gomovie-Login-Copy-XXXXXXXX)"
chmod 700 "$TMP"
CHANGED=0
COMPLETE=0

DB_CODE="$(cat <<'PHP_CODE'
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$op=$argv[1]??"probe";
$old="Sign in to manage your Gomovie account and continue exploring.";
$new="Sign in to manage your account.";
$fail=function($reason){fwrite(STDERR,"BLOCKED_LOGIN_COPY_".$reason."\n");exit(2);};
if(!\Illuminate\Support\Facades\Schema::hasTable("studio_pages"))$fail("NO_APPEARANCE_TABLE");
$table=function(){return \Illuminate\Support\Facades\DB::table("studio_pages");};
$validate=function($row)use($fail,$old,$new){
 if(!$row)return;
 if((bool)$row->is_custom)$fail("CUSTOM_PAGE_OVERRIDE");
 $desc=$row->description;
 if($desc!==null&&$desc!==""&&$desc!==$old&&$desc!==$new)$fail("UNEXPECTED_DESCRIPTION");
};
if($op==="probe"){
 $row=$table()->where("slug","login")->first();$validate($row);
 echo json_encode($row?[
  "exists"=>true,"id"=>(int)$row->id,"description"=>$row->description
 ]:["exists"=>false],JSON_THROW_ON_ERROR);
 exit;
}
if($op==="apply"){
 $snap=json_decode(base64_decode($argv[2]??"",true),true,512,JSON_THROW_ON_ERROR);
 \Illuminate\Support\Facades\DB::transaction(function()use($table,$validate,$fail,$snap,$new){
  $row=$table()->where("slug","login")->lockForUpdate()->first();$validate($row);
  if((bool)$row!==(bool)($snap["exists"]??false))$fail("ROW_CHANGED");
  if($row){
   if((int)$row->id!==(int)$snap["id"]||$row->description!==$snap["description"])$fail("CONCURRENT_CHANGE");
   if($row->description===$new){echo "LOGIN_DESCRIPTION=ALREADY_CURRENT\n";return;}
   $table()->where("id",$row->id)->update(["description"=>$new,"updated_at"=>now()]);
   echo "LOGIN_DESCRIPTION_OVERRIDE=UPDATED\n";
  }else{
   $table()->insert([
    "slug"=>"login","is_custom"=>false,"is_published"=>true,
    "description"=>$new,"created_at"=>now(),"updated_at"=>now()
   ]);
   echo "LOGIN_DESCRIPTION_OVERRIDE=CREATED\n";
  }
 });
 exit;
}
if($op==="restore"){
 $snap=json_decode(base64_decode($argv[2]??"",true),true,512,JSON_THROW_ON_ERROR);
 \Illuminate\Support\Facades\DB::transaction(function()use($table,$fail,$snap,$new){
  $row=$table()->where("slug","login")->lockForUpdate()->first();
  if(!$row||$row->description!==$new)$fail("ROLLBACK_CONFLICT");
  if($snap["exists"]??false){
   if((int)$row->id!==(int)$snap["id"])$fail("ROLLBACK_ID_CHANGED");
   $table()->where("id",$row->id)->update(["description"=>$snap["description"],"updated_at"=>now()]);
  }else{
   if($row->is_custom||$row->eyebrow!==null||$row->heading!==null||
      $row->body!==null||$row->seo_title!==null||$row->seo_description!==null)
      $fail("ROLLBACK_NEW_ROW_MODIFIED");
   $table()->where("id",$row->id)->delete();
  }
  echo "LOGIN_DESCRIPTION_ROLLBACK=COMPLETE\n";
 });
 exit;
}
$fail("UNKNOWN_ACTION");
PHP_CODE
)"
db_action(){
  (cd "$APP" && runuser -u www -- "$PHP" -r "$DB_CODE" "$@")
}
cleanup(){
  code="$?"
  trap - EXIT
  if [ "$CHANGED" = 1 ] && [ "$COMPLETE" = 0 ]; then
    set +e
    echo "========== RESTORE LOGIN DESCRIPTION =========="
    snap="$(base64 -w 0 "$TMP/appearance-before.json")"
    db_action restore "$snap" || echo "LOGIN_DESCRIPTION_ROLLBACK_REQUIRES_REVIEW"
  fi
  if [ "$MODE" = "--check" ]; then rm -rf -- "$TMP"; else echo "BACKUP_DIRECTORY=$TMP"; fi
  exit "$code"
}
trap cleanup EXIT

echo "========== VERIFY COMMITTED GITHUB LOGIN DEFAULT =========="
test "$(gh api "repos/$REPO/commits/$SOURCE" --jq .sha)" = "$SOURCE"
test "$(gh api "repos/$REPO/contents/resources/views/auth/login.blade.php?ref=$SOURCE" --jq .sha)" = "3c1a7ea518c501b4af8b3bc38b0a93ad7c28f8ac"
test "$(gh api "repos/$REPO/contents/app/Support/StudioAppearance.php?ref=$SOURCE" --jq .sha)" = "3a49e7fb91743393a9cce8f2c40a53b561187878"

echo "========== VERIFY PRODUCTION LOGIN APPEARANCE SLOT =========="
python3 - "$VIEW" "$APPEARANCE" <<'PY_BLADE'
import pathlib,re,sys
view=pathlib.Path(sys.argv[1]).read_text(encoding="utf8")
support=pathlib.Path(sys.argv[2]).read_text(encoding="utf8")
if not view or len(view)>1048576 or "\x00" in view:
 raise SystemExit("BLOCKED_LOGIN_VIEW_NOT_TEXT")
if not re.search(r"StudioAppearance::text\s*\(\s*['\"]login['\"]\s*,\s*['\"]description['\"]",view):
 raise SystemExit("BLOCKED_LOGIN_APPEARANCE_SLOT_MISSING")
if not re.search(r"(?m)^\s*public\s+static\s+function\s+text\s*\(",support):
 raise SystemExit("BLOCKED_APPEARANCE_METHOD_MISSING")
for needed in ("gm-auth-subtitle","gm-auth-title","route('login')","@csrf"):
 if needed not in view:raise SystemExit("BLOCKED_LOGIN_STRUCTURE_MISSING="+needed)
print("LOGIN_APPEARANCE_SLOT=VERIFIED")
PY_BLADE

echo "========== BACKUP ORIGINAL APPEARANCE VALUE =========="
db_action probe > "$TMP/appearance-before.json"
python3 - "$TMP/appearance-before.json" <<'PY_SNAPSHOT'
import json,sys
obj=json.load(open(sys.argv[1],encoding="utf8"))
if not isinstance(obj,dict) or "exists" not in obj:
 raise SystemExit("BLOCKED_APPEARANCE_SNAPSHOT")
print("LOGIN_APPEARANCE_OVERRIDE="+("PRESENT" if obj["exists"] else "ABSENT"))
PY_SNAPSHOT

if [ "$MODE" = "--check" ]; then
  echo "GOMOVIE_LOGIN_DESCRIPTION_PREFLIGHT=PASS"
  echo "LIVE_FILES_CHANGED=NO"
  echo "DATABASE_CHANGED=NO"
  exit 0
fi

echo "========== APPLY REGISTER-INDEPENDENT LOGIN DESCRIPTION =========="
snap="$(base64 -w 0 "$TMP/appearance-before.json")"
CHANGED=1
db_action apply "$snap"

echo "========== VERIFY LIVE LOGIN TEXT =========="
curl --noproxy '*' -fsS --connect-timeout 5 --max-time 20 \
  --resolve "$HOST:443:127.0.0.1" "https://$HOST/login" -o "$TMP/login-after.html"
python3 - "$TMP/login-after.html" <<'PY_HTTP'
from html.parser import HTMLParser
import sys
class LoginText(HTMLParser):
 def __init__(self):super().__init__();self.active=False;self.description=""
 def handle_starttag(self,tag,attrs):
  if tag=="p" and "gm-auth-subtitle" in dict(attrs).get("class","").split():self.active=True
 def handle_endtag(self,tag):
  if tag=="p" and self.active:self.active=False
 def handle_data(self,data):
  if self.active:self.description+=data
p=LoginText();p.feed(open(sys.argv[1],encoding="utf8").read())
actual=" ".join(p.description.split())
if actual!="Sign in to manage your account.":
 raise SystemExit("BLOCKED_LOGIN_DESCRIPTION_HTML="+repr(actual))
print("LOGIN_DESCRIPTION=Sign in to manage your account.")
print("LOGIN_COPY_HTTP=PASS")
PY_HTTP
COMPLETE=1
echo "GOMOVIE_LOGIN_DESCRIPTION_DEPLOYMENT=SUCCESS"
echo "LOGIN_FORMS_AND_OTHER_PAGES=UNCHANGED"
