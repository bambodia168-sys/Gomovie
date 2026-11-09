#!/usr/bin/env bash
# Replaces the inaccessible browser-QA sample with an original ffmpeg clip made
# on the customer's OWN aaPanel server; updates only QA movie 23's evidence.
# Application files, production flags, other movies and uploads stay unchanged.
set -Eeuo pipefail
umask 077

if [ "$#" -ne 1 ] || { [ "$1" != --check ] && [ "$1" != --apply ]; }; then
  echo "Usage: bash browser-qa-local-video-recovery.sh --check|--apply" >&2; exit 2
fi
MODE="$1"
APP=/www/wwwroot/gomovie.tonsamith.com
DIR=/var/lib/gomovie-acceptance
QA="$DIR/browser-public-qa-fixture.php"
VIDEO="$DIR/Gomovie_Original_QA_Video.mp4"
PHP=/www/server/php/85/bin/php
OLD_SHA=c510082cf911cce99efafceb96466d87b6a750653fe11385372bb93a588e8cf5
OLD_REF=GOMOVIE-ORIGINAL-FFMPEG-QA-c510082cf911cce9

echo "========== AAPANEL ORIGINAL QA VIDEO RECOVERY =========="
echo "MODE=$MODE"
test "$(id -u)" -eq 0
test -d "$APP" && test ! -L "$APP"
test -d "$DIR" && test ! -L "$DIR"
test -f "$QA" && test ! -L "$QA"
test -f "$APP/vendor/autoload.php"
test -x "$PHP" && test -x /usr/bin/ffmpeg && test -x /usr/bin/ffprobe
test ! -e "$VIDEO" && test ! -L "$VIDEO"
for x in runuser git grep mktemp python3 cp mv sha256sum; do command -v "$x" >/dev/null; done
"$PHP" -l "$QA" >/dev/null
grep -Fqx "const QA_SHA='$OLD_SHA';" "$QA"
grep -Fqx "const QA_REF='$OLD_REF';" "$QA"

echo "========== GUARDED MOVIE 23 AND RIGHTS CHECK =========="
(
 cd "$APP"
 runuser -u www -- "$PHP" -r '
  require "vendor/autoload.php";
  $app=require "bootstrap/app.php";
  $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
  try {
   foreach(["automatic_publication_enabled","public_hls_enabled","transcoding_enabled"] as $flag)
    if(config("gomovie.".$flag)!==true) throw new RuntimeException("Production flag is off: ".$flag);
   $db=\Illuminate\Support\Facades\DB::class;
   if(!$db::table("movies")->where("id",23)->where("slug","gomovie-browser-qa-c510082cf911")
        ->where("status","draft")->where("is_premium",false)->exists())
     throw new RuntimeException("QA movie changed or missing");
   if($db::table("media_ingests")->where("ingestable_type","movie")
        ->where("ingestable_id",23)->count()!==0)
     throw new RuntimeException("QA movie already has an upload");
   if($db::table("content_rights")->where("licensable_type","movie")
       ->where("licensable_id",23)
       ->where("rights_reference","GOMOVIE-ORIGINAL-FFMPEG-QA-c510082cf911cce9")
       ->where("status","active")->whereNull("territories")->count()!==1)
     throw new RuntimeException("QA rights altered or ambiguous");
   echo "BROWSER_QA_MOVIE_ID=23\nBROWSER_QA_UPLOAD_COUNT=0\nQA_RIGHTS=VERIFIED\n";
  } catch (Throwable $e) {fwrite(STDERR,$e->getMessage()."\n");exit(31);}
 '
)
if [ "$MODE" = --check ]; then
  echo "BROWSER_QA_SERVER_VIDEO_PREFLIGHT=PASS"
  echo "DATABASE_AND_MEDIA_CHANGED=NO"
  exit 0
fi

echo "========== GENERATE SELF-OWNED TEST MP4 =========="
NEW_VIDEO="$(mktemp "$DIR/.gomovie-original.XXXXXXXX.mp4")"
NEW_QA="$(mktemp "$DIR/.gomovie-fixture.XXXXXXXX.php")"
BACKUP="$(mktemp /root/gomovie-original-browser-fixture.XXXXXXXX.php)"
cp -p "$QA" "$BACKUP"
DB_UPDATED=0
FILE_UPDATED=0
COMPLETE=0
NEW_REF=""
cleanup() {
  rc=$?
  set +e
  if [ "$COMPLETE" -ne 1 ]; then
    if [ "$FILE_UPDATED" -eq 1 ]; then cp -p "$BACKUP" "$QA"; fi
    if [ "$DB_UPDATED" -eq 1 ]; then
      (cd "$APP" && runuser -u www -- "$PHP" -r '
        require "vendor/autoload.php";
        $app=require "bootstrap/app.php";
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        \Illuminate\Support\Facades\DB::table("content_rights")
         ->where("licensable_type","movie")->where("licensable_id",23)
         ->where("rights_reference",$argv[1])
         ->update(["rights_reference"=>"GOMOVIE-ORIGINAL-FFMPEG-QA-c510082cf911cce9","updated_at"=>now()]);
      ' -- "$NEW_REF")
    fi
    echo "BROWSER_QA_SERVER_VIDEO_RECOVERY=FAILED"
    echo "RECOVERY_BACKUP=$BACKUP"
  fi
  rm -f -- "$NEW_VIDEO" "$NEW_QA"
  exit "$rc"
}
trap cleanup EXIT

/usr/bin/ffmpeg -hide_banner -loglevel error -nostdin -y \
  -f lavfi -i "testsrc2=duration=5:size=854x480:rate=24" \
  -f lavfi -i "sine=frequency=440:sample_rate=44100:duration=5" \
  -map 0:v:0 -map 1:a:0 -c:v libx264 -preset ultrafast \
  -crf 23 -pix_fmt yuv420p -c:a aac -b:a 64k -shortest \
  -movflags +faststart -f mp4 "$NEW_VIDEO"
test -s "$NEW_VIDEO"
test "$(stat -c %s "$NEW_VIDEO")" -lt 4194304
test "$(/usr/bin/ffprobe -v error -select_streams v:0 -show_entries stream=codec_name -of default=nw=1:nk=1 "$NEW_VIDEO")" = h264
test "$(/usr/bin/ffprobe -v error -select_streams a:0 -show_entries stream=codec_name -of default=nw=1:nk=1 "$NEW_VIDEO")" = aac

NEW_SHA="$(sha256sum "$NEW_VIDEO" | cut -d ' ' -f1)"
[[ "$NEW_SHA" =~ ^[0-9a-f]{64}$ ]]
PREFIX="$(printf '%.16s' "$NEW_SHA")"
NEW_REF="GOMOVIE-ORIGINAL-FFMPEG-QA-$PREFIX"

echo "========== BIND QA VERIFICATION TO NEW CLIP =========="
python3 - "$QA" "$NEW_QA" "$OLD_SHA" "$NEW_SHA" "$OLD_REF" "$NEW_REF" <<'PY'
from pathlib import Path
import sys
src,dest,old_sha,new_sha,old_ref,new_ref=sys.argv[1:]
body=Path(src).read_text()
s=f"const QA_SHA='{old_sha}';"
r=f"const QA_REF='{old_ref}';"
if body.count(s)!=1 or body.count(r)!=1: raise SystemExit("Fixture changed unexpectedly")
Path(dest).write_text(body.replace(s,f"const QA_SHA='{new_sha}';").replace(r,f"const QA_REF='{new_ref}';"))
PY
"$PHP" -l "$NEW_QA" >/dev/null
chown www:www "$NEW_QA"
chmod 0640 "$NEW_QA"

echo "========== UPDATE ONLY DOCUMENTED QA RIGHTS =========="
(
 cd "$APP"
 runuser -u www -- "$PHP" -r '
  require "vendor/autoload.php";
  $app=require "bootstrap/app.php";
  $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
  try {
   \Illuminate\Support\Facades\DB::transaction(function() use($argv) {
    if(\Illuminate\Support\Facades\DB::table("media_ingests")
      ->where("ingestable_type","movie")->where("ingestable_id",23)->exists())
      throw new RuntimeException("Unexpected concurrent QA upload");
    $count=\Illuminate\Support\Facades\DB::table("content_rights")
      ->where("licensable_type","movie")->where("licensable_id",23)
      ->where("rights_reference","GOMOVIE-ORIGINAL-FFMPEG-QA-c510082cf911cce9")
      ->where("status","active")->whereNull("territories")
      ->update(["rights_reference"=>$argv[1],"ends_at"=>now()->addDay(),"updated_at"=>now()]);
    if($count!==1)throw new RuntimeException("QA rights update was not unique");
   });
   echo "QA_RIGHTS_REFRESHED_FOR_NEW_ORIGINAL=YES\n";
  } catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(51);}
 ' -- "$NEW_REF"
)
DB_UPDATED=1
mv -f -- "$NEW_QA" "$QA"
FILE_UPDATED=1
chown www:www "$NEW_VIDEO"
chmod 0640 "$NEW_VIDEO"
mv -n -- "$NEW_VIDEO" "$VIDEO"
test -f "$VIDEO" && test ! -L "$VIDEO"
test "$(sha256sum "$VIDEO" | cut -d ' ' -f1)" = "$NEW_SHA"
"$PHP" -l "$QA" >/dev/null

echo "========== FINAL READ-ONLY STATUS =========="
(cd "$APP" && runuser -u www -- "$PHP" "$QA" --status)
echo "SERVER_VIDEO_PATH=$VIDEO"
echo "SHA256=$NEW_SHA"
echo "DOWNLOAD_WITH=AAPANEL_FILE_MANAGER"
echo "RIGHTS_EXPIRY=24_HOURS"
echo "BROWSER_QA_SERVER_VIDEO_RECOVERY=SUCCESS"
COMPLETE=1
