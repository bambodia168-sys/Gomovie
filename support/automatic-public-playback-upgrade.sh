#!/usr/bin/env bash
# Gomovie automatic public playback: selective code + signed CI Vite assets.
# OFF by default: no rights, playback, private media or .env changes.
set -Eeuo pipefail
umask 077

if [ "$#" -ne 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
  echo 'Usage: bash automatic-public-playback-upgrade.sh --check|--apply'
  exit 2
fi
MODE="$1"
REPO="bambodia168-sys/Gomovie"
SOURCE_SHA="a9195a9e706dc6e4e2e8e25015360f45a38ff0d2"
PACKAGE_RUN="42630462743"
PACKAGE_NAME="gomovie-installer-qa-package"
HOST="gomovie.tonsamith.com"
APP="/www/wwwroot/$HOST"
PHP="/www/server/php/85/bin/php"
MYSQLDUMP="/www/server/mysql/bin/mysqldump"
MIGRATION="database/migrations/2026_11_09_000200_create_public_media_files.php"

files() {
  cat <<'PATHS'
app/Http/Controllers/Frontend/PublicHlsController.php
app/Services/Media/AutomaticPublicationService.php
app/Jobs/AutoPublishUploadedVideo.php
database/migrations/2026_11_09_000200_create_public_media_files.php
app/Services/Media/MediaPackageInspector.php
app/Services/Media/PrivateVideoIntake.php
config/gomovie.php
routes/web.php
app/Http/Controllers/Frontend/BrowseController.php
resources/js/player.js
resources/views/frontend/detail.blade.php
app/Http/Controllers/Admin/UploadStudioWorkflowController.php
app/Http/Controllers/Admin/UploadStudioController.php
resources/views/admin/upload-studio.blade.php
PATHS
}

old_blob() {
  case "$1" in
    app/Http/Controllers/Frontend/PublicHlsController.php|app/Services/Media/AutomaticPublicationService.php|app/Jobs/AutoPublishUploadedVideo.php|database/migrations/2026_11_09_000200_create_public_media_files.php) echo NEW;;
    app/Services/Media/MediaPackageInspector.php) echo b0424d8b80c33eb63de1adcdec4e37c6d99240c0;;
    app/Services/Media/PrivateVideoIntake.php) echo 0046013039f785afc7dbdfc2d2c3317256eb41bb;;
    config/gomovie.php) echo dd06feff774b2fe09d91681788ce6274eb7ccebb;;
    routes/web.php) echo f1a61447e5f0e2679104cc8c84cf8cf121000943;;
    app/Http/Controllers/Frontend/BrowseController.php) echo a31f97e68b4d2a2392604aa6fe1dc5b352ff2ed9;;
    resources/js/player.js) echo dde44b7f1284dbe8794fd1c7573606731710ea9e;;
    resources/views/frontend/detail.blade.php) echo 96f299fec273360799d094f61e435eeb73d395ea;;
    app/Http/Controllers/Admin/UploadStudioWorkflowController.php) echo e6d3180ade2f1c48c324734baea643e798eef635;;
    app/Http/Controllers/Admin/UploadStudioController.php) echo d7ad7165e6ca85e76000fb9382a7aae90d67e5e3;;
    resources/views/admin/upload-studio.blade.php) echo dfec39a9af4b7f5812d3c090473d3322c964e614;;
    *) return 1;;
  esac
}
new_blob() {
  case "$1" in
    app/Http/Controllers/Frontend/PublicHlsController.php) echo 526b74d24d1b2b0c50fdcca3ad96e02ec1cd3f76;;
    app/Services/Media/AutomaticPublicationService.php) echo 060b9b812e296c13edb0f0c9ee3d9b16957f2805;;
    app/Jobs/AutoPublishUploadedVideo.php) echo 770e673ab4db60cf1553c7979fdb491259412c4e;;
    database/migrations/2026_11_09_000200_create_public_media_files.php) echo 7aed558f2e773a1aa5771021b8f308827d98b634;;
    app/Services/Media/MediaPackageInspector.php) echo 4513fb2a4a69f4d79863b1d4f19fbbb6d3d384e7;;
    app/Services/Media/PrivateVideoIntake.php) echo b59937b1d14763ac6f04da1b0a62fa0ace07e551;;
    config/gomovie.php) echo 544ed0605159d1d9a31ee684b83035c3b38a9829;;
    routes/web.php) echo 7c281ecc371244edc1b2758ec5ac958de64ecd64;;
    app/Http/Controllers/Frontend/BrowseController.php) echo 4b0bd4708cec3e33974c7b465a1abebe2c811644;;
    resources/js/player.js) echo d9beae59872ffd171e69212c856b559c08b1300a;;
    resources/views/frontend/detail.blade.php) echo d49cdcc3ad8c602c0f4f8e676a71c276d6cccd79;;
    app/Http/Controllers/Admin/UploadStudioWorkflowController.php) echo 7e5ea09359dbc39cae3b740108183b5c3427fb0d;;
    app/Http/Controllers/Admin/UploadStudioController.php) echo e9e62dbb8adf9f496a167c67fca7bfb4a92ad23c;;
    resources/views/admin/upload-studio.blade.php) echo 5a1c362229cda510141d5abae2bf1b93c1d58752;;
    *) return 1;;
  esac
}

echo "========== GOMOVIE AUTOMATIC PUBLIC PLAYBACK =========="
echo "MODE=$MODE"
test "$(id -u)" -eq 0
test -d "$APP" && test ! -L "$APP" && test "$(realpath -e "$APP")" = "$APP"
test -f "$APP/.env" && test ! -L "$APP/.env"
test -f "$APP/artisan" && test ! -L "$APP/artisan"
test -f "$APP/vendor/autoload.php" && test ! -L "$APP/vendor/autoload.php"
test -f "$APP/storage/app/private/.gomovie-installed"
test -f "$APP/public/build/manifest.json" && test ! -L "$APP/public/build/manifest.json"
test -x "$PHP"
id www >/dev/null
for tool in gh git curl unzip sha256sum runuser cmp gzip find; do command -v "$tool" >/dev/null; done

echo "========== STRICT LIVE SOURCE COMPATIBILITY =========="
while IFS= read -r relative; do
  old="$(old_blob "$relative")"
  new="$(new_blob "$relative")"
  if [ "$old" = NEW ] && [ ! -e "$APP/$relative" ]; then
    test ! -L "$APP/$relative"
    echo "NEW_FILE=NOT_INSTALLED:$relative"
    continue
  fi
  test -f "$APP/$relative" && test ! -L "$APP/$relative"
  actual="$(git hash-object "$APP/$relative")"
  if [ "$actual" != "$old" ] && [ "$actual" != "$new" ]; then
    echo "BLOCKED_CUSTOM_SOURCE=$relative"
    echo "CURRENT_BLOB=$actual"
    exit 1
  fi
  echo "SOURCE_COMPATIBLE=$relative"
done < <(files)

if [ "$MODE" = "--check" ]; then
  echo "AUTO_PUBLICATION_INSTALL_PREFLIGHT=PASS"
  echo "LIVE_FILES_CHANGED=NO"
  echo "DATABASE_CHANGED=NO"
  echo "PUBLICATION_ACTIVATED=NO"
  exit 0
fi

test -x "$MYSQLDUMP"
echo "========== VERIFY CI-TESTED SOURCE AND PACKAGE =========="
test "$(gh api "repos/$REPO/commits/$SOURCE_SHA" --jq '.sha')" = "$SOURCE_SHA"
for run in 42630462866 42630462779 42630462770; do
  test "$(gh api "repos/$REPO/actions/runs/$run" --jq '.head_sha')" = "$SOURCE_SHA"
  test "$(gh api "repos/$REPO/actions/runs/$run" --jq '.conclusion')" = success
done
test "$(gh api "repos/$REPO/actions/runs/$PACKAGE_RUN" --jq '.head_sha')" = "$SOURCE_SHA"
test "$(gh api "repos/$REPO/actions/runs/$PACKAGE_RUN" --jq '.conclusion')" = success

BACKUP="$(mktemp -d /root/Gomovie-Auto-Public-XXXXXXXX)"
chmod 700 "$BACKUP"
mkdir -p "$BACKUP/old" "$BACKUP/source" "$BACKUP/package" "$BACKUP/extract"
echo "BACKUP_DIRECTORY=$BACKUP"

echo "========== DOWNLOAD CHECKSUMMED CI RELEASE =========="
gh run download "$PACKAGE_RUN" --repo "$REPO" --name "$PACKAGE_NAME" --dir "$BACKUP/package"
test "$(find "$BACKUP/package" -type f -name 'Gomovie-Installer-QA-*.zip' | wc -l)" -eq 1
ZIP="$(find "$BACKUP/package" -type f -name 'Gomovie-Installer-QA-*.zip' -print -quit)"
test -f "$ZIP.sha256"
read -r ZIP_HASH _ < "$ZIP.sha256"
test "$(sha256sum "$ZIP" | awk '{print $1}')" = "$ZIP_HASH"
unzip -tqq "$ZIP"
echo "CI_PACKAGE_SHA256=VERIFIED"

echo "========== VERIFY AND BACKUP ALL SELECTIVE SOURCES =========="
while IFS= read -r relative; do
  mkdir -p "$BACKUP/source/$(dirname "$relative")" "$BACKUP/old/$(dirname "$relative")"
  unzip -p "$ZIP" "$relative" > "$BACKUP/source/$relative"
  test "$(git hash-object "$BACKUP/source/$relative")" = "$(new_blob "$relative")"
  if [ -e "$APP/$relative" ]; then
    cp -p "$APP/$relative" "$BACKUP/old/$relative"
  fi
  case "$relative" in *.php) "$PHP" -l "$BACKUP/source/$relative" >/dev/null;; esac
  echo "RELEASE_SOURCE_VERIFIED=$relative"
done < <(files)

echo "========== VERIFY COMPILED VITE ASSETS =========="
unzip -q "$ZIP" 'public/build/*' -d "$BACKUP/extract"
BUILD="$BACKUP/extract/public/build"
test -f "$BUILD/manifest.json" && test ! -L "$BUILD/manifest.json"
test -z "$(find "$BUILD" -type l -print -quit)"
"$PHP" -r '
$m=json_decode(file_get_contents($argv[1]."/manifest.json"),true,512,JSON_THROW_ON_ERROR);
foreach (["resources/css/app.css","resources/js/app.js"] as $name) {
  $file=$m[$name]["file"]??null;
  if (!$file || !is_file($argv[1]."/".$file)) exit(3);
}
' "$BUILD"
grep -RlF 'data-public-hls-url' "$BUILD/assets" --include='*.js' >/dev/null || \
  grep -RlF 'publicHlsUrl' "$BUILD/assets" --include='*.js' >/dev/null
while IFS= read -r -d '' asset; do
  relative="$(realpath --relative-to="$BUILD" "$asset")"
  case "$relative" in manifest.json|fonts-manifest.json|assets/*) ;; *) echo "BLOCKED_ASSET_PATH=$relative"; exit 1;; esac
done < <(find "$BUILD" -type f -print0)
echo "VITE_COMPILATION=VERIFIED"

echo "========== CREATE PRIVATE MYSQL BACKUP =========="
APP_PATH="$APP" BACKUP_PATH="$BACKUP" "$PHP" <<'PHP'
<?php
require_once getenv('APP_PATH').'/vendor/autoload.php';
$app=require getenv('APP_PATH').'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$conn=config('database.connections.'.config('database.default'));
if (($conn['driver']??null)!=='mysql') {
    fwrite(STDERR,"BLOCKED: This aaPanel helper supports MySQL only.\n");
    exit(8);
}
$name=(string)($conn['database']??'');
if (!preg_match('/\A[A-Za-z0-9_]+\z/D',$name)) exit(9);
$quote=static fn($value):string=>'"'.str_replace(["\\",'"',"\n","\r"],["\\\\",'\\"','',''],(string)$value).'"';
$cfg="[client]\n";
foreach (['host','port','username'=>'user','password'] as $key=>$value) {
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
echo "MYSQL_BACKUP=VERIFIED"
cp -p "$APP/public/build/manifest.json" "$BACKUP/old/build-manifest.json"
if [ -f "$APP/public/build/fonts-manifest.json" ]; then
  test ! -L "$APP/public/build/fonts-manifest.json"
  cp -p "$APP/public/build/fonts-manifest.json" "$BACKUP/old/fonts-manifest.json"
fi

echo "========== RECHECK PRODUCTION SOURCE BEFORE CHANGES =========="
while IFS= read -r relative; do
  if [ -f "$BACKUP/old/$relative" ]; then
    test "$(git hash-object "$APP/$relative")" = "$(git hash-object "$BACKUP/old/$relative")"
  else
    test ! -e "$APP/$relative" && test ! -L "$APP/$relative"
  fi
done < <(files)

CHANGED=0
COMPLETE=0
rollback() {
  if [ "$CHANGED" -eq 1 ] && [ "$COMPLETE" -eq 0 ]; then
    set +e
    echo "========== RESTORE APPLICATION SOURCES AND VITE MANIFEST =========="
    while IFS= read -r relative; do
      if [ -f "$BACKUP/old/$relative" ]; then
        cp -p "$BACKUP/old/$relative" "$APP/$relative"
      else
        rm -f -- "$APP/$relative"
      fi
    done < <(files)
    cp -p "$BACKUP/old/build-manifest.json" "$APP/public/build/manifest.json"
    if [ -f "$BACKUP/old/fonts-manifest.json" ]; then
      cp -p "$BACKUP/old/fonts-manifest.json" "$APP/public/build/fonts-manifest.json"
    else
      rm -f -- "$APP/public/build/fonts-manifest.json"
    fi
    (cd "$APP" && runuser -u www -- "$PHP" artisan optimize:clear) >/dev/null 2>&1
    echo "SOURCE_ROLLBACK=COMPLETE"
    echo "NOTE=Additive public media files migration may remain. Database backup retained."
  fi
}
trap rollback EXIT

echo "========== INSTALL ADDITIVE DATABASE MIGRATION =========="
CHANGED=1
install -o root -g www -m 0644 "$BACKUP/source/$MIGRATION" "$APP/$MIGRATION"
(
  cd "$APP"
  runuser -u www -- "$PHP" artisan migrate --path="$MIGRATION" --force --no-interaction
)
echo "PUBLIC_MEDIA_FILES_MIGRATION=APPLIED"

echo "========== INSTALL IMMUTABLE VITE ASSETS =========="
while IFS= read -r -d '' asset; do
  relative="$(realpath --relative-to="$BUILD" "$asset")"
  if [ "$relative" = manifest.json ]; then continue; fi
  target="$APP/public/build/$relative"
  case "$relative" in
    assets/*)
      if [ -e "$target" ]; then
        test -f "$target" && test ! -L "$target"
        cmp -s "$asset" "$target" || { echo "BLOCKED_CUSTOM_ASSET=$relative"; exit 1; }
      else
        install -d -o root -g www -m 0755 "$(dirname "$target")"
        install -o root -g www -m 0644 "$asset" "$target"
      fi
      ;;
    fonts-manifest.json)
      if [ -e "$target" ]; then test -f "$target" && test ! -L "$target"; fi
      install -o root -g www -m 0644 "$asset" "$target"
      ;;
  esac
done < <(find "$BUILD" -type f -print0)

echo "========== INSTALL SELECTIVE APPLICATION FILES =========="
while IFS= read -r relative; do
  if [ "$relative" = "$MIGRATION" ]; then continue; fi
  install -o root -g www -m 0644 "$BACKUP/source/$relative" "$APP/$relative"
done < <(files)
NEXT="$APP/public/build/manifest.json.gomovie-autopub-next"
test ! -e "$NEXT" && test ! -L "$NEXT"
install -o root -g www -m 0644 "$BUILD/manifest.json" "$NEXT"
mv -- "$NEXT" "$APP/public/build/manifest.json"

echo "========== CHECK ROUTES, VIEWS AND GUEST SAFETY =========="
(
  cd "$APP"
  runuser -u www -- "$PHP" artisan optimize:clear --no-interaction
  runuser -u www -- "$PHP" artisan view:cache --no-interaction
  runuser -u www -- "$PHP" artisan route:list --path=hls
)
for path in / /login /browse; do
  code="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' "https://$HOST$path")"
  echo "ROUTE=$path HTTP=$code"
  test "$code" = 200
done
code="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
  --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' \
  "https://$HOST/hls/1/master.m3u8")"
echo "UNACTIVATED_PUBLIC_HLS_HTTP=$code"
test "$code" = 404

echo "========== FINAL INTEGRITY =========="
while IFS= read -r relative; do
  test "$(git hash-object "$APP/$relative")" = "$(new_blob "$relative")"
done < <(files)
cmp -s "$BUILD/manifest.json" "$APP/public/build/manifest.json"
COMPLETE=1
echo "AUTOMATIC_PUBLICATION_CODE=INSTALLED"
echo "PUBLIC_HLS_ROUTING=INSTALLED_DISABLED"
echo "LIVE_AUTOMATIC_PUBLICATION=DISABLED_PENDING_HOST_ACCEPTANCE"
echo "PRIVATE_UPLOADS=UNCHANGED"
echo "EXISTING_MEDIA=UNCHANGED"
echo "PAYMENTS=UNCHANGED"
echo "GOMOVIE_AUTO_PUBLIC_DEPLOYMENT=SUCCESS"
echo "BACKUP_DIRECTORY=$BACKUP"
