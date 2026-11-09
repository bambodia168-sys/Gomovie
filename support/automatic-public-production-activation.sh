#!/usr/bin/env bash
# Gomovie: guarded automatic free-video publication activation/disable.
# No schema mutation, no catalog or rights edits, no uploaded-media changes.
# --apply: install only verified rights+HLS URL hotfix, set three flags true,
#          rebuild config and restart exactly the dedicated media service.
# --disable: set only the three flags false and restart the service.
set -Eeuo pipefail
umask 077

if [ "$#" -ne 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ] && [ "$1" != "--disable" ]; }; then
  echo 'Usage: bash automatic-public-production-activation.sh --check|--apply|--disable' >&2
  exit 2
fi
MODE="$1"
APP="/www/wwwroot/gomovie.tonsamith.com"
PHP="/www/server/php/85/bin/php"
REPO="bambodia168-sys/Gomovie"
SOURCE="48c08f7692d58a9686e0dd76f3b9819497f0d70b"
SERVICE="gomovie-media-worker.service"
TARGET="app/Services/Media/AutomaticPublicationService.php"
BLADE="resources/views/frontend/detail.blade.php"
ORIGINAL_BLOB="060b9b812e296c13edb0f0c9ee3d9b16957f2805"
FIXED_BLOB="1c81d54b958ccac348996cde3f6f5ac7c9aa5218"
CACHE="bootstrap/cache/config.php"
ENVFILE=".env"

echo "========== GOMOVIE GUARDED PRODUCTION ACTIVATION =========="
echo "MODE=$MODE"
test "$(id -u)" -eq 0
test -d "$APP" && test ! -L "$APP" && test "$(realpath -e "$APP")" = "$APP"
test -f "$APP/$ENVFILE" && test ! -L "$APP/$ENVFILE"
test -f "$APP/artisan" && test ! -L "$APP/artisan"
test -f "$APP/vendor/autoload.php" && test ! -L "$APP/vendor/autoload.php"
test -f "$APP/$TARGET" && test ! -L "$APP/$TARGET"
test -f "$APP/$BLADE" && test ! -L "$APP/$BLADE"
test -f "$APP/app/Jobs/AutoPublishUploadedVideo.php" && test ! -L "$APP/app/Jobs/AutoPublishUploadedVideo.php"
test "$(git hash-object "$APP/app/Jobs/AutoPublishUploadedVideo.php")" = 770e673ab4db60cf1553c7979fdb491259412c4e
test -x "$PHP"
test -f "$APP/public/build/manifest.json"
test ! -L "$APP/public/build/manifest.json"
test ! -e "$APP/public/hls"
for binary in git gh runuser systemctl curl python3 cp stat mktemp sha256sum cmp install; do command -v "$binary" >/dev/null; done
id www >/dev/null
systemctl is-enabled --quiet "$SERVICE"
systemctl is-active --quiet "$SERVICE"
if [ -e "$APP/$CACHE" ] || [ -L "$APP/$CACHE" ]; then
  test -f "$APP/$CACHE" && test ! -L "$APP/$CACHE"
fi

echo "========== CHECK PUBLIC ROUTE AND VERIFIED PRODUCTION FILES =========="
CURRENT_BLOB="$(git hash-object "$APP/$TARGET")"
test "$CURRENT_BLOB" = "$ORIGINAL_BLOB" || test "$CURRENT_BLOB" = "$FIXED_BLOB"
printf 'RIGHTS_SERVICE=%s\n' "$CURRENT_BLOB"

# Match only the exact one-line URL produced by Gomovie's public player.
python3 - "$APP/$BLADE" <<'PY'
import sys
from pathlib import Path
body=Path(sys.argv[1]).read_text()
old="route('public-hls.show', ['asset' => $publicAsset->id, 'path' => 'master.m3u8'])"
new="route('public-hls.show', ['asset' => $publicAsset->id, 'path' => 'master.m3u8'], false)"
if body.count(old)+body.count(new)!=1 or body.count('data-public-hls-url=')!=1:
    raise SystemExit('BLOCKED: unexpected customized public watch URL template')
print("PUBLIC_WATCH_URL_FIX_COMPATIBLE=YES")
PY

check_app() {
  (
    cd "$APP"
    runuser -u www -- "$PHP" -r '
      require "vendor/autoload.php";
      $app=require "bootstrap/app.php";
      $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
      $check=["automatic_publication_enabled","public_hls_enabled","transcoding_enabled"];
      foreach($check as $key) echo strtoupper($key)."=".
          (config("gomovie.".$key)===true?"ON":"OFF")."\n";
      if(config("gomovie.media_queue_connection")!=="database_media"
         || (int)config("queue.connections.database_media.retry_after")<=7500
         || config("gomovie.billing_enabled")===true) exit(14);
      foreach(["jobs","media_ingests","content_rights","media_assets","public_media_files"] as $name)
        if(!\Illuminate\Support\Facades\Schema::hasTable($name)) exit(15);
      $queue=(int)\Illuminate\Support\Facades\DB::table("jobs")
         ->where("queue","gomovie-media")->count();
      $processing=(int)\Illuminate\Support\Facades\DB::table("media_ingests")
         ->where("status","processing")->count();
      $existing=(int)\Illuminate\Support\Facades\DB::table("media_assets")
         ->where("status","ready")->where("storage_disk","local")
         ->where("format","hls")->count();
      echo "MEDIA_QUEUE_PENDING=".$queue."\n";
      echo "ACTIVE_ENCODINGS=".$processing."\n";
      echo "PREEXISTING_READY_PUBLIC_CANDIDATES=".$existing."\n";
      if($argv[1]!=="--disable" && ($queue!==0||$processing!==0||$existing!==0)) {
          fwrite(STDERR,"BLOCKED: inspect existing work/assets before global activation.\n"); exit(16);
      }
      if($argv[1]==="--apply") {
          foreach(["automatic_publication_enabled","public_hls_enabled","transcoding_enabled"] as $name)
              if(config("gomovie.".$name)===true) exit(19);
      }
    ' "$MODE"
  )
}
check_app

if [ "$MODE" != --disable ]; then
  echo "========== VERIFY REAL MEDIA TOOLS =========="
  (
    cd "$APP"
    runuser -u www -- "$PHP" -r '
      require "vendor/autoload.php";
      $app=require "bootstrap/app.php";
      $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
      foreach(["ffmpeg_binary","ffprobe_binary","malware_scanner_binary"] as $key){
        $file=(string)config("gomovie.".$key);
        if(!str_starts_with($file,"/")||!is_file($file)||!is_executable($file)) exit(17);
        if($key==="malware_scanner_binary"&&basename($file)!=="clamscan") exit(18);
        echo strtoupper($key)."=EXECUTABLE\n";
      }
    '
  )
fi

echo "========== VALIDATE FLAG COMPATIBILITY WITHOUT EXPOSING ENV SECRETS =========="
python3 - "$APP/$ENVFILE" "$MODE" <<'PY'
from pathlib import Path
import re,sys
source=Path(sys.argv[1]).read_text()
mode=sys.argv[2]
flags=("GOMOVIE_AUTOMATIC_PUBLICATION_ENABLED","GOMOVIE_PUBLIC_HLS_ENABLED","GOMOVIE_TRANSCODING_ENABLED")
for key in flags:
    matches=re.findall(r"(?m)^[ \t]*"+re.escape(key)+r"[ \t]*=[^\r\n]*$",source)
    if len(matches)>1: raise SystemExit("BLOCKED: duplicate flag "+key)
    if matches:
        value=matches[0].split("=",1)[1].strip().strip("'\"").lower()
        if value not in ("false","0","off","true","1","on"):
            raise SystemExit("BLOCKED: unknown flag value "+key)
        if mode=="--apply" and value not in ("false","0","off"):
            raise SystemExit("BLOCKED: feature flag already on "+key)
        if mode=="--check" and value not in ("false","0","off"):
            raise SystemExit("BLOCKED: feature flag unexpectedly on "+key)
print("ENV_FLAG_FORMAT=VERIFIED")
PY

if [ "$MODE" = --check ]; then
  test "$CURRENT_BLOB" = "$ORIGINAL_BLOB" || test "$CURRENT_BLOB" = "$FIXED_BLOB"
  echo "PRODUCTION_ACTIVATION_PREFLIGHT=PASS"
  echo "LIVE_FILES_CHANGED=NO"
  echo "PUBLICATION_ACTIVATED=NO"
  exit 0
fi

echo "========== SNAPSHOT PRODUCTION CONFIGURATION =========="
BACKUP="$(mktemp -d /root/Gomovie-Production-Auto-Activation-XXXXXXXX)"
chmod 0700 "$BACKUP"
mkdir -p "$BACKUP/original" "$BACKUP/verified"
cp -p "$APP/$ENVFILE" "$BACKUP/original/dot-env"
cp -p "$APP/$TARGET" "$BACKUP/original/service.php"
cp -p "$APP/$BLADE" "$BACKUP/original/detail.blade.php"
if [ -f "$APP/$CACHE" ]; then
  cp -p "$APP/$CACHE" "$BACKUP/original/config.php"
fi
echo "PRODUCTION_ROLLBACK_BACKUP=$BACKUP"
CHANGED=0
COMPLETE=0
restore_on_failure() {
  rc=$?
  if [ "$CHANGED" -eq 1 ] && [ "$COMPLETE" -ne 1 ]; then
    set +e
    echo "========== ROLLBACK UNSUCCESSFUL ACTIVATION =========="
    cp -p "$BACKUP/original/dot-env" "$APP/$ENVFILE"
    cp -p "$BACKUP/original/service.php" "$APP/$TARGET"
    cp -p "$BACKUP/original/detail.blade.php" "$APP/$BLADE"
    if [ -f "$BACKUP/original/config.php" ]; then
      cp -p "$BACKUP/original/config.php" "$APP/$CACHE"
    else
      rm -f -- "$APP/$CACHE"
    fi
    (cd "$APP" && runuser -u www -- "$PHP" artisan view:clear --no-interaction) >/dev/null 2>&1
    systemctl restart "$SERVICE"
    echo "PRODUCTION_ACTIVATION_ROLLBACK=ATTEMPTED"
    echo "BACKUP_FOR_MANUAL_RECOVERY=$BACKUP"
  fi
  exit "$rc"
}
trap restore_on_failure EXIT

if [ "$MODE" = --apply ]; then
  echo "========== DOWNLOAD EXACT RIGHTS HARDENING =========="
  gh api -H "Accept: application/vnd.github.raw+json" \
    "repos/$REPO/contents/$TARGET?ref=$SOURCE" \
    > "$BACKUP/verified/service.php"
  test "$(git hash-object "$BACKUP/verified/service.php")" = "$FIXED_BLOB"
  "$PHP" -l "$BACKUP/verified/service.php" >/dev/null

  echo "========== VERIFY HLS PLAYER ASSETS =========="
  # Public player JS has shipped on the host; the fix makes the rendered URL
  # relative so its same-origin HLS source check succeeds.
  test -f "$APP/resources/js/player.js"
  grep -Fq "publicHlsUrl.startsWith('/hls/')" "$APP/resources/js/player.js"
  grep -RlF 'publicHlsUrl' "$APP/public/build/assets" --include='*.js' >/dev/null

  echo "========== PATCH ONLY KNOWN PLAYER URL AND RIGHTS SERVICE =========="
  CHANGED=1
  install -o root -g www -m 0644 "$BACKUP/verified/service.php" "$APP/$TARGET"
  python3 - "$APP/$BLADE" <<'PY'
from pathlib import Path
import os,sys,tempfile
p=Path(sys.argv[1])
old="route('public-hls.show', ['asset' => $publicAsset->id, 'path' => 'master.m3u8'])"
new="route('public-hls.show', ['asset' => $publicAsset->id, 'path' => 'master.m3u8'], false)"
text=p.read_text()
if text.count(old)==1 and text.count(new)==0:
    info=p.stat()
    fd,tmp=tempfile.mkstemp(prefix=".gomovie-detail.",dir=str(p.parent))
    try:
        os.fchmod(fd,info.st_mode&0o777)
        os.fchown(fd,info.st_uid,info.st_gid)
        with os.fdopen(fd,"w") as stream:stream.write(text.replace(old,new))
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
elif text.count(old)==0 and text.count(new)==1:
    pass
else:
    raise SystemExit("BLOCKED: unexpected player URL template before activation")
PY
  test "$(git hash-object "$APP/$TARGET")" = "$FIXED_BLOB"
fi

echo "========== UPDATE ONLY THREE ACTIVATION FLAGS =========="
python3 - "$APP/$ENVFILE" "$MODE" <<'PY'
from pathlib import Path
import os,re,sys,tempfile
p=Path(sys.argv[1]);mode=sys.argv[2]
source=p.read_text()
flags=("GOMOVIE_AUTOMATIC_PUBLICATION_ENABLED","GOMOVIE_PUBLIC_HLS_ENABLED","GOMOVIE_TRANSCODING_ENABLED")
value="true" if mode=="--apply" else "false"
new=source
for key in flags:
    pattern=r"(?m)^[ \t]*"+re.escape(key)+r"[ \t]*=[^\r\n]*$"
    if len(re.findall(pattern,new))>1:raise SystemExit("Duplicate flag")
    if re.search(pattern,new):
        new=re.sub(pattern,key+"="+value,new)
    else:
        new+=("" if new.endswith("\n") else "\n")+key+"="+value+"\n"
stat=p.stat()
fd,tmp=tempfile.mkstemp(prefix=".gomovie-activation.",dir=str(p.parent))
try:
    os.fchmod(fd,stat.st_mode&0o777)
    os.fchown(fd,stat.st_uid,stat.st_gid)
    with os.fdopen(fd,"w") as output:
        output.write(new)
        output.flush()
        os.fsync(output.fileno())
    os.replace(tmp,p)
finally:
    if os.path.exists(tmp):os.unlink(tmp)
PY
CHANGED=1

echo "========== REBUILD LARAVEL CONFIG AND RELOAD ISOLATED WORKER =========="
(
  cd "$APP"
  runuser -u www -- "$PHP" artisan config:cache --no-interaction
  runuser -u www -- "$PHP" artisan view:cache --no-interaction
)
systemctl restart "$SERVICE"
systemctl is-active --quiet "$SERVICE"
systemctl is-enabled --quiet "$SERVICE"

(
  cd "$APP"
  runuser -u www -- "$PHP" -r '
    require "vendor/autoload.php";
    $app=require "bootstrap/app.php";
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $expected=$argv[1]==="--apply";
    foreach(["automatic_publication_enabled","public_hls_enabled","transcoding_enabled"] as $name) {
      if((config("gomovie.".$name)===true)!==$expected) {
        fwrite(STDERR,"Feature config did not reach the new state: ".$name."\n");exit(22);
      }
    }
    echo $expected?"PRODUCTION_PUBLICATION_SWITCHES=ENABLED\n":"PRODUCTION_PUBLICATION_SWITCHES=DISABLED\n";
  ' "$MODE"
)

echo "========== VERIFY PUBLIC ROUTES WITHOUT EXPOSING EXISTING MEDIA =========="
for url in / /browse /login; do
  result="$(curl --noproxy '*' -ksS --connect-timeout 5 --max-time 20 \
    --resolve gomovie.tonsamith.com:443:127.0.0.1 \
    -o /dev/null -w '%{http_code}' "https://gomovie.tonsamith.com$url")"
  echo "ROUTE=$url HTTP=$result"
  test "$result" = 200
done
result="$(curl --noproxy '*' -ksS --connect-timeout 5 --max-time 20 \
  --resolve gomovie.tonsamith.com:443:127.0.0.1 \
  -o /dev/null -w '%{http_code}' \
  "https://gomovie.tonsamith.com/hls/999999999/master.m3u8")"
test "$result" = 404
echo "UNKNOWN_PUBLIC_HLS=404"

COMPLETE=1
echo "MEDIA_WORKER=ACTIVE"
echo "EXISTING_VIDEO_OR_RIGHTS_CHANGED=NO"
echo "DATABASE_SCHEMA_CHANGED=NO"
echo "ROLLBACK_SNAPSHOT=$BACKUP"
if [ "$MODE" = --apply ]; then
  echo "PRODUCTION_AUTOMATIC_PUBLICATION_ACTIVATION=SUCCESS"
  echo "BROWSER_VIDEO_UPLOAD_ACCEPTANCE=STILL_REQUIRED"
else
  echo "PRODUCTION_AUTOMATIC_PUBLICATION_DISABLED=SUCCESS"
fi
