#!/usr/bin/env bash
# Gomovie: one persistent isolated media worker for aaPanel/systemd.
# Installs a service only; never edits Laravel, .env, the database, or videos.
set -Eeuo pipefail
umask 077

if [ "$#" -ne 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
  echo 'Usage: bash media-worker-service.sh --check|--apply' >&2
  exit 2
fi

MODE="$1"
APP="/www/wwwroot/gomovie.tonsamith.com"
PHP="/www/server/php/85/bin/php"
UNIT="/etc/systemd/system/gomovie-media-worker.service"
SERVICE="gomovie-media-worker.service"

echo '========== GOMOVIE ISOLATED MEDIA WORKER =========='
echo "MODE=$MODE"
test "$(id -u)" -eq 0
test -d "$APP" && test ! -L "$APP"
test -f "$APP/.env" && test ! -L "$APP/.env"
test -f "$APP/artisan" && test ! -L "$APP/artisan"
test -f "$APP/vendor/autoload.php" && test ! -L "$APP/vendor/autoload.php"
test -x "$PHP"
test -d /etc/systemd/system && test ! -L /etc/systemd/system
for command_name in systemctl runuser git sha256sum ps; do command -v "$command_name" >/dev/null; done
id www >/dev/null
test "$(ps -p 1 -o comm= | tr -d '[:space:]')" = systemd
runuser -u www -- "$PHP" -v >/dev/null

echo '========== VERIFY INSTALLED PUBLICATION INFRASTRUCTURE =========='
# This service is only valid after the previously tested additive release.
test -f "$APP/app/Jobs/AutoPublishUploadedVideo.php"
test ! -L "$APP/app/Jobs/AutoPublishUploadedVideo.php"
test "$(git hash-object "$APP/app/Jobs/AutoPublishUploadedVideo.php")" = 770e673ab4db60cf1553c7979fdb491259412c4e
test -f "$APP/config/queue.php" && test ! -L "$APP/config/queue.php"

(
  cd "$APP"
  runuser -u www -- "$PHP" -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $queue = (string)config("gomovie.media_queue_connection");
    if ($queue !== "database_media") {
      fwrite(STDERR, "BLOCKED: Only the verified database_media connection is supported.\n");
      exit(11);
    }
    if (config("queue.connections.database_media.driver") !== "database"
      || (int)config("queue.connections.database_media.retry_after") <= 7500
      || config("queue.connections.database_media.queue") !== "gomovie-media") {
      fwrite(STDERR, "BLOCKED: Media queue isolation or retry configuration is not safe.\n");
      exit(12);
    }
    if (!\Illuminate\Support\Facades\Schema::hasTable("jobs")
      || !\Illuminate\Support\Facades\Schema::hasTable("public_media_files")) {
      fwrite(STDERR, "BLOCKED: Queue or public-media schema is missing.\n");
      exit(13);
    }
    if (config("gomovie.automatic_publication_enabled") === true
      || config("gomovie.public_hls_enabled") === true
      || config("gomovie.transcoding_enabled") === true) {
      fwrite(STDERR, "BLOCKED: Worker bootstrap requires publication switches OFF.\n");
      exit(14);
    }
    echo "QUEUE_CONNECTION=database_media\n";
    echo "MEDIA_QUEUE=gomovie-media\n";
    echo "RETRY_AFTER_SAFE=YES\n";
    echo "AUTOMATIC_PUBLICATION=OFF\n";
    echo "PUBLIC_HLS=OFF\n";
    echo "TRANSCODING=OFF\n";
    echo "QUEUE_TABLES=READY\n";
  '
)

echo '========== VERIFY UNIT COMPATIBILITY =========='
UNIT_TEXT="$(cat <<'SYSTEMD'
[Unit]
Description=Gomovie dedicated private video processing worker
After=network-online.target mariadb.service mysql.service
Wants=network-online.target
StartLimitIntervalSec=300
StartLimitBurst=10

[Service]
Type=simple
User=www
Group=www
WorkingDirectory=/www/wwwroot/gomovie.tonsamith.com
UMask=0077
ExecStart=/www/server/php/85/bin/php /www/wwwroot/gomovie.tonsamith.com/artisan queue:work database_media --queue=gomovie-media --sleep=3 --tries=1 --timeout=7500 --no-interaction
Restart=always
RestartSec=5
TimeoutStopSec=7600
KillSignal=SIGTERM
LimitNOFILE=4096

[Install]
WantedBy=multi-user.target
SYSTEMD
)"
EXPECTED="$(printf '%s\n' "$UNIT_TEXT" | sha256sum | awk '{print $1}')"
if [ -e "$UNIT" ] || [ -L "$UNIT" ]; then
  test -f "$UNIT" && test ! -L "$UNIT"
  ACTUAL="$(sha256sum "$UNIT" | awk '{print $1}')"
  if [ "$ACTUAL" != "$EXPECTED" ]; then
    echo "BLOCKED_EXISTING_CUSTOM_UNIT=$UNIT"
    echo "CURRENT_SHA256=$ACTUAL"
    echo "EXPECTED_SHA256=$EXPECTED"
    exit 20
  fi
  echo 'SYSTEMD_UNIT=EXACT_MATCH'
  NEW_UNIT=NO
else
  echo 'SYSTEMD_UNIT=NOT_INSTALLED'
  NEW_UNIT=YES
fi

# Do not create a second worker beside a preexisting Supervisor/manual worker.
if [ "$NEW_UNIT" = YES ] && ps -eo args= | grep -E '[a]rtisan queue:work[[:space:]]+database_media' \
  | grep -Fq -- '--queue=gomovie-media'; then
  echo 'BLOCKED_EXISTING_MEDIA_WORKER=YES'
  exit 21
fi

if [ "$MODE" = --check ]; then
  echo 'GOMOVIE_MEDIA_WORKER_PREFLIGHT=PASS'
  echo 'UNIT_CHANGED=NO'
  echo 'WORKER_STARTED=NO'
  echo 'PUBLICATION_ACTIVATED=NO'
  exit 0
fi

# Do not silently replace systemd configuration created by a different administrator.
CREATED=0
COMPLETE=0
rollback() {
  if [ "$COMPLETE" -eq 0 ] && [ "$CREATED" -eq 1 ]; then
    set +e
    echo '========== RECOVER FAILED NEW SERVICE INSTALL =========='
    systemctl disable --now "$SERVICE" >/dev/null 2>&1
    rm -f -- "$UNIT"
    systemctl daemon-reload >/dev/null 2>&1
    echo 'NEW_SERVICE_ROLLBACK=COMPLETE'
  fi
}
trap rollback EXIT

echo '========== INSTALL OR VERIFY WORKER UNIT =========='
if [ "$NEW_UNIT" = YES ]; then
  NEXT="$(mktemp /etc/systemd/system/.gomovie-media-worker.XXXXXXXX)"
  chmod 0644 "$NEXT"
  printf '%s\n' "$UNIT_TEXT" > "$NEXT"
  test "$(sha256sum "$NEXT" | awk '{print $1}')" = "$EXPECTED"
  # Atomic unit activation; never overwrite an existing unit.
  test ! -e "$UNIT" && test ! -L "$UNIT"
  mv -n -- "$NEXT" "$UNIT"
  test -f "$UNIT" && test "$(sha256sum "$UNIT" | awk '{print $1}')" = "$EXPECTED"
  CREATED=1
fi

systemctl daemon-reload
systemctl enable --now "$SERVICE"
systemctl is-enabled --quiet "$SERVICE"
systemctl is-active --quiet "$SERVICE"
sleep 2
systemctl is-active --quiet "$SERVICE"

echo '========== VERIFY WORKER PROCESS =========='
if ! ps -eo args= | grep -E '[a]rtisan queue:work[[:space:]]+database_media' \
  | grep -Fq -- '--queue=gomovie-media'; then
  echo 'WORKER_PROCESS_NOT_FOUND=YES'
  exit 22
fi
echo 'MEDIA_WORKER=RUNNING'
echo 'MEDIA_WORKER_SERVICE=ENABLED'
echo 'ISOLATED_QUEUE=gomovie-media'
echo 'QUEUE_TIMEOUT_SECONDS=7500'
echo 'AUTOMATIC_PUBLICATION=UNCHANGED_OFF'
echo 'PUBLIC_HLS=UNCHANGED_OFF'
echo 'TRANSCODING=UNCHANGED_OFF'
echo 'APPLICATION_FILES_CHANGED=NO'
echo 'DATABASE_CHANGED=NO'
echo 'PRIVATE_VIDEOS_CHANGED=NO'
COMPLETE=1
echo 'GOMOVIE_MEDIA_WORKER_SERVICE=SUCCESS'
