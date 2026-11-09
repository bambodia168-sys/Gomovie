#!/usr/bin/env bash
# Read-only compatibility audit for Gomovie Upload Studio.
# Never runs migrations, installs files, clears caches, or reads .env contents.
set -Eeuo pipefail
umask 077

if [ "$#" -ne 1 ] || [ "$1" != "--check" ]; then
  echo 'Usage: bash upload-studio-live-audit.sh --check' >&2
  exit 2
fi
APP='/www/wwwroot/gomovie.tonsamith.com'
PHP='/www/server/php/85/bin/php'
SOURCE_COMMIT='8c418e8732cdaa6ad102907d75309ab5bc7e6906'

echo '========== GOMOVIE UPLOAD STUDIO READ-ONLY AUDIT =========='
echo "REFERENCE_GITHUB_COMMIT=$SOURCE_COMMIT"
test "$(id -u)" -eq 0
test -d "$APP" && test ! -L "$APP"
test -f "$APP/artisan"
test -f "$APP/vendor/autoload.php"
test -x "$PHP"
command -v git >/dev/null
command -v runuser >/dev/null
id www >/dev/null

match=0
mismatch=0
missing=0
while read -r expected relative; do
  if [ ! -f "$APP/$relative" ] || [ -L "$APP/$relative" ]; then
    echo "MISSING_OR_UNSAFE=$relative"
    missing=$((missing+1))
    continue
  fi
  actual="$(git hash-object -- "$APP/$relative")"
  if [ "$actual" = "$expected" ]; then
    echo "SOURCE_MATCH=$relative"
    match=$((match+1))
  else
    echo "SOURCE_DIFFERENT=$relative"
    echo "  LIVE_BLOB=$actual"
    echo "  REFERENCE_BLOB=$expected"
    mismatch=$((mismatch+1))
  fi
done <<'EXPECTED_SOURCES'
f1a61447e5f0e2679104cc8c84cf8cf121000943 routes/web.php
9b702c7e2a5bed3a2c156745c5583b92e86c7767 app/Http/Controllers/Admin/UploadStudioController.php
e6d3180ade2f1c48c324734baea643e798eef635 app/Http/Controllers/Admin/UploadStudioWorkflowController.php
02af15a694a8ad41dd055560705c10c23e914086 app/Http/Controllers/Admin/MediaController.php
78354f0927030675c82777bb22960e007f95a9bb resources/views/admin/upload-studio.blade.php
162d36ec7271711f1f2aaac7a97440a6e6974073 resources/views/admin/media.blade.php
0046013039f785afc7dbdfc2d2c3317256eb41bb app/Services/Media/PrivateVideoIntake.php
7f03a85b670765784d0e43812f86ccbb8a161169 app/Services/Media/IntakeReviewService.php
250acacab52ad664733938e828b61f8a7f828bc5 database/migrations/2026_11_09_000100_add_cloud_mirror_to_media_ingests.php
5f290c16b0c4f83d7946cd56abc5917873b89a1d config/filesystems.php
dd06feff774b2fe09d91681788ce6274eb7ccebb config/gomovie.php
EXPECTED_SOURCES

echo '========== SAFE RUNTIME CHECKS =========='
if runuser -u www -- test -w "$APP/storage/app/private"; then
  echo 'PRIVATE_STORAGE_WRITABLE=YES'
else
  echo 'PRIVATE_STORAGE_WRITABLE=NO'
fi

if routes="$(cd "$APP" && runuser -u www -- "$PHP" artisan route:list --path=upload-studio 2>/dev/null)"; then
  for route_name in upload-studio.index upload-studio.upload upload-studio.status upload-studio.details upload-studio.artwork upload-studio.access; do
    if printf '%s\n' "$routes" | grep -Fq "$route_name"; then
      echo "ROUTE_PRESENT=$route_name"
    else
      echo "ROUTE_MISSING=$route_name"
    fi
  done
else
  echo 'UPLOAD_ROUTES=UNAVAILABLE'
fi

if schema="$(cd "$APP" && runuser -u www -- "$PHP" -r '
  try {
      require "vendor/autoload.php";
      $app = require "bootstrap/app.php";
      $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
      if (!\Illuminate\Support\Facades\Schema::hasTable("media_ingests")) {
          echo "MEDIA_INGESTS_TABLE=MISSING\n";
          exit(0);
      }
      echo "MEDIA_INGESTS_TABLE=PRESENT\n";
      foreach (["cloud_disk", "cloud_object_key", "cloud_status", "cloud_mirrored_at"] as $name) {
          echo "CLOUD_COLUMN_".strtoupper($name)."=".
              (\Illuminate\Support\Facades\Schema::hasColumn("media_ingests", $name) ? "PRESENT" : "MISSING")."\n";
      }
  } catch (\Throwable $error) {
      echo "DATABASE_READ_ONLY_CHECK=FAILED\n";
      exit(4);
  }
' 2>/dev/null)"; then
  printf '%s\n' "$schema"
else
  printf '%s\n' "$schema"
fi

echo "SOURCE_MATCH_COUNT=$match"
echo "SOURCE_DIFFERENT_COUNT=$mismatch"
echo "SOURCE_MISSING_COUNT=$missing"
echo 'PRODUCTION_FILES_CHANGED=NO'
echo 'DATABASE_CHANGED=NO'
echo 'PRIVATE_VIDEOS_CHANGED=NO'
echo 'AUDIT=COMPLETED_READ_ONLY'
