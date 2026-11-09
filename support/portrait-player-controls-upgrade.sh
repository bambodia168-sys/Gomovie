#!/usr/bin/env bash
# Gomovie portrait controls and capability-gated modern player patch.
# Verified CI-compiled Vite assets and only four pinned source files.
# No migrations, database writes, uploaded media changes, or stream grants.
set -Eeuo pipefail
umask 077

if [ "$#" -ne 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
  echo "Usage: bash portrait-player-controls-upgrade.sh --check|--apply"
  exit 2
fi
MODE="$1"
REPO="bambodia168-sys/Gomovie"
TESTED_SHA="400b7c09c0e40c239577a75ddcb1903457ff9828"
SOURCE_SHA="9e550aab54dc39cdb30728e83535df8d245e012e"
BUILD_RUN="42583509288"
BUILD_NAME="gomovie-installer-qa-package"
HOST="gomovie.tonsamith.com"
APP="/www/wwwroot/$HOST"
PHP="/www/server/php/85/bin/php"

files() {
  printf '%s\n' \
    resources/css/app.css \
    resources/js/player.js \
    resources/views/frontend/detail.blade.php \
    resources/views/frontend/partials/modern-player-controls.blade.php
}
old_blob() {
  case "$1" in
    resources/css/app.css) echo 5669e6515c766698dcf86ab22ac7e4e95f06b713;;
    resources/js/player.js) echo d05f8b6f93d2498a42fd726eb601109b1a0f1dad;;
    resources/views/frontend/detail.blade.php) echo b5827ebe707d4ba3c2dcfdf6810817ad1feea9fe;;
    resources/views/frontend/partials/modern-player-controls.blade.php) echo 001d8542934ed93780f6e4471cb2ceec6b759439;;
    *) return 1;;
  esac
}
new_blob() {
  case "$1" in
    resources/css/app.css) echo 8ec5367d23cb87549d6ef292a603baecca399493;;
    resources/js/player.js) echo a845c2912c15d983b13c4c86026349ca4def0793;;
    resources/views/frontend/detail.blade.php) echo 3ee454f5fa51e584f9b1f85d4c38fe6bb8c2b4ce;;
    resources/views/frontend/partials/modern-player-controls.blade.php) echo 92bdb5cf79a3e2858d13cb44d92a77449a79fcc6;;
    *) return 1;;
  esac
}

echo "========== GOMOVIE PORTRAIT CONTROL FIX =========="
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
  echo "GOMOVIE_PORTRAIT_PREFLIGHT=PASS"
  echo "LIVE_FILES_CHANGED=NO"
  echo "DATABASE_CHANGED=NO"
  exit 0
fi

echo "========== VERIFY PINNED GITHUB ACCEPTANCE =========="
test "$(gh api "repos/$REPO/commits/$TESTED_SHA" --jq '.sha')" = "$TESTED_SHA"
for run in 42583631097 42583631155 42583631114; do
  test "$(gh api "repos/$REPO/actions/runs/$run" --jq '.head_sha')" = "$TESTED_SHA"
  test "$(gh api "repos/$REPO/actions/runs/$run" --jq '.conclusion')" = "success"
done
test "$(gh api "repos/$REPO/actions/runs/$BUILD_RUN" --jq '.head_sha')" = "$SOURCE_SHA"
test "$(gh api "repos/$REPO/actions/runs/$BUILD_RUN" --jq '.conclusion')" = "success"
# Application source was identical in this tested commit and compiled CI
# artifact. The only differences were CI and test source files.
CMP="$(gh api "repos/$REPO/compare/$SOURCE_SHA...$TESTED_SHA" --jq '[.files[].filename] | join(",")')"
test "$CMP" = ".github/workflows/ci.yml,tests/Feature/PlayerSettingsTest.php"

BACKUP="$(mktemp -d /root/Gomovie-Portrait-Player-XXXXXXXX)"
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
  cp -p "$APP/$file" "$BACKUP/old/$file"
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
grep -RlF 'gm-modern-menu-volume' "$BUILD/assets" --include='*.css' >/dev/null
grep -RlF 'gmSubtitleCount' "$BUILD/assets" --include='*.js' >/dev/null
# Laravel Vite's font plugin emits an additional generated, unversioned
# fonts-manifest.json alongside the hashed assets. Audit the FULL compiled
# directory BEFORE changing any live files; reject all unexpected paths.
FONTS_MANIFEST="$BUILD/fonts-manifest.json"
test -f "$FONTS_MANIFEST" && test ! -L "$FONTS_MANIFEST"
"$PHP" -r '
try {
    $data=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
    if (!is_array($data)) exit(3);
} catch (\\Throwable $error) { exit(4); }
' "$FONTS_MANIFEST"
while IFS= read -r -d '' asset; do
  relative="$(realpath --relative-to="$BUILD" "$asset")"
  case "$relative" in
    manifest.json|fonts-manifest.json|assets/*) ;;
    *) echo "BLOCKED_UNEXPECTED_ASSET=$relative"; exit 1;;
  esac
done < <(find "$BUILD" -type f -print0)
echo "COMPILED_ASSET_ALLOWLIST=VERIFIED"
echo "PORTRAIT_PLAYER_COMPILED_ASSETS=VERIFIED"

CHANGED=0
COMPLETE=0
rollback() {
  if [ "$CHANGED" -eq 1 ] && [ "$COMPLETE" -eq 0 ]; then
    set +e
    echo "========== ROLLBACK PLAYER SOURCE AND VITE MANIFEST =========="
    while IFS= read -r file; do
      cp -p "$BACKUP/old/$file" "$APP/$file"
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
  fi
}
trap rollback EXIT

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

echo "========== INSTALL FOUR PLAYER SOURCE FILES =========="
while IFS= read -r file; do
  install -o root -g www -m 0644 "$BACKUP/source/$file" "$APP/$file"
done < <(files)
NEXT_MANIFEST="$APP/public/build/manifest.json.gomovie-portrait-next"
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
echo "GOMOVIE_PORTRAIT_CONTROLS=FIXED"
echo "PORTRAIT_LAYOUT=PLAYER_WIDTH_RESPONSIVE"
echo "CAPTIONS=ONLY_WHEN_AVAILABLE"
echo "PICTURE_IN_PICTURE=ONLY_WHEN_SUPPORTED"
echo "SECURE_PLAYBACK=UNCHANGED"
echo "PRIVATE_PREVIEW=UNCHANGED"
echo "DATABASE_CHANGED=NO"
echo "EXISTING_MEDIA=UNCHANGED"
echo "PAYMENTS=UNCHANGED"
echo "GOMOVIE_PORTRAIT_DEPLOYMENT=SUCCESS"
echo "BACKUP_DIRECTORY=$BACKUP"
