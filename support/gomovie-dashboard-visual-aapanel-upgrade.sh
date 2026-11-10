#!/usr/bin/env bash
# GoMovie premium dashboard visual cards + transparent shared account header.
# Exact-source release, custom-file guard, backup and rollback.
set -Eeuo pipefail
umask 077

if [ "$#" -ne 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
  echo "Usage: bash gomovie-dashboard-visual-aapanel-upgrade.sh --check|--apply" >&2
  exit 2
fi
MODE="$1"
REPO="bambodia168-sys/Gomovie"
BASE="29cca32d407ec48a4c6903fbb42ebdfbd7d18e96"
SOURCE="b9b4143207922a2dc00daf1df01e4fef1df58145"
APP="/www/wwwroot/gomovie.tonsamith.com"
PHP="/www/server/php/85/bin/php"
HOST="gomovie.tonsamith.com"

paths(){
cat <<'FILES'
resources/views/admin/dashboard.blade.php
resources/views/partials/studio-visual-metric.blade.php
resources/views/partials/workspace-ui.blade.php
app/Http/Controllers/Admin/DashboardController.php
FILES
}
echo "========== GOMOVIE DASHBOARD VISUAL RELEASE =========="
echo "MODE=$MODE"
test "$(id -u)" -eq 0 || { echo "BLOCKED_NOT_ROOT"; exit 1; }
for cmd in gh git python3 curl cmp cp install runuser; do
  command -v "$cmd" >/dev/null || { echo "BLOCKED_MISSING_TOOL=$cmd"; exit 1; }
done
test -x "$PHP"
test -d "$APP" && test ! -L "$APP" && test "$(realpath -e "$APP")" = "$APP"
test -f "$APP/artisan" && test -f "$APP/vendor/autoload.php"
id www >/dev/null

TMP="$(mktemp -d /root/Gomovie-Dashboard-Visual-XXXXXXXX)"
chmod 700 "$TMP"
mkdir -p "$TMP/new" "$TMP/base" "$TMP/target" "$TMP/old"
paths > "$TMP/paths"
CHANGED=0
COMPLETE=0

finish(){
  code="$?"
  trap - EXIT
  if [ "$CHANGED" = 1 ] && [ "$COMPLETE" = 0 ]; then
    set +e
    echo "========== RESTORE ORIGINAL DASHBOARD FILES =========="
    while IFS= read -r relative; do
      if [ -f "$TMP/old/$relative" ]; then
        cp -p -- "$TMP/old/$relative" "$APP/$relative"
      else
        rm -f -- "$APP/$relative"
      fi
    done < "$TMP/paths"
    ( cd "$APP" && runuser -u www -- "$PHP" artisan optimize:clear --no-interaction ) >/dev/null 2>&1
    ( cd "$APP" && runuser -u www -- "$PHP" artisan view:cache --no-interaction ) >/dev/null 2>&1
    echo "GOMOVIE_DASHBOARD_SOURCE_ROLLBACK=COMPLETE"
  fi
  rm -f -- "$TMP/blob.json"
  if [ "$MODE" = "--check" ]; then
    rm -rf -- "$TMP"
  else
    echo "BACKUP_DIRECTORY=$TMP"
  fi
  exit "$code"
}
trap finish EXIT

echo "========== VERIFY COMMIT AND THREE WORKFLOW RUNS =========="
test "$(gh api "repos/$REPO/commits/$SOURCE" --jq .sha)" = "$SOURCE"
for run in 42860137825 42860137569 42860138146; do
  status="$(gh api "repos/$REPO/actions/runs/$run" --jq '[.head_sha,.conclusion] | join(":")')"
  if [ "$status" != "$SOURCE:success" ]; then
    echo "BLOCKED_RELEASE_CI=$run:$status"
    exit 1
  fi
  echo "GOMOVIE_RELEASE_CI=VERIFIED:$run"
done

echo "========== DOWNLOAD VERIFIED SOURCE MANIFEST =========="
gh api -H 'Accept: application/vnd.github+json' \
  "repos/$REPO/git/trees/$BASE?recursive=1" > "$TMP/base-tree.json"
gh api -H 'Accept: application/vnd.github+json' \
  "repos/$REPO/git/trees/$SOURCE?recursive=1" > "$TMP/target-tree.json"
python3 - "$TMP/base-tree.json" "$TMP/target-tree.json" "$TMP/paths" > "$TMP/manifest" <<'PY_MANIFEST'
import json,pathlib,re,sys
base,target,paths=map(pathlib.Path,sys.argv[1:])
a=json.loads(base.read_text(encoding="utf-8"))
b=json.loads(target.read_text(encoding="utf-8"))
if a.get("truncated") or b.get("truncated"):
    raise SystemExit("BLOCKED_TRUNCATED_GITHUB_TREE")
amap={p["path"]:p for p in a["tree"] if p.get("type")=="blob"}
bmap={p["path"]:p for p in b["tree"] if p.get("type")=="blob"}
items=paths.read_text(encoding="utf-8").splitlines()
if len(items)!=4 or len(set(items))!=4:
    raise SystemExit("BLOCKED_RELEASE_MANIFEST_COUNT")
for path in items:
    if not re.fullmatch(r"[a-zA-Z0-9_./-]+",path) or ".." in path.split("/"):
        raise SystemExit("BLOCKED_RELEASE_PATH")
    dest=bmap.get(path,{}).get("sha","")
    source=amap.get(path,{}).get("sha","-")
    if not re.fullmatch(r"[0-9a-f]{40}",dest):
        raise SystemExit("BLOCKED_MISSING_RELEASE_BLOB="+path)
    if source!="-" and not re.fullmatch(r"[0-9a-f]{40}",source):
        raise SystemExit("BLOCKED_MISSING_BASE_BLOB="+path)
    print(path+"\t"+source+"\t"+dest)
PY_MANIFEST

download_blob(){
  sha="$1"
  file="$2"
  gh api -H 'Accept: application/vnd.github+json' "repos/$REPO/git/blobs/$sha" > "$TMP/blob.json"
  python3 - "$TMP/blob.json" "$file" "$sha" <<'PY_BLOB'
import base64,binascii,hashlib,json,pathlib,re,sys
src,dest,expected=sys.argv[1:]
obj=json.loads(pathlib.Path(src).read_text(encoding="utf-8"))
if obj.get("sha")!=expected or obj.get("encoding")!="base64":
    raise SystemExit("BLOCKED_BLOB_METADATA")
try:
    payload=base64.b64decode(re.sub(r"\s+","",obj["content"]),validate=True)
except (KeyError,TypeError,binascii.Error):
    raise SystemExit("BLOCKED_BLOB_BASE64")
actual=hashlib.sha1(("blob "+str(len(payload))+"\0").encode()+payload).hexdigest()
if not payload or actual!=expected:raise SystemExit("BLOCKED_BLOB_INTEGRITY")
output=pathlib.Path(dest)
output.parent.mkdir(parents=True,exist_ok=True)
output.write_bytes(payload)
PY_BLOB
}

echo "========== PREPARE AND PRESERVE CUSTOM SOURCE =========="
while IFS=$'\t' read -r relative base_sha target_sha; do
  live="$APP/$relative"
  target="$TMP/target/$relative"
  prepared="$TMP/new/$relative"
  download_blob "$target_sha" "$target"
  mkdir -p "$(dirname "$prepared")"
  if [ "$base_sha" = "-" ]; then
    if [ -e "$live" ] || [ -L "$live" ]; then
      test -f "$live" && test ! -L "$live" || { echo "BLOCKED_NEW_FILE_TYPE=$relative"; exit 1; }
      test "$(git hash-object "$live")" = "$target_sha" || {
        echo "BLOCKED_UNRECOGNIZED_NEW_FILE=$relative"; exit 1;
      }
    fi
    cp -- "$target" "$prepared"
    echo "NEW_VISUAL_COMPONENT=$relative"
  else
    test -f "$live" && test ! -L "$live" || { echo "BLOCKED_LIVE_FILE_MISSING=$relative"; exit 1; }
    live_sha="$(git hash-object "$live")"
    if [ "$live_sha" = "$base_sha" ] || [ "$live_sha" = "$target_sha" ]; then
      cp -- "$target" "$prepared"
      echo "KNOWN_SOURCE=$relative"
    else
      original="$TMP/base/$relative"
      download_blob "$base_sha" "$original"
      if ! git merge-file -p -- "$live" "$original" "$target" > "$prepared"; then
        echo "BLOCKED_CUSTOM_SOURCE_MERGE=$relative"
        echo "LIVE_BLOB=$live_sha"
        exit 1
      fi
      if grep -Eq '^(<<<<<<<|=======|>>>>>>>)' "$prepared"; then
        echo "BLOCKED_CONFLICT_MARKERS=$relative"
        exit 1
      fi
      echo "CUSTOM_SOURCE_PRESERVED=$relative"
    fi
  fi
done < "$TMP/manifest"

echo "========== VERIFY DASHBOARD AND SHARED HEADER CONTRACTS =========="
python3 - "$TMP/new" <<'PY_CONTRACT'
from pathlib import Path
import sys
root=Path(sys.argv[1])
checks={
 "resources/views/admin/dashboard.blade.php":[
  "gm-dashboard-overview","gm-dashboard-metric-grid--four",
  "gm-dashboard-metric-grid--three","partials.studio-visual-metric",
  "data-studio-menu","partials.studio-pager","gm-dashboard-table",
  "admin.catalog.edit","admin.catalog.preview","10 items per page"],
 "resources/views/partials/studio-visual-metric.blade.php":[
  "gm-visual-metric-art","gm-visual-metric-value",
  "gm-visual-metric-progress","role=\"progressbar\"",
  "number_format","partials.studio-icon"],
 "resources/views/partials/workspace-ui.blade.php":[
  "gomovie-workspace-ui","gomovie-workspace-sidebar-controller",
  ".gm-workspace-shell .gm-workspace-home",
  ".gm-header-tools .gm-header-language",
  ".gm-header-tools .gm-theme-tool",
  "background:transparent!important",
  ".gm-dashboard-metric-grid--four",".gm-dashboard-table",
  "html.light .gm-dashboard-overview"],
 "app/Http/Controllers/Admin/DashboardController.php":[
  "Movie::count()","Series::count()","Episode::count()",
  "User::count()","media_ingests",
  "orderByDesc('updated_at')","paginate(10)"],
}
for path,needles in checks.items():
    source=(root/path).read_text(encoding="utf-8")
    for needle in needles:
        if needle not in source:
            raise SystemExit("BLOCKED_RELEASE_CONTRACT="+path+":"+needle)
print("DASHBOARD_VISUAL_CONTRACTS=PASS")
PY_CONTRACT
"$PHP" -l "$TMP/new/app/Http/Controllers/Admin/DashboardController.php" >/dev/null
echo "DASHBOARD_PHP_LINT=PASS"

if [ "$MODE" = "--check" ]; then
  echo "GOMOVIE_DASHBOARD_VISUAL_PREFLIGHT=PASS"
  echo "LIVE_FILES_CHANGED=NO"
  echo "DATABASE_CHANGED=NO"
  exit 0
fi

echo "========== BACKUP EXISTING DASHBOARD FILES =========="
while IFS= read -r relative; do
  if [ -f "$APP/$relative" ]; then
    mkdir -p "$TMP/old/$(dirname "$relative")"
    cp -p -- "$APP/$relative" "$TMP/old/$relative"
  fi
done < "$TMP/paths"
echo "DASHBOARD_SOURCE_BACKUP=VERIFIED"
echo "========== RECHECK PRODUCTION FILES =========="
while IFS= read -r relative; do
  if [ -f "$TMP/old/$relative" ]; then
    test -f "$APP/$relative" && test ! -L "$APP/$relative"
    cmp -s "$TMP/old/$relative" "$APP/$relative"
  else
    test ! -e "$APP/$relative" && test ! -L "$APP/$relative"
  fi
done < "$TMP/paths"

echo "========== INSTALL VISUAL DASHBOARD =========="
CHANGED=1
while IFS= read -r relative; do
  parent="$(dirname "$APP/$relative")"
  test -d "$parent" && test ! -L "$parent" || {
    echo "BLOCKED_RELEASE_PARENT=$relative"
    exit 1
  }
  install -o root -g www -m 0644 "$TMP/new/$relative" "$APP/$relative"
  cmp -s "$TMP/new/$relative" "$APP/$relative"
  echo "DASHBOARD_INSTALLED=$relative"
done < "$TMP/paths"

echo "========== VERIFY LARAVEL COMPILATION AND ROUTES =========="
(
  cd "$APP"
  runuser -u www -- "$PHP" artisan optimize:clear --no-interaction
  runuser -u www -- "$PHP" artisan view:cache --no-interaction
  runuser -u www -- "$PHP" artisan route:list --path=dashboard >/dev/null
)
echo "DASHBOARD_LARAVEL_VIEWS_ROUTES=PASS"

echo "========== PUBLIC AND GUEST ACCESS SMOKE CHECKS =========="
for route in / /login /register; do
  status="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' \
    "https://$HOST$route")"
  test "$status" = 200 || { echo "BLOCKED_PUBLIC_HTTP=$route:$status"; exit 1; }
  echo "PUBLIC_HTTP=$route:200"
done
for route in /dashboard /account; do
  status="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' \
    "https://$HOST$route")"
  case "$status" in
    302|303) echo "GUEST_AUTH_GATE=$route:$status";;
    *) echo "BLOCKED_GUEST_WORKSPACE=$route:$status"; exit 1;;
  esac
done

while IFS= read -r relative; do
  cmp -s "$TMP/new/$relative" "$APP/$relative"
done < "$TMP/paths"
COMPLETE=1
echo "GOMOVIE_DASHBOARD_VISUAL_DEPLOYMENT=SUCCESS"
echo "ALL_ACCOUNT_HEADER_CONTROLS=TRANSPARENT"
echo "PREMIUM_VISUAL_METRICS_AND_TABLE=INSTALLED"
echo "EXISTING_ACTIONS_PAGINATION=PRESERVED"
echo "DATABASE_CHANGED=NO"
echo "UPLOADS_AND_MEDIA=UNCHANGED"
