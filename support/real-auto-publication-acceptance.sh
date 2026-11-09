#!/usr/bin/env bash
# Gomovie first REAL auto-publication acceptance: self-generated original MP4.
# Runs the *actual* production job in a unique isolated one-shot queue.
# Overrides config only in the CLI worker process. Never changes live .env,
# cache, systemd units, public CDN, existing catalog or uploaded videos.
set -Eeuo pipefail
umask 077

if [ "$#" -ne 2 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
  echo "Usage: bash real-auto-publication-acceptance.sh --check|--apply /root/verified-qa.php"
  exit 2
fi
MODE="$1"
SRC="$2"
APP="/www/wwwroot/gomovie.tonsamith.com"
PHP="/www/server/php/85/bin/php"
EXPECTED_PHP_BLOB="a9b6d5b30db17ef9e53b401587490f3f5ef4771f"
UNIT="gomovie-media-worker.service"

echo "========== GOMOVIE REAL AUTOMATIC PUBLICATION ACCEPTANCE =========="
echo "MODE=$MODE"
test "$(id -u)" -eq 0
test -d "$APP" && test ! -L "$APP"
test -f "$APP/artisan" && test ! -L "$APP/artisan"
test -f "$APP/.env" && test ! -L "$APP/.env"
test -f "$APP/vendor/autoload.php" && test ! -L "$APP/vendor/autoload.php"
test -x "$PHP"
test -f "$SRC" && test ! -L "$SRC"
for program in git runuser systemctl timeout mktemp install chmod chown rm rmdir; do
  command -v "$program" >/dev/null
done
id www >/dev/null
test "$(git hash-object "$SRC")" = "$EXPECTED_PHP_BLOB"
"$PHP" -l "$SRC" >/dev/null
systemctl is-active --quiet "$UNIT"
systemctl is-enabled --quiet "$UNIT"

echo "========== VERIFY NO PRODUCTION FEATURE ACTIVATION =========="
(
  cd "$APP"
  runuser -u www -- "$PHP" -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    foreach (["automatic_publication_enabled", "public_hls_enabled", "transcoding_enabled"] as $flag) {
      if (config("gomovie.".$flag) === true) {
        fwrite(STDERR, "BLOCKED: ".$flag." is enabled in production.\n");
        exit(11);
      }
    }
    foreach (["movies", "content_rights", "media_ingests", "media_assets",
      "public_media_files", "jobs", "users", "admin_audit_events"] as $table) {
      if (!\Illuminate\Support\Facades\Schema::hasTable($table)) {
        fwrite(STDERR, "BLOCKED: table ".$table." is missing.\n");
        exit(12);
      }
    }
    if (!\Illuminate\Support\Facades\DB::table("users")
      ->whereIn("role", ["admin", "super_admin"])
      ->where("is_active", true)
      ->whereNotNull("email_verified_at")->exists()) {
      fwrite(STDERR, "BLOCKED: verified administrator account unavailable.\n");
      exit(13);
    }
    if (config("gomovie.media_queue_connection") !== "database_media") exit(14);
    echo "LIVE_PUBLICATION=DISABLED\n";
    echo "LIVE_TRANSCODING=DISABLED\n";
    echo "VERIFIED_ADMIN_AVAILABLE=YES\n";
    echo "QA_DATABASE_TABLES=READY\n";
  '
)
echo "PERSISTENT_MEDIA_WORKER=ACTIVE"
echo "QA_PROCESSING_MODE=ISOLATED_ONE_SHOT"

echo "========== VERIFY PROCESS-SCOPED CONFIGURATION =========="
(
  cd "$APP"
  runuser -u www -- env \
    GOMOVIE_AUTOMATIC_PUBLICATION_ENABLED=true \
    GOMOVIE_PUBLIC_HLS_ENABLED=true \
    GOMOVIE_TRANSCODING_ENABLED=true \
    "$PHP" -r '
      require "vendor/autoload.php";
      $app = require "bootstrap/app.php";
      $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
      foreach (["automatic_publication_enabled", "public_hls_enabled", "transcoding_enabled"] as $flag) {
        if (config("gomovie.".$flag) !== true) {
          fwrite(STDERR, "BLOCKED: isolated setting ".$flag." was ignored by configuration cache.\n");
          exit(17);
        }
      }
      echo "QA_ONLY_CONFIG_OVERRIDES=VERIFIED\n";
    '
)

if [ "$MODE" = --check ]; then
  echo "REAL_AUTO_PUBLICATION_PREFLIGHT=PASS"
  echo "PRODUCTION_FILES_CHANGED=NO"
  echo "DATABASE_CHANGED=NO"
  echo "MEDIA_CHANGED=NO"
  echo "PUBLICATION_ACTIVATED=NO"
  exit 0
fi

echo "========== CREATE ISOLATED TEMPORARY QA WORKSPACE =========="
QA="$(mktemp -d /tmp/gomovie-autopub-qa.XXXXXXXX)"
case "$QA" in /tmp/gomovie-autopub-qa.????????) ;; *) echo "BLOCKED_UNEXPECTED_QA_WORKSPACE=$QA"; exit 2;; esac
test -d "$QA" && test ! -L "$QA"
chown www:www "$QA"
chmod 0700 "$QA"
install -o www -g www -m 0600 "$SRC" "$QA/acceptance.php"

# A failure cannot accidentally leave an unrestricted test movie. Cleanup
# deletes only records/files identified by the unique QA state and fixture slug.
COMPLETE=NO
cleanup() {
  result=$?
  set +e
  echo "========== CLEANUP GENERATED ORIGINAL QA FIXTURE =========="
  if [ -f "$QA/acceptance.php" ]; then
    (cd "$APP" && runuser -u www -- "$PHP" "$QA/acceptance.php" cleanup "$QA")
    cleaned=$?
  else
    cleaned=1
  fi
  if [ "$cleaned" -eq 0 ]; then
    rm -f -- "$QA/original.mp4" "$QA/acceptance.php" "$QA/state.json"
    rmdir -- "$QA"
    echo "TEMPORARY_QA_DIRECTORY=REMOVED"
  else
    echo "QA_CLEANUP=FAILED"
    echo "QA_RECOVERY_WORKSPACE=$QA"
    result=1
  fi
  if [ "$COMPLETE" = YES ] && [ "$result" -eq 0 ] && [ "$cleaned" -eq 0 ]; then
    echo "GOMOVIE_REAL_AUTO_PUBLIC_ACCEPTANCE=PASS"
    echo "PUBLICATION_ACTIVATED_GLOBALLY=NO"
    echo "EXISTING_PRODUCTION_VIDEOS=UNCHANGED"
    echo "SYSTEMD_WORKER=UNCHANGED"
  else
    echo "GOMOVIE_REAL_AUTO_PUBLIC_ACCEPTANCE=FAILED"
  fi
  exit "$result"
}
trap cleanup EXIT

echo "========== CREATE ORIGINAL MP4, TEST TITLE AND DOCUMENTED QA RIGHTS =========="
(
  cd "$APP"
  if ! runuser -u www -- "$PHP" "$QA/acceptance.php" check "$QA"; then
    echo "QA_APP_PREFLIGHT=FAILED"
    exit 65
  fi
  if ! runuser -u www -- "$PHP" "$QA/acceptance.php" setup "$QA"; then
    echo "QA_UPLOAD_SETUP=FAILED"
    exit 66
  fi
) || exit 67

echo "========== IDENTIFY UNIQUE QA QUEUE =========="
QUEUE="$(cd "$APP" && runuser -u www -- "$PHP" "$QA/acceptance.php" queue "$QA")"
if [[ ! "$QUEUE" =~ ^gomovie-qa-[a-f0-9]{16}$ ]]; then
  echo "BLOCKED_UNEXPECTED_QA_QUEUE=$QUEUE"
  exit 2
fi
echo "QA_QUEUE=ISOLATED"

echo "========== RUN REAL AUTOMATIC-PUBLICATION QUEUE JOB =========="
(
  cd "$APP"
  # Normal production worker consumes only gomovie-media. This one-off worker
  # consumes only the QA job and receives flags solely as process environment.
  if ! timeout --signal=TERM --kill-after=10s 220s \
    runuser -u www -- env \
      GOMOVIE_AUTOMATIC_PUBLICATION_ENABLED=true \
      GOMOVIE_PUBLIC_HLS_ENABLED=true \
      GOMOVIE_TRANSCODING_ENABLED=true \
      "$PHP" artisan queue:work database_media \
        --queue="$QUEUE" --once --tries=1 --timeout=180 --sleep=0 --no-interaction; then
    echo "QA_ISOLATED_QUEUE_WORKER=FAILED"
    exit 68
  fi
) || exit 69

echo "========== VERIFY REAL SCAN, HLS, GUEST ROUTE AND RIGHTS REVOCATION =========="
(
  cd "$APP"
  if ! runuser -u www -- env \
    GOMOVIE_AUTOMATIC_PUBLICATION_ENABLED=true \
    GOMOVIE_PUBLIC_HLS_ENABLED=true \
    GOMOVIE_TRANSCODING_ENABLED=true \
    "$PHP" "$QA/acceptance.php" verify "$QA"; then
    echo "QA_AUTOMATIC_PUBLICATION_VERIFICATION=FAILED"
    exit 70
  fi
) || exit 71

echo "========== RECHECK GLOBAL CONFIG AND PRODUCTION WORKER =========="
systemctl is-active --quiet "$UNIT"
systemctl is-enabled --quiet "$UNIT"
(
  cd "$APP"
  runuser -u www -- "$PHP" -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    foreach (["automatic_publication_enabled", "public_hls_enabled", "transcoding_enabled"] as $flag) {
      if (config("gomovie.".$flag) === true) exit(21);
    }
    echo "LIVE_PUBLICATION_SWITCHES=STILL_OFF\n";
  '
)
COMPLETE=YES
echo "REAL_QA_TEST=PASSED_BEFORE_CLEANUP"
