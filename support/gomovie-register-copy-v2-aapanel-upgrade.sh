#!/usr/bin/env bash
# Go Movie: register-only heading and description. Custom form preservation.
set -Eeuo pipefail
umask 077
if [ "$#" -ne 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
  echo "Usage: bash gomovie-register-copy-v2-aapanel-upgrade.sh --check|--apply" >&2
  exit 2
fi
MODE="$1"
APP="/www/wwwroot/gomovie.tonsamith.com"
HOST="gomovie.tonsamith.com"
PHP="/www/server/php/85/bin/php"
VIEW="$APP/resources/views/auth/register.blade.php"
APPEARANCE="$APP/app/Support/StudioAppearance.php"
echo "========== GOMOVIE REGISTER COPY RECOVERY =========="
echo "MODE=$MODE"
test "$(id -u)" -eq 0
test -d "$APP" && test ! -L "$APP" && test "$(realpath -e "$APP")" = "$APP"
test -f "$APP/artisan" && test -f "$APP/vendor/autoload.php" && test -x "$PHP"
test -f "$VIEW" && test ! -L "$VIEW"
test -f "$APPEARANCE" && test ! -L "$APPEARANCE"
id www >/dev/null
for name in python3 curl runuser cp cmp install base64; do command -v "$name" >/dev/null; done

TMP="$(mktemp -d /root/Gomovie-Register-Copy-V2-XXXXXXXX)"
chmod 700 "$TMP"
CHANGED=0
COMPLETE=0
CHANGE_MODE=""
DB_CODE="$(cat <<'PHP_CODE'
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$op=$argv[1]??"probe";
$old=["heading"=>"Create your account","description"=>"Join Gomovie and build your own movie and series watchlist."];
$new=["heading"=>"Create Account","description"=>"Build your own movies and series watchlist."];
$fail=function($msg){fwrite(STDERR,"BLOCKED_REGISTER_DB_".$msg."\n");exit(2);};
if(!\Illuminate\Support\Facades\Schema::hasTable("studio_pages"))$fail("TABLE_MISSING");
$table=function(){return \Illuminate\Support\Facades\DB::table("studio_pages");};
$check=function($row)use($old,$new,$fail){
 if(!$row)return;
 if((bool)$row->is_custom)$fail("CUSTOM_SLUG");
 foreach($old as $key=>$value){
  $v=$row->$key;
  if($v!==null&&$v!==""&&$v!==$value&&$v!==$new[$key])$fail("UNEXPECTED_".$key);
 }
};
if($op==="probe"){
 $row=$table()->where("slug","register")->first();$check($row);
 echo json_encode($row?["exists"=>true,"id"=>(int)$row->id,"heading"=>$row->heading,"description"=>$row->description]:["exists"=>false],JSON_THROW_ON_ERROR);exit;
}
if($op==="apply"){
 $saved=json_decode(base64_decode($argv[2]??"",true),true,512,JSON_THROW_ON_ERROR);
 \Illuminate\Support\Facades\DB::transaction(function()use($saved,$new,$check,$fail,$table){
  $row=$table()->where("slug","register")->lockForUpdate()->first();$check($row);
  if((bool)$row!==(bool)($saved["exists"]??false))$fail("ROW_CHANGED");
  if($row){
   if((int)$row->id!==(int)$saved["id"]||$row->heading!==$saved["heading"]||$row->description!==$saved["description"])$fail("CONCURRENT_EDIT");
   $table()->where("id",$row->id)->update(["heading"=>$new["heading"],"description"=>$new["description"],"updated_at"=>now()]);
   echo "REGISTER_APPEARANCE_ROW=UPDATED\n";
  }else{
   $table()->insert(["slug"=>"register","is_custom"=>false,"is_published"=>true,
       "heading"=>$new["heading"],"description"=>$new["description"],
       "created_at"=>now(),"updated_at"=>now()]);
   echo "REGISTER_APPEARANCE_ROW=CREATED\n";
  }
 });exit;
}
if($op==="restore"){
 $saved=json_decode(base64_decode($argv[2]??"",true),true,512,JSON_THROW_ON_ERROR);
 \Illuminate\Support\Facades\DB::transaction(function()use($saved,$new,$fail,$table){
  $row=$table()->where("slug","register")->lockForUpdate()->first();
  if(!$row||$row->heading!==$new["heading"]||$row->description!==$new["description"])$fail("RESTORE_CONFLICT");
  if($saved["exists"]??false){
   if((int)$row->id!==(int)$saved["id"])$fail("RESTORE_ID_CONFLICT");
   $table()->where("id",$row->id)->update(["heading"=>$saved["heading"],"description"=>$saved["description"],"updated_at"=>now()]);
  }else{
   if($row->is_custom||$row->eyebrow!==null||$row->seo_title!==null||$row->seo_description!==null||$row->body!==null)
     $fail("RESTORE_NEW_ROW_MODIFIED");
   $table()->where("id",$row->id)->delete();
  }
  echo "REGISTER_APPEARANCE_ROLLBACK=COMPLETE\n";
 });exit;
}
$fail("INVALID_OPERATION");
PHP_CODE
)"
db_action(){
  ( cd "$APP" && runuser -u www -- "$PHP" -r "$DB_CODE" "$@" )
}
on_exit(){
 code="$?"
 trap - EXIT
 if [ "$CHANGED" = 1 ] && [ "$COMPLETE" = 0 ]; then
  set +e
  echo "========== ROLLBACK REGISTER COPY =========="
  if [ "$CHANGE_MODE" = "blade" ]; then
   cp -p -- "$TMP/register-before.blade.php" "$VIEW"
   (cd "$APP" && runuser -u www -- "$PHP" artisan optimize:clear --no-interaction) >/dev/null 2>&1
   echo "REGISTER_BLADE_ROLLBACK=COMPLETE"
  elif [ "$CHANGE_MODE" = "appearance" ]; then
   snapshot="$(base64 -w 0 "$TMP/appearance-before.json")"
   db_action restore "$snapshot" || echo "BLOCKED_REGISTER_DB_ROLLBACK_REVIEW"
  fi
 fi
 if [ "$MODE" = "--check" ]; then rm -rf -- "$TMP"; else echo "BACKUP_DIRECTORY=$TMP"; fi
 exit "$code"
}
trap on_exit EXIT
echo "========== DETECT LIVE REGISTER VIEW STRUCTURE =========="
python3 - "$VIEW" "$APPEARANCE" "$TMP" <<'PY_BLADE'
from pathlib import Path
import re,sys
view,appearance,tmp=map(Path,sys.argv[1:])
data=view.read_text(encoding="utf8")
support=appearance.read_text(encoding="utf8")
if not data or len(data)>1048576 or "\x00" in data:raise SystemExit("BLOCKED_REGISTER_BLADE")
if not re.search(r'(?m)^\s*public\s+static\s+function\s+text\s*\(',support) or "studio_pages" not in support:
 raise SystemExit("BLOCKED_APPEARANCE_SUPPORT")
print("REGISTER_APPEARANCE_TEXT_METHOD=VERIFIED")
slot=lambda field:bool(re.search(r"StudioAppearance::text\s*\(\s*['\"]register['\"]\s*,\s*['\"]"+field+r"['\"]",data))
h,d=slot("heading"),slot("description")
old_h="Create your account";new_h="Create Account"
old_d="Join Gomovie and build your own movie and series watchlist."
new_d="Build your own movies and series watchlist."
if h and d:
 mode="appearance";target=data
 print("REGISTER_STRATEGY=APPEARANCE_DATABASE_ONLY")
elif not h and not d:
 mode="blade"
 if old_h not in data and new_h not in data:raise SystemExit("BLOCKED_REGISTER_HEADING_NOT_FOUND")
 if old_d not in data and new_d not in data:raise SystemExit("BLOCKED_REGISTER_DESCRIPTION_NOT_FOUND")
 target=data.replace(old_h,new_h).replace(old_d,new_d)
 for fragment in ("<form","@csrf","route('register')"):
  if target.count(fragment)!=data.count(fragment):raise SystemExit("BLOCKED_REGISTER_FORM_CHANGE")
 print("REGISTER_STRATEGY=EXACT_TEXT_ONLY")
else:raise SystemExit("BLOCKED_PARTIAL_APPEARANCE_SLOTS")
(tmp/"strategy").write_text(mode)
(tmp/"new-register.blade.php").write_text(target)
print("REGISTER_VIEW_PREFLIGHT=PASS")
PY_BLADE
CHANGE_MODE="$(cat "$TMP/strategy")"
if [ "$CHANGE_MODE" = "appearance" ]; then
  db_action probe > "$TMP/appearance-before.json"
  echo "REGISTER_APPEARANCE_PREFLIGHT=PASS"
fi

echo "========== VERIFY CURRENT PUBLIC REGISTER COPY =========="
curl --noproxy '*' -fsS --connect-timeout 5 --max-time 20 \
  --resolve "$HOST:443:127.0.0.1" "https://$HOST/register" -o "$TMP/before.html"
python3 - "$TMP/before.html" <<'PY_BEFORE'
from html.parser import HTMLParser
import sys
class P(HTMLParser):
 def __init__(self):super().__init__();self.which=None;self.h="";self.d=""
 def handle_starttag(self,t,a):
  c=dict(a).get("class","").split()
  if t=="h1" and "gm-auth-title" in c:self.which="h"
  if t=="p" and "gm-auth-subtitle" in c:self.which="d"
 def handle_endtag(self,t):
  if (t=="h1" and self.which=="h") or (t=="p" and self.which=="d"):self.which=None
 def handle_data(self,s):
  if self.which=="h":self.h+=s
  if self.which=="d":self.d+=s
p=P();p.feed(open(sys.argv[1],encoding="utf8").read())
h=" ".join(p.h.split());d=" ".join(p.d.split())
if h not in ("Create your account","Create Account"):raise SystemExit("BLOCKED_VISIBLE_REGISTER_HEADING")
if d not in ("Join Gomovie and build your own movie and series watchlist.","Build your own movies and series watchlist."):raise SystemExit("BLOCKED_VISIBLE_REGISTER_DESCRIPTION")
print("REGISTER_PUBLIC_COPY=RECOGNIZED")
PY_BEFORE
if [ "$MODE" = "--check" ]; then
 echo "GOMOVIE_REGISTER_COPY_PREFLIGHT=PASS"
 echo "LIVE_FILES_CHANGED=NO"
 echo "DATABASE_CHANGED=NO"
 exit 0
fi

echo "========== BACKUP AND APPLY REGISTER COPY =========="
if [ "$CHANGE_MODE" = "blade" ]; then
 cp -p -- "$VIEW" "$TMP/register-before.blade.php"
 cmp -s "$VIEW" "$TMP/register-before.blade.php"
 CHANGED=1
 install -o root -g www -m 0644 "$TMP/new-register.blade.php" "$VIEW"
 cmp -s "$VIEW" "$TMP/new-register.blade.php"
 (cd "$APP" && runuser -u www -- "$PHP" artisan optimize:clear --no-interaction &&
  runuser -u www -- "$PHP" artisan view:cache --no-interaction)
else
 db_action probe > "$TMP/appearance-before.json"
 snapshot="$(base64 -w 0 "$TMP/appearance-before.json")"
 CHANGED=1
 db_action apply "$snapshot"
fi

echo "========== VERIFY NEW REGISTER COPY =========="
curl --noproxy '*' -fsS --connect-timeout 5 --max-time 20 \
  --resolve "$HOST:443:127.0.0.1" "https://$HOST/register" -o "$TMP/after.html"
python3 - "$TMP/after.html" <<'PY_AFTER'
from html.parser import HTMLParser
import sys
class P(HTMLParser):
 def __init__(self):super().__init__();self.which=None;self.h="";self.d=""
 def handle_starttag(self,t,a):
  c=dict(a).get("class","").split()
  if t=="h1" and "gm-auth-title" in c:self.which="h"
  if t=="p" and "gm-auth-subtitle" in c:self.which="d"
 def handle_endtag(self,t):
  if (t=="h1" and self.which=="h") or (t=="p" and self.which=="d"):self.which=None
 def handle_data(self,s):
  if self.which=="h":self.h+=s
  if self.which=="d":self.d+=s
p=P();p.feed(open(sys.argv[1],encoding="utf8").read())
h=" ".join(p.h.split());d=" ".join(p.d.split())
if h!="Create Account":raise SystemExit("BLOCKED_REGISTER_HEADING="+repr(h))
if d!="Build your own movies and series watchlist.":raise SystemExit("BLOCKED_REGISTER_DESCRIPTION="+repr(d))
print("REGISTER_HEADING=Create Account")
print("REGISTER_DESCRIPTION=Build your own movies and series watchlist.")
print("REGISTER_COPY_HTTP=PASS")
PY_AFTER
COMPLETE=1
echo "GOMOVIE_REGISTER_COPY_DEPLOYMENT=SUCCESS"
echo "OTHER_PAGES_AND_FORMS=UNCHANGED"
