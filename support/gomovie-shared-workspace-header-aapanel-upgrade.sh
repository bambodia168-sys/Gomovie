#!/usr/bin/env bash
# Go Movie: account + Studio shared compact header and collapsible sidebar.
# Only three shared partials and two workspace layouts; no DB or media changes.
# All customized production layouts must merge without conflicts, or stop safely.
set -Eeuo pipefail
umask 077
if [ "$#" -ne 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
  echo "Usage: bash gomovie-shared-workspace-header-aapanel-upgrade.sh --check|--apply" >&2
  exit 2
fi
MODE="$1"
REPO="bambodia168-sys/Gomovie"
BASE="d3a5745825bd1a869c4c5c595a7b798582824231"
SOURCE="bd68adbf0887cc55bef92dcfed31988b664b56a6"
APP="/www/wwwroot/gomovie.tonsamith.com"
HOST="gomovie.tonsamith.com"
PHP="/www/server/php/85/bin/php"

paths(){
cat <<'PATHS'
resources/views/partials/workspace-header.blade.php
resources/views/partials/workspace-sidebar.blade.php
resources/views/partials/workspace-ui.blade.php
resources/views/layouts/admin.blade.php
resources/views/layouts/account.blade.php
PATHS
}

echo "========== GO MOVIE SHARED WORKSPACE HEADER =========="
echo "MODE=$MODE"
test "$(id -u)" -eq 0 || { echo "BLOCKED_NOT_ROOT"; exit 1; }
for cmd in gh git python3 curl cmp cp install runuser; do
  command -v "$cmd" >/dev/null || { echo "BLOCKED_MISSING_TOOL=$cmd"; exit 1; }
done
test -d "$APP" && test ! -L "$APP" && test "$(realpath -e "$APP")" = "$APP"
test -f "$APP/artisan" && test -f "$APP/vendor/autoload.php"
test -x "$PHP"
id www >/dev/null

TMP="$(mktemp -d /root/Gomovie-Shared-Workspace-XXXXXXXX)"
chmod 700 "$TMP"
mkdir -p "$TMP/base" "$TMP/target" "$TMP/new" "$TMP/old"
paths > "$TMP/paths"
CHANGED=0
COMPLETE=0

cleanup(){
  local status="$?"
  trap - EXIT
  if [ "$CHANGED" = 1 ] && [ "$COMPLETE" = 0 ]; then
    set +e
    echo "========== ROLLBACK WORKSPACE SOURCE =========="
    while IFS= read -r relative; do
      if [ -f "$TMP/old/$relative" ]; then
        cp -p -- "$TMP/old/$relative" "$APP/$relative"
      else
        rm -f -- "$APP/$relative"
      fi
    done < "$TMP/paths"
    (cd "$APP" && runuser -u www -- "$PHP" artisan optimize:clear --no-interaction) >/dev/null 2>&1
    (cd "$APP" && runuser -u www -- "$PHP" artisan view:cache --no-interaction) >/dev/null 2>&1
    echo "WORKSPACE_SOURCE_ROLLBACK=COMPLETE"
  fi
  rm -f -- "$TMP/blob.json"
  if [ "$MODE" = "--check" ]; then
    rm -rf -- "$TMP"
  else
    echo "BACKUP_DIRECTORY=$TMP"
  fi
  exit "$status"
}
trap cleanup EXIT

echo "========== VERIFY EXACT GITHUB SOURCE AND ACCEPTANCE =========="
test "$(gh api "repos/$REPO/commits/$SOURCE" --jq .sha)" = "$SOURCE"
for run in 42848582919 42848584124 42848582860; do
  result="$(gh api "repos/$REPO/actions/runs/$run" --jq '[.head_sha,.conclusion] | join(":")')"
  if [ "$result" != "$SOURCE:success" ]; then
    echo "BLOCKED_RELEASE_CI=$run:$result"
    exit 1
  fi
  echo "VERIFIED_RELEASE_CI=$run:success"
done

echo "========== VERIFY EXACT BASELINE AND TARGET BLOBS =========="
gh api -H 'Accept: application/vnd.github+json' \
  "repos/$REPO/git/trees/$BASE?recursive=1" > "$TMP/base-tree.json"
gh api -H 'Accept: application/vnd.github+json' \
  "repos/$REPO/git/trees/$SOURCE?recursive=1" > "$TMP/source-tree.json"
python3 - "$TMP/base-tree.json" "$TMP/source-tree.json" "$TMP/paths" > "$TMP/manifest" <<'PY_MANIFEST'
import json,pathlib,re,sys
base,target,files=map(pathlib.Path,sys.argv[1:])
b=json.loads(base.read_text(encoding="utf-8"))
t=json.loads(target.read_text(encoding="utf-8"))
if b.get("truncated") or t.get("truncated"):
    raise SystemExit("BLOCKED_GITHUB_TREE_TRUNCATED")
bmap={row["path"]:row for row in b["tree"] if row.get("type")=="blob"}
tmap={row["path"]:row for row in t["tree"] if row.get("type")=="blob"}
paths=files.read_text(encoding="utf-8").splitlines()
if len(paths)!=5 or len(set(paths))!=5:
    raise SystemExit("BLOCKED_RELEASE_FILE_LIST")
for path in paths:
    if not re.fullmatch(r"[A-Za-z0-9_./-]+",path) or ".." in path.split("/"):
        raise SystemExit("BLOCKED_INVALID_RELEASE_PATH")
    target_sha=tmap.get(path,{}).get("sha","")
    base_sha=bmap.get(path,{}).get("sha","-")
    if not re.fullmatch(r"[0-9a-f]{40}",target_sha):
        raise SystemExit("BLOCKED_RELEASE_SOURCE_MISSING="+path)
    if base_sha!="-" and not re.fullmatch(r"[0-9a-f]{40}",base_sha):
        raise SystemExit("BLOCKED_BASE_FILE_SHA")
    print(path+"\t"+base_sha+"\t"+target_sha)
PY_MANIFEST

download_blob(){
  local sha="$1" dest="$2"
  gh api -H 'Accept: application/vnd.github+json' "repos/$REPO/git/blobs/$sha" > "$TMP/blob.json"
  python3 - "$TMP/blob.json" "$dest" "$sha" <<'PY_BLOB'
import base64,binascii,hashlib,json,pathlib,re,sys
source,dest,expected=sys.argv[1:]
obj=json.loads(pathlib.Path(source).read_text(encoding="utf-8"))
if obj.get("sha")!=expected or obj.get("encoding")!="base64":
    raise SystemExit("BLOCKED_GITHUB_BLOB_METADATA")
try:
    data=base64.b64decode(re.sub(r"\s+","",obj["content"]),validate=True)
except (KeyError,TypeError,binascii.Error):
    raise SystemExit("BLOCKED_GITHUB_BLOB_BASE64")
actual=hashlib.sha1(("blob "+str(len(data))+"\0").encode()+data).hexdigest()
if not data or actual!=expected:
    raise SystemExit("BLOCKED_GITHUB_BLOB_INTEGRITY")
p=pathlib.Path(dest)
p.parent.mkdir(parents=True,exist_ok=True)
p.write_bytes(data)
PY_BLOB
}

echo "========== PRESERVE CUSTOM PRODUCTION WORKSPACE LAYOUTS =========="
while IFS=$'\t' read -r relative base_sha target_sha; do
  live="$APP/$relative"
  target="$TMP/target/$relative"
  prepared="$TMP/new/$relative"
  download_blob "$target_sha" "$target"
  mkdir -p "$(dirname "$prepared")"
  if [ "$base_sha" = "-" ]; then
    if [ -e "$live" ] || [ -L "$live" ]; then
      test -f "$live" && test ! -L "$live" || {
        echo "BLOCKED_NEW_FILE_TYPE=$relative"; exit 1;
      }
      test "$(git hash-object "$live")" = "$target_sha" || {
        echo "BLOCKED_UNEXPECTED_NEW_PARTIAL=$relative"; exit 1;
      }
    fi
    cp -- "$target" "$prepared"
    echo "NEW_SHARED_COMPONENT=$relative"
  else
    test -f "$live" && test ! -L "$live" || {
      echo "BLOCKED_LIVE_LAYOUT_MISSING=$relative"; exit 1;
    }
    live_sha="$(git hash-object "$live")"
    if [ "$live_sha" = "$base_sha" ] || [ "$live_sha" = "$target_sha" ]; then
      cp -- "$target" "$prepared"
      echo "KNOWN_LIVE_WORKSPACE_SOURCE=$relative"
    else
      baseline="$TMP/base/$relative"
      download_blob "$base_sha" "$baseline"
      if git merge-file -p -- "$live" "$baseline" "$target" > "$prepared"; then
        echo "CUSTOM_WORKSPACE_MERGED=$relative"
      elif [ "$relative" = "resources/views/layouts/admin.blade.php" ] &&
           [ "$live_sha" = "70cd912960cf3f9d371a8bda7790c43187064896" ]; then
        echo "========== PRESERVE VERIFIED CUSTOM ADMIN STRUCTURE =========="
        echo "CUSTOM_ADMIN_LAYOUT_BLOB=VERIFIED:$live_sha"
        # This is a narrow, fail-closed adapter for the exact production blob
        # reported on 2026-11-10. It replaces only the navigation/header
        # regions and adds the new shared includes, retaining unrelated
        # custom assets, application content, and layout markup.
        if ! python3 - "$live" "$prepared" <<'PY_CUSTOM_ADMIN'
from pathlib import Path
import re
import sys

live=Path(sys.argv[1])
dest=Path(sys.argv[2])
text=live.read_text(encoding="utf-8")
original=text

def require(condition, error):
    if not condition:
        raise SystemExit("BLOCKED_CUSTOM_ADMIN_STRUCTURE="+error)

def unique_region(tag, identifying_class):
    global text
    opens=list(re.finditer(r"<"+tag+r"\b[^>]*>",text,re.S|re.I))
    matches=[m for m in opens if re.search(
        r'\bclass\s*=\s*(["\'])(.*?)\1',m.group(),re.S)
        and identifying_class in m.group()]
    require(len(matches)==1,tag+"-start-count")
    opening=matches[0]
    closing="</"+tag+">"
    # The existing Studio shell has a single top-level sidebar and topbar.
    # Disallow nested same-type tags and ambiguous closing boundaries.
    following=text.find(closing,opening.end())
    require(following>=0,tag+"-end-missing")
    require(not re.search(r"<"+tag+r"\b",text[opening.end():following],re.I),
            tag+"-nested")
    return opening.start(), following+len(closing)

def replace_region(tag, cls, replacement):
    global text
    start,end=unique_region(tag,cls)
    text=text[:start]+replacement+text[end:]

def add_class_to_tag(tag,needle,extra):
    global text
    opens=list(re.finditer(r"<"+tag+r"\b[^>]*>",text,re.S|re.I))
    matches=[]
    for m in opens:
        cl=re.search(r'\bclass\s*=\s*(["\'])(.*?)\1',m.group(),re.S)
        if cl and needle in cl.group(2).split():
            matches.append((m,cl))
    require(len(matches)==1,tag+"-"+needle+"-count")
    match,cl=matches[0]
    opening=match.group()
    require(extra not in cl.group(2).split(),tag+"-"+extra+"-already")
    changed=opening[:cl.start(2)]+cl.group(2)+" "+extra+opening[cl.end(2):]
    text=text[:match.start()]+changed+text[match.end():]

require(len(re.findall(r"<aside\b",text,re.I))==1,"aside-count")
require(len(re.findall(r"</aside\s*>",text,re.I))==1,"aside-end-count")
require(len(re.findall(r"<header\b",text,re.I))==1,"topbar-count")
require(len(re.findall(r"</header\s*>",text,re.I))==1,"topbar-end-count")
require(text.count("@yield('content')")==1,"content-outlet")
require(text.count("@include('partials.brand-head')")==1,"brand-head")
require(text.count("@include('partials.header-ui')")==1,"header-ui")
require(text.count("@vite(")==1,"vite")
require("partials.workspace-header" not in text,"already-migrated-header")
require("partials.workspace-sidebar" not in text,"already-migrated-sidebar")
require("partials.workspace-ui" not in text,"already-migrated-style")
require("data-gm-workspace" not in text,"already-migrated-data")
require(text.count("</head>")==1 and text.count("</body>")==1,"document-tags")

replace_region("aside","gm-studio-sidebar",
               "@php $workspaceMode='admin'; @endphp\n  @include('partials.workspace-sidebar')")
replace_region("header","gm-studio-topbar",
               "@include('partials.workspace-header')")
add_class_to_tag("div","min-h-screen","gm-workspace-frame")
add_class_to_tag("div","gm-studio-main","gm-workspace-main")

body=re.search(r"<body\b[^>]*>",text,re.S|re.I)
require(body is not None,"body-opening")
opening=body.group()
cl=re.search(r'\bclass\s*=\s*(["\'])(.*?)\1',opening,re.S)
require(cl is not None and "gm-studio-shell" in cl.group(2).split(),"body-class")
opening=opening[:cl.start(2)]+cl.group(2)+" gm-workspace-shell"+opening[cl.end(2):]
opening=opening.replace(' x-data="{menu:false}"',"")
opening=opening[:-1]+' data-gm-workspace="studio">'
text=text[:body.start()]+opening+text[body.end():]
require(text.count('data-gm-workspace="studio"')==1,"workspace-mode")

main=list(re.finditer(r"<main\b[^>]*>",text,re.S|re.I))
matches=[m for m in main if re.search(r'\bid\s*=\s*["\']studio-content["\']',m.group())]
require(len(matches)==1,"main-content-id")
m=matches[0]
opening=m.group()
cm=re.search(r'\bclass\s*=\s*(["\'])(.*?)\1',opening,re.S)
if cm:
    opening=opening[:cm.start(2)]+cm.group(2)+" gm-workspace-content"+opening[cm.end(2):]
else:
    opening=opening[:-1]+' class="gm-workspace-content">'
text=text[:m.start()]+opening+text[m.end():]
text=text.replace("</head>","@include('partials.workspace-ui')\n</head>",1)
text=text.replace("</body>",'''<button class="gm-workspace-backdrop" type="button"
        data-gm-sidebar-backdrop aria-label="Close navigation" hidden></button>
</body>''',1)

for token in (
    "partials.workspace-sidebar","partials.workspace-header",
    "partials.workspace-ui",'data-gm-workspace="studio"',
    'gm-workspace-frame','gm-workspace-main','gm-workspace-content',
    'data-gm-sidebar-backdrop',"@yield('content')",
    "partials.brand-head","partials.header-ui",
):
    require(token in text,"missing-"+token)
require(text!=original,"no-changes")
require("<<<<<<<" not in text and ">>>>>>>" not in text,"conflict-markers")
dest.write_text(text,encoding="utf-8")
print("CUSTOM_ADMIN_LAYOUT_PRESERVED=YES")
print("CUSTOM_ADMIN_REPLACED_REGIONS=SIDEBAR_AND_TOPBAR_ONLY")
PY_CUSTOM_ADMIN
        then
          echo "BLOCKED_CUSTOM_ADMIN_ADAPTER=$relative"
          exit 1
        fi
      else
        echo "BLOCKED_CUSTOM_WORKSPACE_MERGE=$relative"
        echo "LIVE_BLOB=$live_sha"
        exit 1
      fi
      if grep -Eq '^(<<<<<<<|=======|>>>>>>>)' "$prepared"; then
        echo "BLOCKED_MERGE_CONFLICT_MARKERS=$relative"; exit 1;
      fi
    fi
  fi
done < "$TMP/manifest"

echo "========== CHECK SHARED WORKSPACE CONTRACTS =========="
python3 - "$TMP/new" <<'PY_CONTRACTS'
from pathlib import Path
import sys
p=Path(sys.argv[1])
checks={
 "resources/views/partials/workspace-header.blade.php":[
   "gm-workspace-topbar","data-gm-sidebar-toggle",
   'class="gm-workspace-header-search"',"route('browse')",
   'class="gm-header-tool gm-workspace-home"',"partials.header-actions"],
 "resources/views/partials/workspace-sidebar.blade.php":[
   'id="gm-workspace-sidebar"',"gm-workspace-link-label",
   "StudioNavigation::staff","StudioNavigation::administrator",
   "gm-account-nav","aria-current"],
 "resources/views/partials/workspace-ui.blade.php":[
   'id="gomovie-workspace-ui"','id="gomovie-workspace-sidebar-controller"',
   "gomovie.workspace.sidebar.collapsed","is-sidebar-collapsed",
   "is-sidebar-open","data-gm-sidebar-toggle",
   ".gm-workspace-sidebar-nav>a.is-current .gm-workspace-link-label",
   ".gm-workspace-header-search"],
 "resources/views/layouts/admin.blade.php":[
   'data-gm-workspace="studio"','gm-workspace-frame',
   "partials.workspace-sidebar","partials.workspace-header",
   "partials.workspace-ui","data-gm-sidebar-backdrop",
   "@yield('content')"],
 "resources/views/layouts/account.blade.php":[
   'data-gm-workspace="account"','gm-workspace-frame',
   "partials.workspace-sidebar","partials.workspace-header",
   "partials.workspace-ui","data-gm-sidebar-backdrop",
   "@yield('account_content')"]
}
for file,needles in checks.items():
    text=(p/file).read_text(encoding="utf-8")
    for needle in needles:
        if needle not in text:
            raise SystemExit("BLOCKED_WORKSPACE_CONTRACT="+file+":"+needle)
for file in ("resources/views/layouts/admin.blade.php",
             "resources/views/layouts/account.blade.php"):
    text=(p/file).read_text(encoding="utf-8")
    for needle in ("partials.workspace-header","partials.workspace-sidebar",
                   "partials.workspace-ui"):
        if text.count(needle)!=1:
            raise SystemExit("BLOCKED_DUPLICATE_WORKSPACE_STRUCTURE="+file)
    if "x-data=\"{menu:false}\"" in text or '@extends(' in text:
        raise SystemExit("BLOCKED_LEGACY_WORKSPACE_LAYOUT="+file)
    if text.count("@yield('content')")+text.count("@yield('account_content')")!=1:
        raise SystemExit("BLOCKED_WORKSPACE_CONTENT_OUTLET="+file)
print("WORKSPACE_CONTRACTS=VERIFIED")
PY_CONTRACTS

echo "========== PHP SYNTAX =========="
while IFS= read -r relative; do
  "$PHP" -l "$TMP/new/$relative" >/dev/null || {
    echo "BLOCKED_BLADE_PHP_SYNTAX=$relative"; exit 1;
  }
done < "$TMP/paths"
echo "WORKSPACE_PHP_SYNTAX=PASS"

if [ "$MODE" = "--check" ]; then
  echo "GOMOVIE_WORKSPACE_PREFLIGHT=PASS"
  echo "LIVE_FILES_CHANGED=NO"
  echo "DATABASE_CHANGED=NO"
  exit 0
fi

echo "========== BACKUP CURRENT WORKSPACE FILES =========="
while IFS= read -r relative; do
  if [ -f "$APP/$relative" ]; then
    mkdir -p "$TMP/old/$(dirname "$relative")"
    cp -p -- "$APP/$relative" "$TMP/old/$relative"
  fi
done < "$TMP/paths"
echo "WORKSPACE_SOURCE_BACKUP=VERIFIED"

echo "========== RECHECK LIVE SOURCE IS UNCHANGED =========="
while IFS= read -r relative; do
  if [ -f "$TMP/old/$relative" ]; then
    test -f "$APP/$relative" && test ! -L "$APP/$relative"
    cmp -s "$TMP/old/$relative" "$APP/$relative"
  else
    test ! -e "$APP/$relative" && test ! -L "$APP/$relative"
  fi
done < "$TMP/paths"

echo "========== INSTALL FIVE WORKSPACE FILES =========="
CHANGED=1
while IFS= read -r relative; do
  test -d "$(dirname "$APP/$relative")" &&
    test ! -L "$(dirname "$APP/$relative")" || {
      echo "BLOCKED_PARENT_DIRECTORY=$relative"; exit 1;
    }
  install -o root -g www -m 0644 "$TMP/new/$relative" "$APP/$relative"
  cmp -s "$TMP/new/$relative" "$APP/$relative"
  echo "WORKSPACE_FILE_INSTALLED=$relative"
done < "$TMP/paths"

echo "========== VERIFY LARAVEL VIEWS AND ROUTES =========="
(
  cd "$APP"
  runuser -u www -- "$PHP" artisan optimize:clear --no-interaction
  runuser -u www -- "$PHP" artisan view:cache --no-interaction
  runuser -u www -- "$PHP" artisan route:list --path=dashboard >/dev/null
  runuser -u www -- "$PHP" artisan route:list --path=account >/dev/null
)
echo "WORKSPACE_BLADE_AND_ROUTES=PASS"

echo "========== LOCAL HTTPS SMOKE TEST =========="
for page in / /login /register; do
  http="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" \
    -o /dev/null -w '%{http_code}' "https://$HOST$page")"
  test "$http" = 200 || { echo "BLOCKED_PUBLIC_HTTP=$page:$http"; exit 1; }
  echo "WORKSPACE_PUBLIC_HTTP=$page:200"
done
for page in /dashboard /account; do
  http="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" \
    -o /dev/null -w '%{http_code}' "https://$HOST$page")"
  case "$http" in
    302|303) echo "WORKSPACE_GUEST_AUTH_GATE=$page:$http";;
    *) echo "BLOCKED_WORKSPACE_GUEST_AUTH=$page:$http";exit 1;;
  esac
done
while IFS= read -r relative; do
  cmp -s "$TMP/new/$relative" "$APP/$relative"
done < "$TMP/paths"
COMPLETE=1
echo "GOMOVIE_WORKSPACE_DEPLOYMENT=SUCCESS"
echo "ROLE_BASED_NAVIGATION=PRESERVED"
echo "PUBLIC_FRONTEND_LAYOUT=UNCHANGED"
echo "DATABASE_CHANGED=NO"
echo "UPLOADS_AND_VIDEOS=UNCHANGED"
