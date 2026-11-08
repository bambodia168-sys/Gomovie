#!/usr/bin/env bash
# HTTP installer acceptance against a disposable local MySQL CI database.
set -Eeuo pipefail
umask 077
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
QA="$(mktemp -d)"
PORT=18737
SERVER_PID=""
cleanup() {
  if [[ -n "$SERVER_PID" ]]; then kill "$SERVER_PID" 2>/dev/null || true; wait "$SERVER_PID" 2>/dev/null || true; fi
  rm -rf -- "$QA"
}
trap cleanup EXIT
for dir in app bootstrap config database public resources routes storage support; do
  cp -a "$ROOT/$dir" "$QA/"
done
cp "$ROOT"/{artisan,composer.json,.env.example} "$QA/"
ln -s "$ROOT/vendor" "$QA/vendor"
test ! -e "$QA/.env"
test -f "$QA/public/build/manifest.json"

php -S "127.0.0.1:$PORT" -t "$QA/public" "$QA/public/index.php" > "$QA/http.log" 2>&1 &
SERVER_PID="$!"
URL="http://127.0.0.1:$PORT"
for _ in {1..40}; do
  if curl -sS -o "$QA/status" "$URL/installer/" 2>/dev/null; then break; fi
  sleep 0.25
done
test -s "$QA/status"
grep -q 'Step 1' "$QA/status"
test -f "$QA/storage/framework/installer/owner-key"
KEY="$(tr -d '\r\n' < "$QA/storage/framework/installer/owner-key")"
test "$(printf '%s' "$KEY" | wc -c)" = 64

JAR="$QA/cookies"
PAGE="$QA/page.html"
csrf() { grep -oE 'name="csrf" value="[0-9a-f]{64}"' "$PAGE" | head -n1 | cut -d '"' -f 4; }
curl -fsS -c "$JAR" -b "$JAR" "$URL/installer/" -o "$PAGE"
CSRF="$(csrf)"
test "$(printf '%s' "$CSRF" | wc -c)" = 64

curl -fsS -c "$JAR" -b "$JAR" -o "$PAGE" -X POST "$URL/installer/" \
  --data-urlencode "csrf=$CSRF" --data-urlencode 'action=access' \
  --data-urlencode "installer_key=$KEY"
grep -q 'Step 2' "$PAGE"

curl -fsS -c "$JAR" -b "$JAR" -o "$PAGE" -X POST "$URL/installer/" \
  --data-urlencode "csrf=$CSRF" --data-urlencode 'action=application' \
  --data-urlencode "app_url=$URL" --data-urlencode 'purchase_code=QA-CODE-NOT-VERIFIED'
grep -q 'Step 3' "$PAGE"

curl -fsS -c "$JAR" -b "$JAR" -o "$PAGE" -X POST "$URL/installer/" \
  --data-urlencode "csrf=$CSRF" --data-urlencode 'action=database' \
  --data-urlencode 'driver=mysql' --data-urlencode 'host=127.0.0.1' \
  --data-urlencode 'port=3306' --data-urlencode 'database=gomovie_install_ci' \
  --data-urlencode 'username=gomovie_installer' --data-urlencode 'password=installer_test_only'
grep -q 'Step 4' "$PAGE"

FINAL="$(curl -sS -c "$JAR" -b "$JAR" -o "$PAGE" -w '%{http_code}' \
  -X POST "$URL/installer/" \
  --data-urlencode "csrf=$CSRF" --data-urlencode 'action=install' \
  --data-urlencode 'name=QA Initial Administrator' \
  --data-urlencode 'email=qa-admin@example.test' \
  --data-urlencode 'password=Strong-QA-Install-123' \
  --data-urlencode 'password_confirmation=Strong-QA-Install-123' \
  --data-urlencode 'confirm_install=yes')"
if [[ "$FINAL" != "303" ]]; then
  echo "SETUP_HTTP_INSTALL=FAIL status:$FINAL"
  if grep -q 'class="error"' "$PAGE"; then
    sed -n '/class="error"/p' "$PAGE" | cut -c1-220
  fi
  tail -15 "$QA/http.log" | sed -E 's/(password|secret|key)=[^ ]+/\1=[REDACTED]/gi' || true
  exit 1
fi

curl -fsS -c "$JAR" -b "$JAR" "$URL/installer/" -o "$PAGE"
grep -q 'Gomovie is installed' "$PAGE"
test -f "$QA/.env"
test -f "$QA/storage/app/private/.gomovie-installed"
test ! -e "$QA/storage/framework/installer/owner-key"

QA_ENV_FILE="$QA/.env" php -r '
$file = file_get_contents(getenv("QA_ENV_FILE"));
if (!preg_match("/^APP_KEY=\"base64:[^\"]+\"/m", $file) ||
    !str_contains($file, "GOMOVIE_BILLING_ENABLED=\"false\"") ||
    !str_contains($file, "GOMOVIE_EDGE_ENABLED=\"false\"")) exit(1);
'
QA_DB_USER='gomovie_installer' QA_DB_PASSWORD='installer_test_only' php -r '
$db = new PDO("mysql:host=127.0.0.1;port=3306;dbname=gomovie_install_ci",
    getenv("QA_DB_USER"), getenv("QA_DB_PASSWORD"));
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
if ((int)$db->query("SELECT COUNT(*) FROM users WHERE role = '\''super_admin'\''")->fetchColumn() !== 1 ||
    (int)$db->query("SELECT COUNT(*) FROM admin_audit_events WHERE action = '\''server.initial_owner_installed'\''")->fetchColumn() !== 1 ||
    (int)$db->query("SELECT COUNT(*) FROM migrations")->fetchColumn() < 8) exit(1);
'
# A fresh visitor without the original session must be refused.
LOCK_CODE="$(curl -sS -o "$QA/locked" -w '%{http_code}' "$URL/installer/")"
test "$LOCK_CODE" = "410"
LOGIN_CODE="$(curl -sS -o "$QA/login" -w '%{http_code}' "$URL/login")"
test "$LOGIN_CODE" = "200"
grep -q 'Sign in' "$QA/login"
ROOT_CODE="$(curl -sS -o "$QA/home" -w '%{http_code}' "$URL/")"
test "$ROOT_CODE" = "200"
echo "INSTALLER_HTTP_MYSQL_FRESH_INSTALL=PASS"
echo "INSTALLER_INITIAL_ADMIN_AND_AUDIT=PASS"
echo "INSTALLER_LOCK_AND_REENTRY_BLOCK=PASS"
echo "INSTALLER_FRONTEND_LANDING_AND_LOGIN=PASS"
echo "INDEPENDENT_AAPANEL_CPANEL_ACCEPTED=NO"
