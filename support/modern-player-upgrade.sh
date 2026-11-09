#!/usr/bin/env bash
# Selective Gomovie modern player update for installed aaPanel.
# Pinned CI assets + 11 source files + one additive settings migration.
set -Eeuo pipefail
umask 077
if [ "$#" -ne 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
  echo "Usage: bash modern-player-upgrade.sh --check|--apply"; exit 2
fi
MODE="$1"
REPO="bambodia168-sys/Gomovie"
TESTED_SHA="45fbe882bde4484a41121e9ecba99826d2d11f20"
SOURCE_SHA="180f8cad1ecff7e98dd105106ebd6326d60e33f4"
BUILD_RUN="42580390022"
BUILD_NAME="gomovie-installer-qa-package"
HOST="gomovie.tonsamith.com"
APP="/www/wwwroot/$HOST"
PHP="/www/server/php/85/bin/php"
MYSQLDUMP="/www/server/mysql/bin/mysqldump"
MIGRATION="database/migrations/2026_11_09_000200_create_player_preferences_table.php"
files() {
  printf '%s\n' \
    routes/web.php \
    resources/views/layouts/admin.blade.php \
    app/Http/Controllers/Frontend/BrowseController.php \
    resources/views/frontend/detail.blade.php \
    resources/js/player.js \
    resources/css/app.css \
    resources/views/frontend/partials/modern-player-controls.blade.php \
    resources/views/admin/player-settings.blade.php \
    app/Support/PlayerPreferences.php \
    app/Http/Controllers/Admin/PlayerSettingsController.php \
    "$MIGRATION"
}
old_blob() {
  case "$1" in
    routes/web.php) echo 1415dfb8db52620e1d2f7f5ff7d31e8c3e3e397f;;
    resources/views/layouts/admin.blade.php) echo a85983f4c4aa6a661044593b1a06700d5cc8a7cf;;
    app/Http/Controllers/Frontend/BrowseController.php) echo 389dc1830e840a68cb84595c4fc77564b9d5c119;;
    resources/views/frontend/detail.blade.php) echo 1436397b450338df3db992e2d7b239e089ca0a95;;
    resources/js/player.js) echo 3fdba292171441f30bdd40e48cf06c8d5ee57bc8;;
    resources/css/app.css) echo 56669559c6adebe1b997a0ecbd59c460fe49f461;;
    *) echo NEW_FILE;;
  esac
}
new_blob() {
  case "$1" in
    routes/web.php) echo f1a61447e5f0e2679104cc8c84cf8cf121000943;;
    resources/views/layouts/admin.blade.php) echo 23ed146c494703d71e993e6bc39cbd1a94059577;;
    app/Http/Controllers/Frontend/BrowseController.php) echo a31f97e68b4d2a2392604aa6fe1dc5b352ff2ed9;;
    resources/views/frontend/detail.blade.php) echo b5827ebe707d4ba3c2dcfdf6810817ad1feea9fe;;
    resources/js/player.js) echo d05f8b6f93d2498a42fd726eb601109b1a0f1dad;;
    resources/css/app.css) echo 5669e6515c766698dcf86ab22ac7e4e95f06b713;;
    resources/views/frontend/partials/modern-player-controls.blade.php) echo 001d8542934ed93780f6e4471cb2ceec6b759439;;
    resources/views/admin/player-settings.blade.php) echo 49b4b88b5029b6476fc7bc3d1ea18532e7022922;;
    app/Support/PlayerPreferences.php) echo 88749cb111c419703b080296d0b4f06493509bcc;;
    app/Http/Controllers/Admin/PlayerSettingsController.php) echo 92bc98ced49925a7d63fc8bf951173c6d66f3174;;
    database/migrations/2026_11_09_000200_create_player_preferences_table.php) echo 4293a65f960b8a72528712070acda6e4d5787ae6;;
    *) return 1;;
  esac
}

echo "========== GOMOVIE MODERN PLAYER =========="
echo "MODE=$MODE"
test "$(id -u)" -eq 0
test -d "$APP" && test ! -L "$APP" && test "$(realpath -e "$APP")" = "$APP"
test -f "$APP/.env" && test ! -L "$APP/.env"
test -f "$APP/artisan" && test -f "$APP/vendor/autoload.php"
test -f "$APP/storage/app/private/.gomovie-installed"
test -f "$APP/public/build/manifest.json" && test ! -L "$APP/public/build/manifest.json"
test -x "$PHP" && test -x "$MYSQLDUMP"
id www >/dev/null
for tool in gh git curl unzip sha256sum gzip runuser cmp; do command -v "$tool" >/dev/null; done

echo "========== READ-ONLY LIVE SOURCE PREFLIGHT =========="
while IFS= read -r file; do
  old="$(old_blob "$file")"
  if [ "$old" = NEW_FILE ] && [ ! -e "$APP/$file" ]; then
    test ! -L "$APP/$file"
    echo "NEW_SOURCE=NOT_INSTALLED:$file"
    continue
  fi
  test -f "$APP/$file" && test ! -L "$APP/$file"
  actual="$(git hash-object "$APP/$file")"
  if [ "$actual" != "$old" ] && [ "$actual" != "$(new_blob "$file")" ]; then
    echo "BLOCKED_CUSTOM_FILE=$file CURRENT_BLOB=$actual"
    exit 1
  fi
  echo "SOURCE_VERIFIED=$file"
done < <(files)
if [ "$MODE" = "--check" ]; then
  echo "GOMOVIE_MODERN_PLAYER_PREFLIGHT=PASS"
  echo "FILES_CHANGED=NO"
  echo "DATABASE_CHANGED=NO"
  exit 0
fi

echo "========== VERIFY EXACT SOURCE AND CI BUILD =========="
test "$(gh api "repos/$REPO/commits/$TESTED_SHA" --jq '.sha')" = "$TESTED_SHA"
test "$(gh api "repos/$REPO/actions/runs/$BUILD_RUN" --jq '.head_sha')" = "$SOURCE_SHA"
test "$(gh api "repos/$REPO/actions/runs/$BUILD_RUN" --jq '.conclusion')" = success
for run in 42580757713 42580757814 42580757677; do
  test "$(gh api "repos/$REPO/actions/runs/$run" --jq '.head_sha')" = "$TESTED_SHA"
  test "$(gh api "repos/$REPO/actions/runs/$run" --jq '.conclusion')" = success
done
CMP="$(gh api "repos/$REPO/compare/$SOURCE_SHA...$TESTED_SHA" --jq '[.files[].filename] | join(",")')"
test "$CMP" = "tests/Feature/PlayerSettingsTest.php"

BACKUP="$(mktemp -d /root/Gomovie-Modern-Player-XXXXXXXX)"
chmod 700 "$BACKUP"
mkdir -p "$BACKUP/old" "$BACKUP/source" "$BACKUP/package" "$BACKUP/extract"
echo "BACKUP_DIRECTORY=$BACKUP"

echo "========== DOWNLOAD CHECKSUMMED COMPILED RELEASE =========="
gh run download "$BUILD_RUN" --repo "$REPO" --name "$BUILD_NAME" --dir "$BACKUP/package"
test "$(find "$BACKUP/package" -type f -name 'Gomovie-Installer-QA-*.zip' | wc -l)" -eq 1
ZIP="$(find "$BACKUP/package" -type f -name 'Gomovie-Installer-QA-*.zip' -print -quit)"
test -f "$ZIP.sha256"
read -r EXPECTED_ZIP_HASH _ < "$ZIP.sha256"
test "$(sha256sum "$ZIP" | awk '{print $1}')" = "$EXPECTED_ZIP_HASH"
unzip -tqq "$ZIP"
echo "CI_PACKAGE_SHA256=VERIFIED"

echo "========== VALIDATE ALL SOURCE FILES =========="
while IFS= read -r file; do
  mkdir -p "$BACKUP/source/$(dirname "$file")" "$BACKUP/old/$(dirname "$file")"
  unzip -p "$ZIP" "$file" > "$BACKUP/source/$file"
  test "$(git hash-object "$BACKUP/source/$file")" = "$(new_blob "$file")"
  if [ -f "$APP/$file" ]; then cp -p "$APP/$file" "$BACKUP/old/$file"; fi
  case "$file" in *.php) "$PHP" -l "$BACKUP/source/$file" >/dev/null;; esac
  echo "SOURCE_PACKAGE_VERIFIED=$file"
done < <(files)
cp -p "$APP/public/build/manifest.json" "$BACKUP/old/build-manifest.json"

echo "========== VALIDATE COMPILED CSS AND JAVASCRIPT =========="
unzip -q "$ZIP" 'public/build/*' -d "$BACKUP/extract"
BUILD="$BACKUP/extract/public/build"
test -f "$BUILD/manifest.json"
test -z "$(find "$BUILD" -type l -print -quit)"
"$PHP" -r '
$m=json_decode(file_get_contents($argv[1]."/manifest.json"),true,512,JSON_THROW_ON_ERROR);
foreach (["resources/css/app.css","resources/js/app.js"] as $name) {
  if (!isset($m[$name]["file"]) || !is_file($argv[1]."/".$m[$name]["file"])) exit(3);
}
' "$BUILD"
grep -RlF 'data-gm-timeline' "$BUILD/assets" --include='*.js' >/dev/null
grep -RlF 'gm-modern-player' "$BUILD/assets" --include='*.css' >/dev/null
echo "MODERN_PLAYER_FRONTEND_BUILD=VERIFIED"

echo "========== BACKUP MYSQL =========="
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
rm -f "$BACKUP/mysql.cnf" "$BACKUP/database-name"
echo "DATABASE_BACKUP=VERIFIED"

CHANGED=0
COMPLETE=0
rollback() {
  if [ "$CHANGED" = 1 ] && [ "$COMPLETE" = 0 ]; then
    set +e
    echo "========== ROLL BACK SOURCES AND MANIFEST =========="
    while IFS= read -r file; do
      if [ -f "$BACKUP/old/$file" ]; then
        cp -p "$BACKUP/old/$file" "$APP/$file"
      else
        rm -f -- "$APP/$file"
      fi
    done < <(files)
    cp -p "$BACKUP/old/build-manifest.json" "$APP/public/build/manifest.json"
    ( cd "$APP" && runuser -u www -- "$PHP" artisan optimize:clear ) >/dev/null 2>&1
    echo "SOURCE_ROLLBACK=COMPLETE"
    echo "NOTE=Additive settings migration might remain; database backup retained."
  fi
}
trap rollback EXIT

echo "========== APPLY ADDITIVE MIGRATION =========="
CHANGED=1
install -o root -g www -m 0644 "$BACKUP/source/$MIGRATION" "$APP/$MIGRATION"
( cd "$APP" && runuser -u www -- "$PHP" artisan migrate --path="$MIGRATION" --force --no-interaction )
echo "PLAYER_SETTINGS_MIGRATION=APPLIED"

echo "========== INSTALL VERSIONED VITE ASSETS =========="
while IFS= read -r -d '' asset; do
  relative="$(realpath --relative-to="$BUILD" "$asset")"
  if [ "$relative" = manifest.json ]; then continue; fi
  target="$APP/public/build/$relative"
  if [ -e "$target" ]; then
    test -f "$target" && test ! -L "$target"
    cmp -s "$asset" "$target" || { echo "BLOCKED_CUSTOM_ASSET=$relative"; exit 1; }
  else
    install -d -o root -g www -m 0755 "$(dirname "$target")"
    install -o root -g www -m 0644 "$asset" "$target"
  fi
done < <(find "$BUILD" -type f -print0)
echo "VITE_ASSETS=INSTALLED"

echo "========== INSTALL PLAYER SOURCE =========="
while IFS= read -r file; do
  if [ "$file" = "$MIGRATION" ]; then continue; fi
  install -d -o root -g www -m 0755 "$(dirname "$APP/$file")"
  install -o root -g www -m 0644 "$BACKUP/source/$file" "$APP/$file"
done < <(files)
echo "========== ACTIVATE COMPILED MANIFEST =========="
NEXT="$APP/public/build/manifest.json.gomovie-player-next"
test ! -e "$NEXT"
install -o root -g www -m 0644 "$BUILD/manifest.json" "$NEXT"
mv -- "$NEXT" "$APP/public/build/manifest.json"

echo "========== VERIFY ROUTES AND BLADE =========="
( cd "$APP" && runuser -u www -- "$PHP" artisan optimize:clear && \
  runuser -u www -- "$PHP" artisan view:cache )
(
  cd "$APP"
  runuser -u www -- "$PHP" artisan route:list --path=dashboard/player-settings --json
) | "$PHP" -r '
$routes=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$expected=["admin.player-settings.edit"=>"GET","admin.player-settings.update"=>"PATCH"];
foreach ($routes as $r) {
  $name=$r["name"]??"";
  if (isset($expected[$name]) && ($r["uri"]??"")==="dashboard/player-settings"
      && in_array($expected[$name],explode("|",(string)($r["method"]??"")),true)) unset($expected[$name]);
}
if ($expected) {fwrite(STDERR,"PLAYER_SETTINGS_ROUTES=NOT_FOUND\n");exit(1);}
echo "PLAYER_SETTINGS_ROUTES=VERIFIED\n";
'

echo "========== LIVE WEBSITE SMOKE CHECK =========="
for route in / /login /register /browse /watch/movie/a-story-of-derry-1813355; do
  code="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' "https://$HOST$route")"
  echo "ROUTE=$route HTTP=$code"
  test "$code" = 200
done
for route in /dashboard/player-settings /dashboard/media/ingests/1/preview; do
  code="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' "https://$HOST$route")"
  echo "GUEST_ROUTE=$route HTTP=$code"
  test "$code" = 302
done

echo "========== INSTALLED SOURCE & MANIFEST INTEGRITY =========="
while IFS= read -r file; do
  test "$(git hash-object "$APP/$file")" = "$(new_blob "$file")"
done < <(files)
cmp -s "$BUILD/manifest.json" "$APP/public/build/manifest.json"
COMPLETE=1
echo "GOMOVIE_MODERN_PLAYER=INSTALLED"
echo "PLAYER_SETTINGS=/dashboard/player-settings"
echo "PLAYER_UI=EDITABLE"
echo "PRIVATE_PREVIEW_SECURITY=UNCHANGED"
echo "PUBLIC_STREAMING=UNCHANGED"
echo "EXISTING_MEDIA=UNCHANGED"
echo "PAYMENTS=UNCHANGED"
echo "GOMOVIE_MODERN_PLAYER_DEPLOYMENT=SUCCESS"
echo "BACKUP_DIRECTORY=$BACKUP"
