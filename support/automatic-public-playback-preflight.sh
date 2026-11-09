#!/usr/bin/env bash
# Gomovie automatic public playback: read-only host readiness and source audit.
# No writes, migrations, queue dispatches, config changes, uploads, or publication.
set -Eeuo pipefail
umask 077

if [ "$#" -ne 1 ] || [ "$1" != "--check" ]; then
  echo "Usage: bash automatic-public-playback-preflight.sh --check" >&2
  exit 2
fi

APP="/www/wwwroot/gomovie.tonsamith.com"
PHP="/www/server/php/85/bin/php"
HOST="gomovie.tonsamith.com"

echo "========== GOMOVIE AUTOMATIC PUBLIC PLAYBACK READ-ONLY AUDIT =========="
test "$(id -u)" -eq 0
test -d "$APP" && test ! -L "$APP"
test -f "$APP/artisan"
test -f "$APP/vendor/autoload.php" && test ! -L "$APP/vendor/autoload.php"
test -f "$APP/.env" && test ! -L "$APP/.env"
test -x "$PHP"
command -v git >/dev/null
command -v runuser >/dev/null
id www >/dev/null

echo "========== COMPARE LIVE SOURCE WITH NEW AUTOMATIC PUBLISHING =========="
# Hashes are the GitHub state just before the new milestone; a different live
# file is not assumed safe for overwrite or silently treated as missing.
while read -r previous relative; do
  if [ ! -f "$APP/$relative" ] || [ -L "$APP/$relative" ]; then
    if [ "$previous" = NEW ] && [ ! -e "$APP/$relative" ] && [ ! -L "$APP/$relative" ]; then
      echo "NEW_FILE_NOT_INSTALLED=$relative"
    else
      echo "FILE_UNAVAILABLE_OR_UNSAFE=$relative"
    fi
    continue
  fi
  current="$(git hash-object -- "$APP/$relative")"
  if [ "$previous" = NEW ]; then
    echo "NEW_FILE_ALREADY_PRESENT=$relative BLOB=$current"
  elif [ "$current" = "$previous" ]; then
    echo "BASELINE_MATCH=$relative"
  else
    echo "LIVE_CUSTOM_FILE=$relative BLOB=$current"
  fi
done <<'SOURCES'
NEW app/Http/Controllers/Frontend/PublicHlsController.php
NEW app/Services/Media/AutomaticPublicationService.php
NEW app/Jobs/AutoPublishUploadedVideo.php
NEW database/migrations/2026_11_09_000200_create_public_media_files.php
b0424d8b80c33eb63de1adcdec4e37c6d99240c0 app/Services/Media/MediaPackageInspector.php
0046013039f785afc7dbdfc2d2c3317256eb41bb app/Services/Media/PrivateVideoIntake.php
dd06feff774b2fe09d91681788ce6274eb7ccebb config/gomovie.php
f1a61447e5f0e2679104cc8c84cf8cf121000943 routes/web.php
a31f97e68b4d2a2392604aa6fe1dc5b352ff2ed9 app/Http/Controllers/Frontend/BrowseController.php
dde44b7f1284dbe8794fd1c7573606731710ea9e resources/js/player.js
96f299fec273360799d094f61e435eeb73d395ea resources/views/frontend/detail.blade.php
e6d3180ade2f1c48c324734baea643e798eef635 app/Http/Controllers/Admin/UploadStudioWorkflowController.php
d7ad7165e6ca85e76000fb9382a7aae90d67e5e3 app/Http/Controllers/Admin/UploadStudioController.php
dfec39a9af4b7f5812d3c090473d3322c964e614 resources/views/admin/upload-studio.blade.php
SOURCES

echo "========== READ-ONLY HOST ENVIRONMENT =========="
if [ -d "$APP/storage/app/private" ] && runuser -u www -- test -w "$APP/storage/app/private"; then
  echo 'PRIVATE_MEDIA_STORAGE=WRITABLE'
else
  echo 'PRIVATE_MEDIA_STORAGE=BLOCKED'
fi
if [ -f "$APP/public/build/manifest.json" ] && [ ! -L "$APP/public/build/manifest.json" ]; then
  echo 'VITE_PRODUCTION_MANIFEST=PRESENT'
else
  echo 'VITE_PRODUCTION_MANIFEST=MISSING'
fi
if command -v ps >/dev/null; then
  if ps -eo args= | grep -E '[a]rtisan queue:work (database_media|redis_media)' >/dev/null; then
    echo 'MEDIA_QUEUE_WORKER=DETECTED'
  else
    echo 'MEDIA_QUEUE_WORKER=NOT_DETECTED'
  fi
fi

echo "========== LARAVEL CONFIGURATION AND DOCUMENTED FREE RIGHTS =========="
(
  cd "$APP"
  runuser -u www -- "$PHP" -r '
    try {
        require "vendor/autoload.php";
        $app = require "bootstrap/app.php";
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $checks = [
            "PUBLIC_HLS_ENABLED" => config("gomovie.public_hls_enabled") === true,
            "AUTOMATIC_PUBLICATION_ENABLED" => config("gomovie.automatic_publication_enabled") === true,
            "TRANSCODING_ENABLED" => config("gomovie.transcoding_enabled") === true,
        ];
        foreach ($checks as $name => $enabled) {
            echo $name."=".($enabled ? "YES" : "NO")."\n";
        }
        foreach (["ffmpeg_binary"=>"FFMPEG", "ffprobe_binary"=>"FFPROBE",
                  "malware_scanner_binary"=>"CLAMSCAN"] as $key => $name) {
            $path = (string)config("gomovie.".$key);
            $safe = str_starts_with($path, "/") && is_file($path) && is_executable($path);
            if ($key === "malware_scanner_binary") $safe = $safe && basename($path) === "clamscan";
            echo $name."=".($safe ? "EXECUTABLE" : "NOT_READY")."\n";
        }
        $connection = (string)config("gomovie.media_queue_connection");
        echo "MEDIA_QUEUE_CONNECTION=".
            (in_array($connection, ["database_media","redis_media"], true) ? "ISOLATED" : "NOT_ISOLATED")."\n";
        echo "PUBLIC_MEDIA_FILES_TABLE=".
            (\Illuminate\Support\Facades\Schema::hasTable("public_media_files") ? "PRESENT" : "NOT_INSTALLED")."\n";

        $movieCount = \Illuminate\Support\Facades\DB::table("movies as movies")
            ->where("movies.is_premium", false)
            ->whereIn("movies.status", ["draft", "published"])
            ->whereExists(function ($q) {
                $q->selectRaw("1")->from("content_rights as rights")
                    ->where("rights.licensable_type", "movie")
                    ->whereColumn("rights.licensable_id", "movies.id")
                    ->where("rights.status", "active")
                    ->whereNull("rights.territories")
                    ->whereNotNull("rights.rights_reference")
                    ->where("rights.rights_reference", "!=", "")
                    ->where(function($q) { $q->whereNull("rights.starts_at")->orWhere("rights.starts_at", "<=", now()); })
                    ->where(function($q) { $q->whereNull("rights.ends_at")->orWhere("rights.ends_at", ">", now()); });
            })->count();
        echo "DOCUMENTED_ELIGIBLE_FREE_MOVIES=".$movieCount."\n";
    } catch (\Throwable $error) {
        echo "LARAVEL_READ_ONLY_CHECK=FAILED\n";
        exit(5);
    }
  '
)

echo "========== FINISHED (NO CHANGES MADE) =========="
echo "PRODUCTION_SOURCE_CHANGED=NO"
echo "DATABASE_CHANGED=NO"
echo "MEDIA_CHANGED=NO"
echo "PUBLICATION_ACTIVATED=NO"
echo "GOMOVIE_AUTO_PUBLIC_PREFLIGHT=COMPLETE"
