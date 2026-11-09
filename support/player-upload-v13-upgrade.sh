#!/usr/bin/env bash
# Gomovie player and upload milestone v1.3: portrait UI, real quality/captions, private CRUD.
# 15 checksum-pinned files; additive caption table; verified MySQL backup; rollback-safe.
# Existing media, public distribution and billing stay untouched. Only captions table is created.
set -Eeuo pipefail
umask 077

if [ "$#" -ne 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
  echo "Usage: bash player-upload-v13-upgrade.sh --check|--apply"
  exit 2
fi
MODE="$1"
REPO="bambodia168-sys/Gomovie"
TESTED_SHA="c76af06e7f16b9ffbac8e21a4ee94a00fe23afd4"
SOURCE_SHA="430d4ec9b5ec8ac374ca576100448441d4d09022"
BUILD_RUN="42637837627"
BUILD_NAME="gomovie-installer-qa-package"
HOST="gomovie.tonsamith.com"
APP="/www/wwwroot/$HOST"
PHP="/www/server/php/85/bin/php"

files() {
  printf '%s\n' \
    resources/js/player.js \
    resources/css/app.css \
    resources/views/frontend/partials/modern-player-controls.blade.php \
    resources/views/frontend/detail.blade.php \
    resources/views/admin/upload-studio.blade.php \
    resources/views/admin/player-settings.blade.php \
    app/Http/Controllers/Admin/UploadStudioWorkflowController.php \
    app/Http/Controllers/Admin/PlayerSettingsController.php \
    app/Http/Controllers/Frontend/BrowseController.php \
    app/Support/PlayerPreferences.php \
    app/Services/Media/TranscodingService.php \
    config/gomovie.php \
    routes/web.php \
    app/Http/Controllers/Admin/UploadCaptionController.php \
    database/migrations/2026_11_09_000300_create_ingest_captions_table.php
}
old_blob() {
  case "$1" in
    resources/js/player.js) echo d9beae59872ffd171e69212c856b559c08b1300a;;
    resources/css/app.css) echo 6dcd7cc8a5a7d51ec10130ff42f981ed71e79b73;;
    resources/views/frontend/partials/modern-player-controls.blade.php) echo a3da95ce141e77ef77a951ead7c1d896aec7b68d;;
    resources/views/frontend/detail.blade.php) echo d49cdcc3ad8c602c0f4f8e676a71c276d6cccd79;;
    resources/views/admin/upload-studio.blade.php) echo 5a1c362229cda510141d5abae2bf1b93c1d58752;;
    resources/views/admin/player-settings.blade.php) echo 49b4b88b5029b6476fc7bc3d1ea18532e7022922;;
    app/Http/Controllers/Admin/UploadStudioWorkflowController.php) echo 7e5ea09359dbc39cae3b740108183b5c3427fb0d;;
    app/Http/Controllers/Admin/PlayerSettingsController.php) echo 92bc98ced49925a7d63fc8bf951173c6d66f3174;;
    app/Http/Controllers/Frontend/BrowseController.php) echo 4b0bd4708cec3e33974c7b465a1abebe2c811644;;
    app/Support/PlayerPreferences.php) echo 88749cb111c419703b080296d0b4f06493509bcc;;
    app/Services/Media/TranscodingService.php) echo 41e4d0bf387f900c5fc3d36831b15abad9e76423;;
    config/gomovie.php) echo 544ed0605159d1d9a31ee684b83035c3b38a9829;;
    routes/web.php) echo 7c281ecc371244edc1b2758ec5ac958de64ecd64;;
    *) echo NOT_INSTALLED;;
  esac
}
new_blob() {
  case "$1" in
    resources/js/player.js) echo 1d0f9422f517aefed401115d30e43a523b3803f8;;
    resources/css/app.css) echo d8a3d94a51e449ee1d6b0bc1f08bf0032da1c857;;
    resources/views/frontend/partials/modern-player-controls.blade.php) echo ab4bac40840fdce12c5a81114977506174db76c2;;
    resources/views/frontend/detail.blade.php) echo 980629d232e6ad547ec6b35bd938417b520306b2;;
    resources/views/admin/upload-studio.blade.php) echo 8354b5f055964ca1f64d1818997131ac74e6ec87;;
    resources/views/admin/player-settings.blade.php) echo 1e089be3e8e991029263af21628ac67a39d4aaf3;;
    app/Http/Controllers/Admin/UploadStudioWorkflowController.php) echo 280d22aa29229448385d9f02e35fbda022d3db6c;;
    app/Http/Controllers/Admin/PlayerSettingsController.php) echo c3f5f0dd7150cfed0109f38c2c5be2b762537533;;
    app/Http/Controllers/Frontend/BrowseController.php) echo 383a345a869604b691d606f6d1381a20ba7ab7d8;;
    app/Support/PlayerPreferences.php) echo 45dd15098339ee468b3984e3bada36f748f7f2a3;;
    app/Services/Media/TranscodingService.php) echo 1be3afbe5c7564d1b3ce10b0cc938d2cf13ee893;;
    config/gomovie.php) echo 899a8f36fba432586205f01ceaafc68212b7e48f;;
    routes/web.php) echo ea9aae9fb49d323556f3cffdd074ed285fca7377;;
    app/Http/Controllers/Admin/UploadCaptionController.php) echo 65fa354e4fba4938f1cc83c505ef03ed647cd41f;;
    database/migrations/2026_11_09_000300_create_ingest_captions_table.php) echo 125dca4e69430045248502e3e2591e15458c3938;;
    *) return 1;;
  esac
}
echo "========== GOMOVIE PLAYER UPLOAD V1.3 =========="
echo "MODE=$MODE"
test "$(id -u)" -eq 0
test -d "$APP" && test ! -L "$APP" && test "$(realpath -e "$APP")" = "$APP"
test -f "$APP/artisan" && test -f "$APP/vendor/autoload.php"
test -f "$APP/.env" && test ! -L "$APP/.env"
test -f "$APP/storage/app/private/.gomovie-installed"
test -f "$APP/public/build/manifest.json" && test ! -L "$APP/public/build/manifest.json"
test -x "$PHP"
id www >/dev/null
for tool in gh git curl unzip sha256sum runuser cmp; do
  command -v "$tool" >/dev/null
done

echo "========== READ-ONLY LIVE SOURCE PREFLIGHT =========="
while IFS= read -r file; do
  if [ "$(old_blob "$file")" = "NOT_INSTALLED" ] && [ ! -e "$APP/$file" ] && [ ! -L "$APP/$file" ]; then
    echo "NEW_SOURCE_NOT_INSTALLED=$file"
    continue
  fi
  test -f "$APP/$file" && test ! -L "$APP/$file"
  actual="$(git hash-object "$APP/$file")"
  if [ "$actual" != "$(old_blob "$file")" ] && [ "$actual" != "$(new_blob "$file")" ]; then
    echo "BLOCKED_CUSTOM_SOURCE=$file"
    echo "ACTUAL_BLOB=$actual"
    exit 1
  fi
  echo "SOURCE_VERIFIED=$file"
done < <(files)
if [ "$MODE" = "--check" ]; then
  echo "GOMOVIE_PLAYER_UPLOAD_PREFLIGHT=PASS"
  echo "LIVE_FILES_CHANGED=NO"
  echo "DATABASE_CHANGED=NO"
  exit 0
fi

echo "========== VERIFY PINNED GITHUB ACCEPTANCE =========="
test "$(gh api "repos/$REPO/commits/$TESTED_SHA" --jq '.sha')" = "$TESTED_SHA"
for run in 42638706049 42638706023 42638706296; do
  test "$(gh api "repos/$REPO/actions/runs/$run" --jq '.head_sha')" = "$TESTED_SHA"
  test "$(gh api "repos/$REPO/actions/runs/$run" --jq '.conclusion')" = "success"
done
test "$(gh api "repos/$REPO/actions/runs/$BUILD_RUN" --jq '.head_sha')" = "$SOURCE_SHA"
test "$(gh api "repos/$REPO/actions/runs/$BUILD_RUN" --jq '.conclusion')" = "success"
# Only CI and JavaScript regression tests changed after the compiled build.
# Release runtime file blobs are separately verified, before replacement.
CMP="$(gh api "repos/$REPO/compare/$SOURCE_SHA...$TESTED_SHA" --jq '[.files[].filename] | join(",")')"
test "$CMP" = ".github/workflows/ci.yml,tests/Feature/UploadCaptionTest.php,tests/Feature/UploadStudioTest.php,tests/javascript/player-portrait-v13.test.mjs"

BACKUP="$(mktemp -d /root/Gomovie-Player-Upload-v13-XXXXXXXX)"
chmod 700 "$BACKUP"
mkdir -p "$BACKUP/old" "$BACKUP/source" "$BACKUP/package" "$BACKUP/extract"
echo "BACKUP_DIRECTORY=$BACKUP"

echo "========== DOWNLOAD VERIFIED CI COMPILED RELEASE =========="
gh run download "$BUILD_RUN" --repo "$REPO" \
  --name "$BUILD_NAME" --dir "$BACKUP/package"
test "$(find "$BACKUP/package" -maxdepth 3 -type f -name 'Gomovie-Installer-QA-*.zip' | wc -l)" -eq 1
ZIP="$(find "$BACKUP/package" -maxdepth 3 -type f -name 'Gomovie-Installer-QA-*.zip' -print -quit)"
test -f "$ZIP.sha256"
read -r EXPECTED_ZIP_HASH _ < "$ZIP.sha256"
test "$(sha256sum "$ZIP" | awk '{print $1}')" = "$EXPECTED_ZIP_HASH"
unzip -tqq "$ZIP"
echo "CI_PACKAGE_SHA256=VERIFIED"

echo "========== VERIFY EXACT SOURCE BLOBS =========="
while IFS= read -r file; do
  mkdir -p "$BACKUP/source/$(dirname "$file")" "$BACKUP/old/$(dirname "$file")"
  unzip -p "$ZIP" "$file" > "$BACKUP/source/$file"
  test "$(git hash-object "$BACKUP/source/$file")" = "$(new_blob "$file")"
  if [ -f "$APP/$file" ]; then
    cp -p "$APP/$file" "$BACKUP/old/$file"
  fi
  echo "NEW_SOURCE_VERIFIED=$file"
done < <(files)
cp -p "$APP/public/build/manifest.json" "$BACKUP/old/manifest.json"
if [ -e "$APP/public/build/fonts-manifest.json" ] || [ -L "$APP/public/build/fonts-manifest.json" ]; then
  test -f "$APP/public/build/fonts-manifest.json"
  test ! -L "$APP/public/build/fonts-manifest.json"
  cp -p "$APP/public/build/fonts-manifest.json" "$BACKUP/old/fonts-manifest.json"
  echo "FONTS_MANIFEST_BACKUP=VERIFIED"
else
  echo "FONTS_MANIFEST_BACKUP=NOT_INSTALLED"
fi

echo "========== VERIFY COMPILED PLAYER ASSETS =========="
unzip -q "$ZIP" 'public/build/*' -d "$BACKUP/extract"
BUILD="$BACKUP/extract/public/build"
test -f "$BUILD/manifest.json"
test -z "$(find "$BUILD" -type l -print -quit)"
"$PHP" -r '
$m=json_decode(file_get_contents($argv[1]."/manifest.json"),true,512,JSON_THROW_ON_ERROR);
foreach (["resources/css/app.css","resources/js/app.js"] as $entry) {
    $file=$m[$entry]["file"]??null;
    if (!$file || !is_file($argv[1]."/".$file)) exit(3);
}
' "$BUILD"
grep -RlF 'gm-player-placement.is-portrait' "$BUILD/assets" --include='*.css' >/dev/null
grep -RlF 'gomovie:subtitles-change' "$BUILD/assets" --include='*.js' >/dev/null
grep -RlF 'gm-modern-settings.gm-settings-premium' "$BUILD/assets" --include='*.css' >/dev/null
# Laravel Vite's font plugin emits an additional generated, unversioned
# fonts-manifest.json alongside the hashed assets. Audit the FULL compiled
# directory BEFORE changing any live files; reject all unexpected paths.
FONTS_MANIFEST="$BUILD/fonts-manifest.json"
test -f "$FONTS_MANIFEST" && test ! -L "$FONTS_MANIFEST"
"$PHP" -r '
try {
    $data=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
    if (!is_array($data)) exit(3);
} catch (\Throwable $error) { exit(4); }
' "$FONTS_MANIFEST"
while IFS= read -r -d '' asset; do
  relative="$(realpath --relative-to="$BUILD" "$asset")"
  case "$relative" in
    manifest.json|fonts-manifest.json|assets/*) ;;
    *) echo "BLOCKED_UNEXPECTED_ASSET=$relative"; exit 1;;
  esac
done < <(find "$BUILD" -type f -print0)
echo "COMPILED_ASSET_ALLOWLIST=VERIFIED"
echo "PLAYER_UPLOAD_COMPILED_ASSETS=VERIFIED"

echo "========== VERIFIED MYSQL BACKUP BEFORE MIGRATION =========="
MYSQLDUMP="/www/server/mysql/bin/mysqldump"
test -x "$MYSQLDUMP"
command -v gzip >/dev/null
APP_PATH="$APP" BACKUP_PATH="$BACKUP" "$PHP" <<'PHP'
<?php
require_once getenv('APP_PATH').'/vendor/autoload.php';
$app=require getenv('APP_PATH').'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$conn=config('database.connections.'.config('database.default'));
if (($conn['driver']??null)!=='mysql') {
    fwrite(STDERR,"BLOCKED: This aaPanel upgrade supports MySQL only.\n");
    exit(8);
}
$name=(string)($conn['database']??'');
if (!preg_match('/\A[A-Za-z0-9_]+\z/D',$name)) exit(9);
$quote=static fn($value):string=>'"'.str_replace(["\\",'"',"\n","\r"],["\\\\",'\\"','',''],(string)$value).'"';
$cfg="[client]\n";
foreach(['host','port','username'=>'user','password'] as $key=>$value) {
    $field=is_int($key)?$value:$key;
    $out=is_int($key)?$value:$value;
    if (!isset($conn[$field]) || (string)$conn[$field]==='') continue;
    $cfg.=$out.'='.$quote($conn[$field])."\n";
}
$root=getenv('BACKUP_PATH');
file_put_contents($root.'/mysql.cnf',$cfg);
chmod($root.'/mysql.cnf',0600);
file_put_contents($root.'/database-name',$name);
chmod($root.'/database-name',0600);
PHP
DB_NAME="$(cat "$BACKUP/database-name")"
test -n "$DB_NAME"
"$MYSQLDUMP" --defaults-extra-file="$BACKUP/mysql.cnf" --single-transaction \
  --quick --no-tablespaces --set-gtid-purged=OFF \
  "$DB_NAME" | gzip -1 > "$BACKUP/database.sql.gz"
test -s "$BACKUP/database.sql.gz"
gzip -t "$BACKUP/database.sql.gz"
rm -f -- "$BACKUP/mysql.cnf" "$BACKUP/database-name"
echo "DATABASE_BACKUP=VERIFIED"

CHANGED=0
COMPLETE=0
rollback() {
  if [ "$CHANGED" -eq 1 ] && [ "$COMPLETE" -eq 0 ]; then
    set +e
    echo "========== ROLLBACK PLAYER SOURCE AND VITE MANIFEST =========="
    while IFS= read -r file; do
      if [ -f "$BACKUP/old/$file" ]; then
        cp -p "$BACKUP/old/$file" "$APP/$file"
      else
        rm -f -- "$APP/$file"
      fi
    done < <(files)
    cp -p "$BACKUP/old/manifest.json" "$APP/public/build/manifest.json"
    if [ -f "$BACKUP/old/fonts-manifest.json" ]; then
      cp -p "$BACKUP/old/fonts-manifest.json" "$APP/public/build/fonts-manifest.json"
    else
      rm -f -- "$APP/public/build/fonts-manifest.json"
    fi
    (
      cd "$APP"
      runuser -u www -- "$PHP" artisan optimize:clear
    ) >/dev/null 2>&1
    echo "SOURCE_ROLLBACK=COMPLETE"
    echo "NOTE=Additive caption migration may remain applied; verified database backup retained."
  fi
}
trap rollback EXIT

echo "========== APPLY ADDITIVE WEBVTT CAPTION MIGRATION =========="
MIGRATION="database/migrations/2026_11_09_000300_create_ingest_captions_table.php"
test ! -e "$APP/$MIGRATION" || test "$(git hash-object "$APP/$MIGRATION")" = "$(new_blob "$MIGRATION")"
CHANGED=1
install -d -o root -g www -m 0755 "$(dirname "$APP/$MIGRATION")"
install -o root -g www -m 0644 "$BACKUP/source/$MIGRATION" "$APP/$MIGRATION"
(
  cd "$APP"
  runuser -u www -- "$PHP" artisan migrate --path="$MIGRATION" --force --no-interaction
)
echo "CAPTIONS_MIGRATION=APPLIED"

echo "========== INSTALL VERSIONED FRONTEND ASSETS =========="
CHANGED=1
while IFS= read -r -d '' asset; do
  relative="$(realpath --relative-to="$BUILD" "$asset")"
  if [ "$relative" = "manifest.json" ]; then continue; fi
  # All entries were checked against the exact allowlist before changing
  # production. The generated font manifest is unversioned; backup + replace
  # it deliberately and restore it on failure. Hashed assets remain immutable.
  case "$relative" in
    fonts-manifest.json)
      target="$APP/public/build/fonts-manifest.json"
      if [ -e "$target" ] || [ -L "$target" ]; then
        test -f "$target" && test ! -L "$target"
      fi
      install -o root -g www -m 0644 "$asset" "$target"
      echo "FONTS_MANIFEST=UPDATED"
      ;;
    assets/*)
      target="$APP/public/build/$relative"
      if [ -e "$target" ] || [ -L "$target" ]; then
        test -f "$target" && test ! -L "$target"
        cmp -s "$asset" "$target" || { echo "BLOCKED_CUSTOM_ASSET=$relative"; exit 1; }
      else
        install -d -o root -g www -m 0755 "$(dirname "$target")"
        install -o root -g www -m 0644 "$asset" "$target"
      fi
      ;;
    *) echo "BLOCKED_UNEXPECTED_ASSET=$relative"; exit 1;;
  esac
done < <(find "$BUILD" -type f -print0)
echo "VITE_ASSETS=INSTALLED"

echo "========== INSTALL PLAYER, CAPTIONS AND UPLOAD STUDIO SOURCE =========="
while IFS= read -r file; do
  if [ "$file" = "$MIGRATION" ]; then continue; fi
  install -d -o root -g www -m 0755 "$(dirname "$APP/$file")"
  install -o root -g www -m 0644 "$BACKUP/source/$file" "$APP/$file"
done < <(files)
NEXT_MANIFEST="$APP/public/build/manifest.json.gomovie-player-v13-next"
test ! -e "$NEXT_MANIFEST"
install -o root -g www -m 0644 "$BUILD/manifest.json" "$NEXT_MANIFEST"
mv -- "$NEXT_MANIFEST" "$APP/public/build/manifest.json"

echo "========== COMPILE AND VERIFY LARAVEL =========="
(
  cd "$APP"
  runuser -u www -- "$PHP" artisan optimize:clear
  runuser -u www -- "$PHP" artisan view:cache
)
echo "LARAVEL_VIEWS=COMPILED"
echo "========== VERIFY UPLOAD CAPTION ROUTES =========="
runuser -u www -- "$PHP" "$APP/artisan" route:list --path=upload-studio --json > "$BACKUP/routes.json"
"$PHP" -r '
$routes=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
$names=array_column($routes,"name");
foreach (["upload-studio.captions.index","upload-studio.captions.store","upload-studio.captions.update","upload-studio.captions.destroy","upload-studio.captions.file","upload-studio.destroy"] as $name) {
    if (!in_array($name,$names,true)) exit(11);
}
' "$BACKUP/routes.json"
echo "PRIVATE_CAPTION_ROUTES=VERIFIED"

echo "========== PUBLIC ROUTE SAFETY SMOKE CHECKS =========="
for route in / /login /register /browse \
  /watch/movie/a-story-of-derry-1813355 \
  '/watch/movie/a-story-of-derry-1813355?view=portrait'; do
  code="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' \
    "https://$HOST$route")"
  echo "ROUTE=$route HTTP=$code"
  test "$code" = 200
done
code="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
  --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' \
  "https://$HOST/dashboard/media/ingests/1/preview")"
echo "PRIVATE_PREVIEW_GUEST_HTTP=$code"
test "$code" = 302

echo "========== VERIFY EXACT INSTALLED VERSION =========="
while IFS= read -r file; do
  test "$(git hash-object "$APP/$file")" = "$(new_blob "$file")"
done < <(files)
cmp -s "$BUILD/manifest.json" "$APP/public/build/manifest.json"
cmp -s "$FONTS_MANIFEST" "$APP/public/build/fonts-manifest.json"
echo "FONTS_MANIFEST=VERIFIED"
COMPLETE=1
echo "GOMOVIE_PLAYER_UPLOAD_V13=INSTALLED"
echo "GEAR_PORTRAIT_POPUP=CONTAINED"
echo "PORTRAIT_CONTROLS=SINGLE_INLINE_ROW"
echo "INACTIVE_CHROME=FAST_HIDE"
echo "QUALITY=REAL_HLS_LEVELS_ONLY"
echo "CAPTIONS=OWNER_PRIVATE_WEBVTT_CRUD"
echo "UPLOAD_DELETE=LOCAL_UNPROCESSED_ONLY"
echo "NATIVE_VIDEO_CONTROLS=UNCHANGED_FALLBACK"
echo "PLAYER_CHROME=AUTO_HIDE_WHEN_INACTIVE"
echo "AD_UI=INTEGRATION_READY_DISABLED_BY_DEFAULT"
echo "CAPTIONS=ONLY_WHEN_AVAILABLE"
echo "PICTURE_IN_PICTURE=ONLY_WHEN_SUPPORTED"
echo "SECURE_PLAYBACK=UNCHANGED"
echo "PRIVATE_PREVIEW=UNCHANGED"
echo "DATABASE_CHANGE=ADDITIVE_CAPTIONS_TABLE_ONLY"
echo "EXISTING_MEDIA=UNCHANGED"
echo "PAYMENTS=UNCHANGED"
echo "GOMOVIE_PLAYER_UPLOAD_V13_DEPLOYMENT=SUCCESS"
echo "BACKUP_DIRECTORY=$BACKUP"
