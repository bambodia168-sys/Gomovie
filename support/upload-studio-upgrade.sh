#!/usr/bin/env bash
# Gomovie Upload Studio: selective aaPanel deployment only. NEVER replace the
# installed application, its .env, existing auth pages or private media.
set -Eeuo pipefail
umask 077
if [ "$#" -ne 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
  echo "Usage: bash upload-studio-upgrade.sh --check|--apply"
  exit 2
fi
MODE="$1"
APP="/www/wwwroot/gomovie.tonsamith.com"
PHP="/www/server/php/85/bin/php"
MYSQLDUMP="/www/server/mysql/bin/mysqldump"
REPO="bambodia168-sys/Gomovie"
# Release is pinned to the validated source commit. The helper can be updated
# independently without silently changing the deployed source.
SHA="4a89a9a4bdbf421bd4c671b1ac945ad8bbcbf332"

files() {
  printf '%s\n' \
  app/Http/Controllers/Admin/MediaController.php \
  app/Http/Controllers/Admin/UploadStudioController.php \
  app/Services/Media/PrivateVideoIntake.php \
  app/Services/Media/IntakeReviewService.php \
  app/Jobs/MirrorPrivateIngestToR2.php \
  resources/views/admin/upload-studio.blade.php \
  resources/views/admin/upload-review.blade.php \
  resources/views/admin/media.blade.php \
  resources/views/layouts/admin.blade.php \
  routes/web.php \
  config/gomovie.php \
  config/filesystems.php \
  composer.json \
  database/migrations/2026_11_09_000100_add_cloud_mirror_to_media_ingests.php
}
old_blob() {
  case "$1" in
    app/Http/Controllers/Admin/MediaController.php) echo 1bcd66b6a4ac4c382448c9b572351c41c5cc7133;;
    resources/views/admin/media.blade.php) echo a1ebb2c396a96aaaba16c5242a78b9f8d9fce968;;
    resources/views/layouts/admin.blade.php) echo 51f9ef903860bf2e5696e10669f1112a60ecc18b;;
    routes/web.php) echo b3dedebe5f032035f71d20904941053cd8d33f2d;;
    config/gomovie.php) echo 719b390a3de3608fd49dc186ced761f1818a4787;;
    config/filesystems.php) echo 37d8fca4f6f85da3a176a3668c537bc5e7fd29a7;;
    composer.json) echo 85dc654d9bf4404be21c2a039cc0845321fa1e5e;;
    *) echo NEW; ;;
  esac
}
new_blob() {
  case "$1" in
    app/Http/Controllers/Admin/MediaController.php) echo 663121af0db6bfc4917c99c2cf60885cc3ee28b4;;
    app/Http/Controllers/Admin/UploadStudioController.php) echo ec67e470dc4be266acec7a1be3a7b563c10cfea3;;
    app/Services/Media/PrivateVideoIntake.php) echo 0046013039f785afc7dbdfc2d2c3317256eb41bb;;
    app/Services/Media/IntakeReviewService.php) echo a99734c500631c69c4ba5f57aa5db0876892978c;;
    app/Jobs/MirrorPrivateIngestToR2.php) echo 8a6c9dc94600df9e13cff7fd22c665abd446a0ec;;
    resources/views/admin/upload-studio.blade.php) echo a3553b62ee4c3e0c4ec741f1787a952a852abdcb;;
    resources/views/admin/upload-review.blade.php) echo 311115d9d5e0ead52c323bc46507654928a90d24;;
    resources/views/admin/media.blade.php) echo 77c22ae2396c4b95d515f5677c81593f2df83ae1;;
    resources/views/layouts/admin.blade.php) echo b4f206d1a0397547822f63bcea50029e237b08c0;;
    routes/web.php) echo 7c926837a05d4e27cf3f411adcfec08af9baf98e;;
    config/gomovie.php) echo dd06feff774b2fe09d91681788ce6274eb7ccebb;;
    config/filesystems.php) echo 5f290c16b0c4f83d7946cd56abc5917873b89a1d;;
    composer.json) echo ed5d139638387dd487d3de244c88d4ae5218fbaf;;
    database/migrations/2026_11_09_000100_add_cloud_mirror_to_media_ingests.php) echo 250acacab52ad664733938e828b61f8a7f828bc5;;
    *) return 1;;
  esac
}
echo "========== GOMOVIE UPLOAD STUDIO =========="
echo "MODE=$MODE"
test "$(id -u)" = 0
test -d "$APP" && test ! -L "$APP"
test -f "$APP/.env" && test ! -L "$APP/.env"
test -f "$APP/artisan"
test -f "$APP/vendor/autoload.php" && test ! -L "$APP/vendor/autoload.php"
test -f "$APP/storage/app/private/.gomovie-installed"
test -x "$PHP"
test -x "$MYSQLDUMP"
for command_name in gh git curl gzip php runuser; do command -v "$command_name" >/dev/null; done
id www >/dev/null

echo "========== VERIFY EXISTING FILES =========="
while IFS= read -r file; do
  old="$(old_blob "$file")"
  new="$(new_blob "$file")"
  if [ "$old" = NEW ]; then
    if [ -e "$APP/$file" ]; then
      test -f "$APP/$file" && test ! -L "$APP/$file"
      current="$(git hash-object "$APP/$file")"
      test "$current" = "$new" || { echo "BLOCKED_CUSTOM_NEW_FILE=$file"; exit 1; }
    fi
  else
    test -f "$APP/$file" && test ! -L "$APP/$file"
    current="$(git hash-object "$APP/$file")"
    if [ "$current" != "$old" ] && [ "$current" != "$new" ]; then
      echo "BLOCKED_CUSTOM_LIVE_FILE=$file"
      echo "CURRENT_BLOB=$current"
      exit 1
    fi
  fi
  echo "SOURCE_VERIFIED=$file"
done < <(files)
if [ "$MODE" = "--check" ]; then
  echo "GOMOVIE_UPLOAD_PREFLIGHT=PASS"
  echo "LIVE_FILES_CHANGED=NO"
  echo "DATABASE_CHANGED=NO"
  exit 0
fi
echo "========== VERIFY GITHUB RELEASE =========="
test "$(gh api "repos/$REPO/commits/$SHA" --jq '.sha')" = "$SHA"
BACKUP="$(mktemp -d /root/Gomovie-Upload-Studio-XXXXXXXX)"
chmod 700 "$BACKUP"
mkdir -p "$BACKUP/old" "$BACKUP/new"
echo "BACKUP_DIRECTORY=$BACKUP"
while IFS= read -r file; do
  mkdir -p "$BACKUP/old/$(dirname "$file")" "$BACKUP/new/$(dirname "$file")"
  if [ -f "$APP/$file" ]; then cp -p "$APP/$file" "$BACKUP/old/$file"; fi
done < <(files)

echo "========== DOWNLOAD AND VERIFY ALL SOURCE BLOBS =========="
while IFS= read -r file; do
  gh api -H "Accept: application/vnd.github.raw+json" \
    "repos/$REPO/contents/$file?ref=$SHA" > "$BACKUP/new/$file"
  test "$(git hash-object "$BACKUP/new/$file")" = "$(new_blob "$file")"
  case "$file" in
    *.php) "$PHP" -l "$BACKUP/new/$file" >/dev/null;;
  esac
done < <(files)
echo "GITHUB_SOURCE=VERIFIED"

echo "========== SECURE DATABASE BACKUP (MYSQL) =========="
APP_PATH="$APP" BACKUP_PATH="$BACKUP" "$PHP" <<'PHP'
<?php
require_once getenv('APP_PATH').'/vendor/autoload.php';
$app=require getenv('APP_PATH').'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$conn=config('database.connections.'.config('database.default'));
if (($conn['driver']??null)!=='mysql') {
    fwrite(STDERR,"BLOCKED: This aaPanel upgrade script supports MySQL only.\n");
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
rm -f "$BACKUP/mysql.cnf" "$BACKUP/database-name"
echo "DATABASE_BACKUP=VERIFIED"

CHANGED=0
COMPLETE=0
rollback() {
  if [ "$CHANGED" = 1 ] && [ "$COMPLETE" = 0 ]; then
    set +e
    echo "========== RESTORE ORIGINAL SOURCE FILES =========="
    while IFS= read -r file; do
      if [ -f "$BACKUP/old/$file" ]; then
        cp -p "$BACKUP/old/$file" "$APP/$file"
      else
        rm -f -- "$APP/$file"
      fi
    done < <(files)
    ( cd "$APP" && runuser -u www -- "$PHP" artisan optimize:clear ) >/dev/null 2>&1
    echo "SOURCE_ROLLBACK=COMPLETE"
    echo "NOTE=Additive database migration may remain applied; backup retained."
  fi
}
trap rollback EXIT

echo "========== INSTALL ADDITIVE MIGRATION =========="
MIGRATION="database/migrations/2026_11_09_000100_add_cloud_mirror_to_media_ingests.php"
install -o root -g www -m 0644 "$BACKUP/new/$MIGRATION" "$APP/$MIGRATION"
CHANGED=1
(
  cd "$APP"
  runuser -u www -- "$PHP" artisan migrate \
    --path="$MIGRATION" --force --no-interaction
)
echo "DATABASE_MIGRATION=APPLIED"

echo "========== INSTALL UPLOAD STUDIO ONLY =========="
while IFS= read -r file; do
  if [ "$file" = "$MIGRATION" ]; then continue; fi
  install -o root -g www -m 0644 "$BACKUP/new/$file" "$APP/$file"
done < <(files)
(
  cd "$APP"
  runuser -u www -- "$PHP" artisan optimize:clear
  runuser -u www -- "$PHP" artisan route:list --path=dashboard/upload-studio --compact 2>/dev/null ||
  runuser -u www -- "$PHP" artisan route:list --path=dashboard/upload-studio
)
echo "========== VERIFY PUBLIC SITE AND AUTH UNCHANGED =========="
for path in / /login /register /browse /watch/series/the-flash-60735; do
  code="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
    --resolve gomovie.tonsamith.com:443:127.0.0.1 \
    -o /dev/null -w '%{http_code}' "https://gomovie.tonsamith.com$path")"
  echo "ROUTE=$path HTTP=$code"
  test "$code" = 200
done
while IFS= read -r file; do
  test "$(git hash-object "$APP/$file")" = "$(new_blob "$file")"
done < <(files)

COMPLETE=1
echo "========== FINAL STATUS =========="
echo "UPLOAD_STUDIO=INSTALLED"
echo "VIDEO_INTAKE=PRIVATE_QUARANTINE"
echo "CLOUDFLARE_R2=OPTIONAL_DISABLED_BY_DEFAULT"
echo "TRANSCODING=UNCHANGED"
echo "STREAMING=UNCHANGED"
echo "PAYMENTS=UNCHANGED"
echo "HOMEPAGE_WATCH_AUTH=UNCHANGED"
echo "GOMOVIE_DEPLOYMENT=SUCCESS"
echo "BACKUP_DIRECTORY=$BACKUP"
