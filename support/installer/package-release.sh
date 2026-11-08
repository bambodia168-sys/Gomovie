#!/usr/bin/env bash
# Build a customer-ready installer ZIP from a clean CI checkout with already
# installed production Composer dependencies and compiled Vite assets.
set -Eeuo pipefail
umask 077

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
OUT="$ROOT/dist"
TEMP="$(mktemp -d)"
trap 'rm -rf -- "$TEMP"' EXIT
STAGE="$TEMP/gomovie"
mkdir -p "$STAGE" "$OUT"

test -f "$ROOT/vendor/autoload.php"
test -f "$ROOT/public/build/manifest.json"
test -f "$ROOT/composer.lock"
test -f "$ROOT/package-lock.json"
test ! -f "$ROOT/.env.production"

for dir in app bootstrap config database public resources routes storage vendor; do
  cp -a "$ROOT/$dir" "$STAGE/"
done
mkdir -p "$STAGE/support/installer"
cp -a "$ROOT/support/installer/InstallerEngine.php" "$STAGE/support/installer/"
for file in artisan composer.json composer.lock package.json package-lock.json .env.example LICENSE; do
  cp "$ROOT/$file" "$STAGE/$file"
done

cat > "$STAGE/INSTALL.txt" <<'INSTRUCTIONS'
GOMOVIE — CUSTOMER INSTALLATION
===============================
For a NEW, EMPTY, dedicated MySQL 8 or PostgreSQL 16 database.

1. Upload and extract the complete ZIP outside your website public directory.
2. Configure your aaPanel/cPanel web document root to Gomovie/public/.
   HTTPS and PHP 8.4+ are required. Never publish .env, vendor or private storage.
3. Open https://your-domain/installer/ in your browser.
4. In the hosting File Manager open storage/framework/installer/owner-key.
   Copy the secret into Step 1 of the installer; it is never shown publicly.
5. Complete the five steps:
   Requirements and ownership, Application URL/Purchase Code (optional,
   not verified), Database, Administrator and initial installation, Complete.
6. Remove public/installer/ using your hosting File Manager when setup succeeds.
7. Verify HTTPS, SMTP delivery, backup restoration, queue supervision,
   and real devices before you consider the instance ready for customers.

DO NOT upload this package over an existing installation. The installer
will refuse an existing .env, existing DB tables, or an installation lock.
Do NOT run migrate:fresh or production PHPUnit tests against customer data.

Streaming edge, DRM, payment processing and transcoding are disabled
until their independent compliance, security and provider acceptance.
INSTRUCTIONS

# No repo internals, test fixtures, credentials, Markdown docs, dev tooling
# or any .env file may enter the buyer distribution.
find "$STAGE" -type f -name '*.md' -delete
rm -rf "$STAGE/tests" "$STAGE/.github" "$STAGE/node_modules"
rm -f "$STAGE/public/hot" "$STAGE/.env" "$STAGE/.env.production"
test ! -e "$STAGE/storage/app/private/.gomovie-installed"
test ! -e "$STAGE/storage/framework/installer/owner-key"
test ! -e "$STAGE/storage/framework/installer/operation.lock"
test ! -e "$STAGE/storage/framework/installer/sessions"

find "$STAGE" -type d -exec chmod 755 {} +
find "$STAGE" -type f -exec chmod 644 {} +
test -f "$STAGE/public/installer/index.php"
test -f "$STAGE/support/installer/InstallerEngine.php"
test -f "$STAGE/public/build/manifest.json"
test -f "$STAGE/vendor/autoload.php"
test -f "$STAGE/composer.lock"
test -f "$STAGE/package-lock.json"

REV="$(git -C "$ROOT" rev-parse --short=12 HEAD)"
ARCHIVE="$OUT/Gomovie-Installer-QA-$REV.zip"
( cd "$STAGE" && zip -q -r "$ARCHIVE" . )
sha256sum "$ARCHIVE" > "$ARCHIVE.sha256"
if unzip -Z1 "$ARCHIVE" | grep -E '(^|/)(\.env|owner-key|operation\.lock|\.gomovie-installed)$|(^|/)(tests|node_modules)/|\.md$'; then
  echo "BLOCKED: prohibited file entered installer archive" >&2
  exit 1
fi
echo "INSTALLER_PACKAGE_STRUCTURE=PASS"
echo "INSTALLER_PACKAGE_SHA256=RECORDED"
echo "INSTALLER_PRODUCTION_HOST_ACCEPTED=NO"
echo "ZIP=$ARCHIVE"
