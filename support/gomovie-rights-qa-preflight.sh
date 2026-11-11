#!/usr/bin/env bash
# Read-only by default; intentional EICAR test only with --scan-eicar.
set -Eeuo pipefail
umask 077
[[ "$#" == 2 ]] || { echo 'USAGE: script --check|--scan-eicar /absolute/isolated-qa-app' >&2; exit 2; }
MODE="$1"
APP="$2"
case "$MODE" in --check|--scan-eicar) ;; *) echo 'INVALID_MODE' >&2; exit 2;; esac
[[ "$APP" == /* && -d "$APP" && ! -L "$APP" ]] ||
  { echo 'BLOCKED: Existing absolute QA checkout required.' >&2; exit 2; }
APP="$(realpath -- "$APP")"
case "$APP" in *qa*|*QA*|*staging*|*acceptance*) ;; *)
  echo 'BLOCKED: Checkout must be named qa, staging or acceptance.' >&2; exit 2 ;;
esac
case "$APP" in /www/wwwroot/gomovie.tonsamith.com|/www/wwwroot/gomovie.tonsamith.com/*)
  echo 'BLOCKED: This is the live GoMovie root.' >&2; exit 2 ;;
esac
[[ -f "$APP/artisan" && -f "$APP/.env" && ! -L "$APP/.env" ]] ||
  { echo 'BLOCKED: isolated Laravel QA installation and local .env required.' >&2; exit 2; }
ENVIRONMENT="$(sed -n -E "s/^APP_ENV=['\"]?([^'\"#]+)['\"]?.*/\1/p" "$APP/.env" | head -n1 | tr -d '[:space:]')"
case "$ENVIRONMENT" in testing|staging|local|qa) ;; *)
  echo 'BLOCKED: APP_ENV must identify an isolated non-production environment.' >&2; exit 2 ;;
esac
if grep -Eiq '^[[:space:]]*GOMOVIE_(AUTOMATIC_PUBLICATION_ENABLED|PUBLIC_HLS_ENABLED)=(true|1|on)' "$APP/.env"; then
  echo 'BLOCKED: public streaming and automatic publication must be disabled in QA.' >&2; exit 2
fi
echo '========== GOMOVIE RIGHTS ISOLATED QA PREFLIGHT =========='
echo "MODE=$MODE"
echo 'QA_ISOLATION=PATH_AND_ENV_CHECKED'
PHP="$(command -v php)"
[[ -x "$PHP" ]] || { echo 'BLOCKED: PHP executable not found.' >&2; exit 2; }
"$PHP" -v | head -n1
"$PHP" -m | grep -E '^(fileinfo|mbstring|openssl|pdo_mysql|pdo_pgsql|pdo_sqlite)$' | sort || true
for file in \
  app/Services/Rights/RightsEvidenceService.php \
  app/Services/Rights/ReviewedDistributionRights.php \
  app/Http/Controllers/Admin/RightsEvidenceController.php \
  resources/views/admin/rights-evidence.blade.php \
  resources/views/admin/rights-evidence-inspect.blade.php \
  database/migrations/2026_11_11_003000_create_rights_evidence.php; do
  [[ -f "$APP/$file" ]] || { echo "BLOCKED_MISSING=$file" >&2; exit 3; }
  case "$file" in *.php)
    "$PHP" -l "$APP/$file" >/dev/null ||
      { echo "BLOCKED_SYNTAX=$file" >&2; exit 3; }
  esac
  echo "QA_SOURCE_PRESENT=$file"
done
CLAM="$(command -v clamscan || true)"
[[ -n "$CLAM" && -x "$CLAM" && "$(basename "$CLAM")" == clamscan ]] ||
  { echo 'BLOCKED: Actual executable clamscan required.' >&2; exit 3; }
"$CLAM" --version
echo 'CLAMAV_EXECUTABLE=VERIFIED'
if [[ -d /var/lib/clamav ]]; then
  find /var/lib/clamav -maxdepth 1 -type f \( -name '*.cvd' -o -name '*.cld' \) \
    -printf 'CLAMAV_SIGNATURE=%f\n'
fi
echo 'Signature freshness remains an independent operator acceptance gate.'
for binary in ffmpeg ffprobe; do
  if command -v "$binary" >/dev/null 2>&1; then "$binary" -version 2>&1 | head -n1;
  else echo "PENDING_BINARY=$binary"; fi
done
if [[ "$MODE" == --check ]]; then
  echo 'GOMOVIE_RIGHTS_QA_PREFLIGHT=READ_ONLY_PASS'
  echo 'EICAR_DETECTION=NOT_RUN'
  echo 'DB_MIGRATIONS=NOT_RUN'
  echo 'PRODUCTION_CHANGED=NO'
  exit 0
fi
echo '========== EXPLICIT SYNTHETIC CLAMAV EICAR ACCEPTANCE =========='
TMP="$(mktemp -d /tmp/gomovie-rights-eicar-qa.XXXXXXXX)"
trap 'rm -rf -- "$TMP"' EXIT
python3 - "$TMP/test.txt" <<'PY'
from pathlib import Path
import sys
# Harmless industry-standard antivirus test string; not malicious executable code.
test = "X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*"
Path(sys.argv[1]).write_bytes(test.encode("ascii"))
PY
set +e
"$CLAM" --no-summary --infected "$TMP/test.txt" >/dev/null 2>&1
STATUS="$?"
set -e
[[ "$STATUS" == 1 ]] || {
  echo "BLOCKED_EICAR_DETECTION=UNEXPECTED_EXIT_$STATUS" >&2
  exit 4
}
echo 'REAL_CLAMAV_EICAR_DETECTION=PASS'
echo 'ONLY_ISOLATED_TEST_FILE_CREATED_AND_REMOVED=YES'
echo 'PRODUCTION_MEDIA_AND_DATABASE_CHANGED=NO'
