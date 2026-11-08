#!/usr/bin/env bash
# Synthetic SQLite installation/restore drill ONLY. Never accepts a live
# database path or a source database argument from the operator.
set -Eeuo pipefail
umask 077

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TEMP="$(mktemp -d)"
trap 'rm -rf "$TEMP"' EXIT

SOURCE_DB="$TEMP/new-install.sqlite"
BACKUP_DB="$TEMP/offline-backup.sqlite"
RESTORED_DB="$TEMP/restored.sqlite"
: > "$SOURCE_DB"

cd "$ROOT"
export APP_ENV=testing DB_CONNECTION=sqlite CACHE_STORE=array
export QUEUE_CONNECTION=sync SESSION_DRIVER=array MAIL_MAILER=array
export GOMOVIE_EDGE_ENABLED=false GOMOVIE_BILLING_ENABLED=false
export GOMOVIE_TRANSCODING_ENABLED=false GOMOVIE_MEDIA_STAGING_ENABLED=false

DB_DATABASE="$SOURCE_DB" php artisan migrate --force --no-interaction --no-ansi >/dev/null

php -r '
$db = new PDO("sqlite:".$argv[1]);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("INSERT INTO genres (name, slug, created_at, updated_at)
           VALUES (\"Restore Evidence\", \"synthetic-restore-evidence\", CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
' "$SOURCE_DB"

# Deliberately offline: the test database is not held open for writes.
cp -- "$SOURCE_DB" "$BACKUP_DB"
BEFORE="$(sha256sum "$BACKUP_DB" | cut -d' ' -f1)"
cp -- "$BACKUP_DB" "$RESTORED_DB"
AFTER="$(sha256sum "$RESTORED_DB" | cut -d' ' -f1)"
test "$BEFORE" = "$AFTER"

DB_DATABASE="$RESTORED_DB" php artisan migrate:status --no-ansi >/dev/null
php -r '
$db = new PDO("sqlite:".$argv[1]);
$st = $db->prepare("SELECT COUNT(*) FROM genres WHERE slug = ?");
$st->execute(["synthetic-restore-evidence"]);
if ((int) $st->fetchColumn() !== 1) {
    fwrite(STDERR, "Synthetic restore assertion failed.\n");
    exit(1);
}
' "$RESTORED_DB"

echo 'SYNTHETIC_SQLITE_FRESH_INSTALL=PASS'
echo 'SYNTHETIC_SQLITE_OFFLINE_BACKUP_INTEGRITY=PASS'
echo 'SYNTHETIC_SQLITE_RESTORED_SCHEMA_AND_DATA=PASS'
echo 'PRODUCTION_OR_OFFSITE_RESTORATION_VERIFIED=NO'
