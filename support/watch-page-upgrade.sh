#!/usr/bin/env bash
# Selective, rollback-capable upgrade for an INSTALLED Gomovie aaPanel app.
# NEVER extract the whole customer installer archive into the live directory.
set -Eeuo pipefail
umask 077

if [ "$#" -ne 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
  echo "Usage: bash watch-page-upgrade.sh --check|--apply"
  exit 2
fi
MODE="$1"

HOST="gomovie.tonsamith.com"
APP="/www/wwwroot/$HOST"
PHP="/www/server/php/85/bin/php"
REPO="bambodia168-sys/Gomovie"
SOURCE_SHA="3093a1301d40f0bcac7bc9613caa86bb8c3dc3fe"
BUILD_SHA="3fc5f4fb463719b207ffbdeb7400fcda097e9ce2"
BUILD_RUN="42510103098"
BUILD_NAME="gomovie-installer-qa-package"

files() {
  printf '%s\n' \
    "app/Http/Controllers/Frontend/BrowseController.php" \
    "resources/views/frontend/detail.blade.php" \
    "resources/js/player.js" \
    "resources/css/app.css"
}
old_blob() {
  case "$1" in
    "app/Http/Controllers/Frontend/BrowseController.php") echo "febca025e264c2109fd3721da2d3c26f165d6ae8";;
    "resources/views/frontend/detail.blade.php") echo "5e0b95055068b1cc2910df67446d6f73f04f6e3f";;
    "resources/js/player.js") echo "0a0aae1a4a068ad87ecd665bc0e667288a76d4c7";;
    "resources/css/app.css") echo "c4c1e63a705d412a2fba54b18f79868ded100bef";;
    *) return 1;;
  esac
}
new_blob() {
  case "$1" in
    "app/Http/Controllers/Frontend/BrowseController.php") echo "5d7930d135562a5cd8bfacddf571819860a55d2c";;
    "resources/views/frontend/detail.blade.php") echo "df7897dfd6dba60b6debe9be2d6a63e1c5f1d4ff";;
    "resources/js/player.js") echo "3fdba292171441f30bdd40e48cf06c8d5ee57bc8";;
    "resources/css/app.css") echo "56669559c6adebe1b997a0ecbd59c460fe49f461";;
    *) return 1;;
  esac
}

echo "========== GOMOVIE WATCH PAGE UPGRADE =========="
echo "MODE=$MODE"
test "$(id -u)" -eq 0
test -d "$APP"
test ! -L "$APP"
test "$(realpath -e "$APP")" = "$APP"
test -x "$PHP"
test -f "$APP/.env"
test ! -L "$APP/.env"
test -f "$APP/artisan"
test -f "$APP/storage/app/private/.gomovie-installed"
test -f "$APP/public/build/manifest.json"
test ! -L "$APP/public/build/manifest.json"
id www >/dev/null
for cmd in git gh unzip sha256sum curl python3; do command -v "$cmd" >/dev/null; done

echo "========== VERIFY CURRENT SOURCE FILES =========="
while IFS= read -r file; do
  test -f "$APP/$file"
  test ! -L "$APP/$file"
  current="$(git hash-object "$APP/$file")"
  if [ "$current" != "$(old_blob "$file")" ] &&
     [ "$current" != "$(new_blob "$file")" ]; then
    echo "BLOCKED: Unexpected live file: $file"
    echo "CURRENT_BLOB=$current"
    exit 1
  fi
  echo "SOURCE_VERIFIED=$file"
done < <(files)

if [ "$MODE" = "--check" ]; then
  echo "GOMOVIE_WATCH_PREFLIGHT=PASS"
  echo "GOMOVIE_DATABASE_CHANGED=NO"
  echo "GOMOVIE_LIVE_FILES_CHANGED=NO"
  exit 0
fi

echo "========== VERIFY IMMUTABLE GITHUB WORKFLOW =========="
RUN_HEAD="$(gh api "repos/$REPO/actions/runs/$BUILD_RUN" --jq '.head_sha')"
RUN_CONCLUSION="$(gh api "repos/$REPO/actions/runs/$BUILD_RUN" --jq '.conclusion')"
test "$RUN_HEAD" = "$BUILD_SHA"
test "$RUN_CONCLUSION" = "success"
test "$(gh api "repos/$REPO/commits/$SOURCE_SHA" --jq '.sha')" = "$SOURCE_SHA"

BACKUP="$(mktemp -d /root/Gomovie-Watch-Upgrade-XXXXXXXX)"
chmod 700 "$BACKUP"
mkdir -p "$BACKUP/original" "$BACKUP/new" "$BACKUP/package" "$BACKUP/build"
echo "BACKUP_DIRECTORY=$BACKUP"
while IFS= read -r file; do
  mkdir -p "$BACKUP/original/$(dirname "$file")"
  cp -p "$APP/$file" "$BACKUP/original/$file"
done < <(files)
cp -p "$APP/public/build/manifest.json" "$BACKUP/original/manifest.json"

HOME_BEFORE="$(sha256sum "$APP/resources/views/frontend/home.blade.php" | awk '{print $1}')"
APP_LAYOUT_BEFORE="$(sha256sum "$APP/resources/views/layouts/app.blade.php" | awk '{print $1}')"
AUTH_BEFORE="$(sha256sum "$APP/resources/views/layouts/auth.blade.php" | awk '{print $1}')"
ADMIN_BEFORE="$(sha256sum "$APP/resources/views/layouts/admin.blade.php" | awk '{print $1}')"

echo "========== DOWNLOAD CHECKSUMMED CI BUILD =========="
gh run download "$BUILD_RUN" --repo "$REPO" \
  --name "$BUILD_NAME" --dir "$BACKUP/package"

ZIP_COUNT="$(find "$BACKUP/package" -maxdepth 3 -type f -name 'Gomovie-Installer-QA-*.zip' | wc -l)"
test "$ZIP_COUNT" -eq 1
ZIP="$(find "$BACKUP/package" -maxdepth 3 -type f -name 'Gomovie-Installer-QA-*.zip' -print -quit)"
test -n "$ZIP"
test -f "$ZIP.sha256"

read -r EXPECTED_ZIP_HASH _ < "$ZIP.sha256"
ACTUAL_ZIP_HASH="$(sha256sum "$ZIP" | awk '{print $1}')"
test "$EXPECTED_ZIP_HASH" = "$ACTUAL_ZIP_HASH"
unzip -tqq "$ZIP"
echo "CI_PACKAGE_SHA256=VERIFIED"
echo "CI_PACKAGE_COMMIT=$BUILD_SHA"

echo "========== EXTRACT ONLY WATCH SOURCES AND BUILT ASSETS =========="
while IFS= read -r file; do
  mkdir -p "$BACKUP/new/$(dirname "$file")"
  unzip -p "$ZIP" "$file" > "$BACKUP/new/$file"
  test "$(git hash-object "$BACKUP/new/$file")" = "$(new_blob "$file")"
  echo "TARGET_SOURCE=VERIFIED:$file"
done < <(files)

unzip -q "$ZIP" 'public/build/*' -d "$BACKUP/build"
test -f "$BACKUP/build/public/build/manifest.json"
test -z "$(find "$BACKUP/build/public/build" -type l -print -quit)"
"$PHP" -l "$BACKUP/new/app/Http/Controllers/Frontend/BrowseController.php"
grep -Fq 'gm-watch-toolbar-compact' "$BACKUP/new/resources/views/frontend/detail.blade.php"
grep -Fq 'All Episodes' "$BACKUP/new/resources/views/frontend/detail.blade.php"
grep -Fq 'gomovie:autoplay-next' "$BACKUP/new/resources/js/player.js"
grep -Fq 'gm-watch-player-tools' "$BACKUP/new/resources/css/app.css"
grep -RIlF 'gomovie:autoplay-next' "$BACKUP/build/public/build/assets" >/dev/null

CHANGED=0
COMPLETE=0
rollback() {
  if [ "$CHANGED" -eq 1 ] && [ "$COMPLETE" -eq 0 ]; then
    set +e
    echo "========== ROLLING BACK WATCH FILES =========="
    while IFS= read -r file; do
      cp -p "$BACKUP/original/$file" "$APP/$file"
    done < <(files)
    cp -p "$BACKUP/original/manifest.json" "$APP/public/build/manifest.json"
    (
      cd "$APP"
      runuser -u www -- "$PHP" artisan view:clear
    ) >/dev/null 2>&1
    echo "GOMOVIE_WATCH_UPGRADE=ROLLED_BACK"
  fi
}
trap rollback EXIT

echo "========== INSTALL COMPILED ASSETS FIRST =========="
CHANGED=1
BUILD_DIR="$BACKUP/build/public/build"
while IFS= read -r -d '' asset; do
  relative="$(realpath --relative-to="$BUILD_DIR" "$asset")"
  destination="$APP/public/build/$relative"
  install -d -o root -g www -m 0755 "$(dirname "$destination")"
  install -o root -g www -m 0644 "$asset" "$destination"
done < <(find "$BUILD_DIR" -type f ! -name manifest.json -print0)

echo "========== INSTALL WATCH SOURCE FILES =========="
while IFS= read -r file; do
  next="$APP/$file.gomovie-watch-next"
  test ! -e "$next"
  install -o root -g www -m 0644 "$BACKUP/new/$file" "$next"
  mv -- "$next" "$APP/$file"
done < <(files)

echo "========== INSTALL COMPILED MANIFEST ATOMICALLY =========="
NEW_MANIFEST="$APP/public/build/manifest.json.gomovie-watch-next"
test ! -e "$NEW_MANIFEST"
install -o root -g www -m 0644 "$BUILD_DIR/manifest.json" "$NEW_MANIFEST"
mv -- "$NEW_MANIFEST" "$APP/public/build/manifest.json"

echo "========== CLEAR COMPILED VIEWS =========="
(
  cd "$APP"
  runuser -u www -- "$PHP" artisan view:clear
)

echo "========== VERIFY LIVE WATCH PAGE =========="
RESPONSE="$BACKUP/watch-response.html"
WATCH_URL="https://$HOST/watch/series/the-flash-60735"
CODE="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
  --resolve "$HOST:443:127.0.0.1" -o "$RESPONSE" \
  -w '%{http_code}' "$WATCH_URL")"
echo "WATCH_PAGE_HTTP=$CODE"
test "$CODE" = "200"
grep -Fq 'gm-watch-toolbar-compact' "$RESPONSE"
grep -Fq 'All Episodes' "$RESPONSE"
! grep -Fq 'gm-watch-breadcrumb' "$RESPONSE"
! grep -Fq 'Now exploring' "$RESPONSE"

for route in / /login /register /browse; do
  code="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" -o /dev/null \
    -w '%{http_code}' "https://$HOST$route")"
  echo "ROUTE=$route HTTP=$code"
  test "$code" = "200"
done

echo "========== VERIFY UNCHANGED LAYOUTS =========="
test "$(sha256sum "$APP/resources/views/frontend/home.blade.php" | awk '{print $1}')" = "$HOME_BEFORE"
test "$(sha256sum "$APP/resources/views/layouts/app.blade.php" | awk '{print $1}')" = "$APP_LAYOUT_BEFORE"
test "$(sha256sum "$APP/resources/views/layouts/auth.blade.php" | awk '{print $1}')" = "$AUTH_BEFORE"
test "$(sha256sum "$APP/resources/views/layouts/admin.blade.php" | awk '{print $1}')" = "$ADMIN_BEFORE"
while IFS= read -r file; do
  test "$(git hash-object "$APP/$file")" = "$(new_blob "$file")"
done < <(files)
cmp -s "$BUILD_DIR/manifest.json" "$APP/public/build/manifest.json"

COMPLETE=1
echo "========== FINAL STATUS =========="
echo "GOMOVIE_WATCH_UI=DEPLOYED"
echo "GOMOVIE_COMPILED_PLAYER=DEPLOYED"
echo "GOMOVIE_PUBLISHED_EPISODES=DISPLAY_ONLY"
echo "GOMOVIE_LICENSE_GATE=UNCHANGED"
echo "GOMOVIE_HOMEPAGE_HEADER_FOOTER_AUTH=UNCHANGED"
echo "GOMOVIE_DATABASE_CHANGED=NO"
echo "GOMOVIE_DEPLOYMENT=SUCCESS"
echo "BACKUP_DIRECTORY=$BACKUP"
