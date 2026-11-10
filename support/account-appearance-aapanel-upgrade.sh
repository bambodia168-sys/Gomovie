#!/usr/bin/env bash
# Gomovie Account + Appearance deployment for aaPanel.
# Pins source and CI artifact; blocks unknown modifications.
set -Eeuo pipefail
umask 077

if [ "$#" -ne 1 ] || { [ "$1" != "--check" ] && [ "$1" != "--apply" ]; }; then
  echo "Usage: bash account-appearance-aapanel-upgrade.sh --check|--apply" >&2
  exit 2
fi
MODE="$1"
REPO="bambodia168-sys/Gomovie"
SOURCE="1d5a613294f683bcdd5300e071cb4c72ac61e859"
BASE="11eddadf605588ac8b7d30f7edd4c379c3868fba"
MID="b895bf75ba1ac32bea13f83dce26909e81e3d509"
RUN="42658912663"
APP="/www/wwwroot/gomovie.tonsamith.com"
PHP="/www/server/php/85/bin/php"
HOST="gomovie.tonsamith.com"
DUMP="/www/server/mysql/bin/mysqldump"
AUTH="resources/views/layouts/auth.blade.php"

paths() {
cat <<'PATHS'
app/Http/Controllers/Account/LibraryController.php
app/Http/Controllers/Account/SettingsController.php
app/Http/Controllers/Admin/AppearanceController.php
app/Http/Controllers/Admin/DashboardController.php
app/Http/Controllers/Admin/EpisodeStudioController.php
app/Http/Controllers/Admin/MediaController.php
app/Http/Controllers/Admin/OperationsController.php
app/Http/Controllers/Admin/UserController.php
app/Support/StudioAppearance.php
database/migrations/2026_11_09_000400_create_studio_appearance_tables.php
database/migrations/2026_11_09_000500_create_studio_content_slots_table.php
database/migrations/2026_11_09_000600_add_studio_typography_variants.php
resources/css/app.css
resources/css/studio.css
resources/js/app.js
resources/js/studio.js
resources/views/account/library.blade.php
resources/views/account/overview.blade.php
resources/views/account/settings.blade.php
resources/views/admin/appearance/index.blade.php
resources/views/admin/catalog/form.blade.php
resources/views/admin/catalog/index.blade.php
resources/views/admin/dashboard.blade.php
resources/views/admin/media.blade.php
resources/views/admin/operations.blade.php
resources/views/admin/player-settings.blade.php
resources/views/admin/series/episodes.blade.php
resources/views/admin/users.blade.php
resources/views/auth/forgot.blade.php
resources/views/auth/login.blade.php
resources/views/auth/register.blade.php
resources/views/auth/reset.blade.php
resources/views/auth/verify-email.blade.php
resources/views/frontend/about.blade.php
resources/views/frontend/browse.blade.php
resources/views/frontend/custom-page.blade.php
resources/views/frontend/detail.blade.php
resources/views/frontend/home.blade.php
resources/views/layouts/account.blade.php
resources/views/layouts/admin.blade.php
resources/views/layouts/app.blade.php
resources/views/layouts/auth.blade.php
resources/views/partials/feedback.blade.php
resources/views/partials/studio-icon.blade.php
resources/views/partials/studio-pager.blade.php
routes/web.php
PATHS
}

echo "========== GOMOVIE AA PANEL ACCOUNT + APPEARANCE =========="
echo "MODE=$MODE"
test "$(id -u)" = 0
test -d "$APP" && test ! -L "$APP" && test "$(realpath -e "$APP")" = "$APP"
test -x "$PHP" && test -x "$DUMP"
id www >/dev/null
for f in artisan .env vendor/autoload.php public/build/manifest.json; do
  test -f "$APP/$f" && test ! -L "$APP/$f" || { echo "BLOCKED_LIVE_PATH=$f"; exit 1; }
done
test ! -e "$APP/public/hot"
for bin in gh git curl runuser python3 unzip sha256sum gzip find cmp; do
  command -v "$bin" >/dev/null || { echo "MISSING_TOOL=$bin"; exit 1; }
done
TMP="$(mktemp -d /root/Gomovie-Account-Appearance-XXXXXXXX)"
chmod 700 "$TMP"
mkdir -p "$TMP/old" "$TMP/new" "$TMP/package" "$TMP/extract"
paths > "$TMP/paths"
CHANGED=0
COMPLETE=0

on_exit() {
  code="$?"
  trap - EXIT
  if [ "$CHANGED" -eq 1 ] && [ "$COMPLETE" -eq 0 ]; then
    set +e
    echo "========== RESTORING ORIGINAL SOURCES =========="
    while IFS= read -r f; do
      if [ -f "$TMP/old/$f" ]; then cp -p "$TMP/old/$f" "$APP/$f"; else rm -f -- "$APP/$f"; fi
    done < "$TMP/paths"
    cp -p "$TMP/old/manifest.json" "$APP/public/build/manifest.json"
    if [ -f "$TMP/old/fonts-manifest.json" ]; then
      cp -p "$TMP/old/fonts-manifest.json" "$APP/public/build/fonts-manifest.json"
    else
      rm -f -- "$APP/public/build/fonts-manifest.json"
    fi
    (cd "$APP" && runuser -u www -- "$PHP" artisan optimize:clear --no-interaction) >/dev/null 2>&1
    echo "SOURCE_ROLLBACK=COMPLETE"
    echo "DATABASE_NOTE=Additive schema migrations may remain; database backup is retained."
    echo "BACKUP_DIRECTORY=$TMP"
  fi
  # Ensure DB credentials are never retained even on backup failures.
  rm -f -- "$TMP/mysql.cnf" "$TMP/db-name"
  if [ "$MODE" = "--check" ]; then rm -rf -- "$TMP"; fi
  exit "$code"
}
trap on_exit EXIT

# The previous failed release may have left this newly-created directory
# root-owned with mode 0700. Do not alter it during --check. Only permit
# automatic repair if it matches that exact, empty rollback remnant.
APPEARANCE_VIEW_DIR="$APP/resources/views/admin/appearance"
check_appearance_view_dir() {
  local dir="$APPEARANCE_VIEW_DIR"
  local parent="$APP/resources/views/admin"
  test -d "$parent" && test ! -L "$parent" || {
    echo "BLOCKED_APPEARANCE_PARENT=$parent"; return 1;
  }
  if [ -L "$dir" ]; then
    echo "BLOCKED_APPEARANCE_DIRECTORY_SYMLINK=$dir"; return 1
  fi
  if [ ! -e "$dir" ]; then
    echo "APPEARANCE_VIEW_DIRECTORY=NEW"; return 0
  fi
  test -d "$dir" || { echo "BLOCKED_APPEARANCE_DIRECTORY_TYPE=$dir"; return 1; }
  if runuser -u www -- test -x "$dir"; then
    echo "APPEARANCE_VIEW_DIRECTORY=ACCESSIBLE"; return 0
  fi
  if [ "$(stat -c '%u:%a' "$dir")" = "0:700" ] &&
     [ -z "$(find "$dir" -mindepth 1 -maxdepth 1 -print -quit)" ]; then
    echo "APPEARANCE_VIEW_DIRECTORY=SAFE_EMPTY_ROLLBACK_REMNANT"; return 0
  fi
  echo "BLOCKED_APPEARANCE_DIRECTORY_UNEXPECTED_PERMISSIONS=$dir"
  return 1
}
check_appearance_view_dir

# Read-only audit of the Laravel package manifest cache. Some older
# root-run maintenance commands leave cache files inaccessible to www.
# Never touch application configuration, .env, or arbitrary directories.
CACHE_DIR="$APP/bootstrap/cache"
bootstrap_cache_audit() {
  local file owner mode
  test -d "$APP/bootstrap" && test ! -L "$APP/bootstrap" || {
    echo "BLOCKED_BOOTSTRAP_PARENT"; return 1;
  }
  runuser -u www -- test -x "$APP/bootstrap" || {
    echo "BLOCKED_BOOTSTRAP_PARENT_NOT_TRAVERSABLE"; return 1;
  }
  test -d "$CACHE_DIR" && test ! -L "$CACHE_DIR" || {
    echo "BLOCKED_BOOTSTRAP_CACHE_DIR_TYPE"; return 1;
  }
  if ! runuser -u www -- test -w "$CACHE_DIR"; then
    owner="$(stat -c '%u' "$CACHE_DIR")"
    mode="$(stat -c '%a' "$CACHE_DIR")"
    if [ "$owner" != 0 ] || [[ ! "$mode" =~ ^(700|750|755|770|775)$ ]]; then
      echo "BLOCKED_BOOTSTRAP_CACHE_DIR_OWNER_MODE=$owner:$mode"; return 1;
    fi
    echo "BOOTSTRAP_CACHE_DIR=REPAIRABLE_ROOT_OWNED:$mode"
  else
    echo "BOOTSTRAP_CACHE_DIR=WRITABLE_BY_WWW"
  fi
  for file in "$CACHE_DIR"/*.php; do
    if [ -L "$file" ]; then
      echo "BLOCKED_BOOTSTRAP_CACHE_SYMLINK=$file"; return 1
    fi
    if [ ! -e "$file" ]; then continue; fi
    test -f "$file" || { echo "BLOCKED_BOOTSTRAP_CACHE_FILE_TYPE=$file"; return 1; }
    if runuser -u www -- test -r "$file" &&
       runuser -u www -- test -w "$file"; then
      echo "BOOTSTRAP_CACHE_FILE=ACCESSIBLE:${file##*/}"
      continue
    fi
    owner="$(stat -c '%u' "$file")"
    mode="$(stat -c '%a' "$file")"
    if [ "$owner" != 0 ] || [[ ! "$mode" =~ ^(600|640|644|660|664)$ ]]; then
      echo "BLOCKED_BOOTSTRAP_CACHE_FILE_OWNER_MODE=${file##*/}:$owner:$mode"; return 1
    fi
    echo "BOOTSTRAP_CACHE_FILE=REPAIRABLE_ROOT_OWNED:${file##*/}:$mode"
  done
}
bootstrap_cache_audit

repair_bootstrap_cache() {
  local file
  # This is only called after private source and MySQL backups succeed.
  if ! runuser -u www -- test -w "$CACHE_DIR"; then
    chgrp www "$CACHE_DIR"
    chmod 0770 "$CACHE_DIR"
    echo "BOOTSTRAP_CACHE_DIR=REPAIRED"
  fi
  runuser -u www -- test -w "$CACHE_DIR" || {
    echo "BLOCKED_BOOTSTRAP_CACHE_DIR_NOT_WRITABLE"; return 1;
  }
  for file in "$CACHE_DIR"/*.php; do
    [ -e "$file" ] || continue
    if ! runuser -u www -- test -r "$file" ||
       ! runuser -u www -- test -w "$file"; then
      test ! -L "$file" && test "$(stat -c '%u' "$file")" = 0 || {
        echo "BLOCKED_BOOTSTRAP_CACHE_FILE_CHANGED=$file"; return 1;
      }
      case "$(stat -c '%a' "$file")" in
        600|640|644|660|664) ;;
        *) echo "BLOCKED_BOOTSTRAP_CACHE_MODE_CHANGED=$file"; return 1 ;;
      esac
      chgrp www "$file"
      chmod 0660 "$file"
      echo "BOOTSTRAP_CACHE_FILE=REPAIRED:${file##*/}"
    fi
    runuser -u www -- test -r "$file" &&
    runuser -u www -- test -w "$file" || {
      echo "BLOCKED_BOOTSTRAP_CACHE_FILE_NOT_ACCESSIBLE=$file"; return 1;
    }
  done
}

# Create any missing source subdirectories with explicit traverse permissions.
# Existing directories retain their ownership and mode. Reject symlinks.
prepare_live_parent() {
  local relative="$1" parent="${1%/*}" current="$APP" part
  local -a parts
  if [ "$parent" = "$relative" ]; then
    echo "BLOCKED_SOURCE_PATH=$relative"; return 1
  fi
  IFS='/' read -r -a parts <<< "$parent"
  for part in "${parts[@]}"; do
    case "$part" in ''|'.'|'..')
      echo "BLOCKED_SOURCE_PARENT_COMPONENT=$relative"; return 1 ;;
    esac
    current="$current/$part"
    if [ -L "$current" ]; then
      echo "BLOCKED_SOURCE_PARENT_SYMLINK=$current"; return 1
    elif [ -e "$current" ]; then
      test -d "$current" || { echo "BLOCKED_SOURCE_PARENT_TYPE=$current"; return 1; }
    else
      install -d -o root -g www -m 0755 "$current"
      echo "CREATED_SOURCE_DIRECTORY=$current"
    fi
  done
}

echo "========== VERIFY EXACT RELEASE AND CI =========="
test "$(gh api "repos/$REPO/commits/$SOURCE" --jq '.sha')" = "$SOURCE"
test "$(gh api "repos/$REPO/actions/runs/$RUN" --jq '.head_sha')" = "$SOURCE"
test "$(gh api "repos/$REPO/actions/runs/$RUN" --jq '.conclusion')" = success
for revision in "$BASE" "$MID" "$SOURCE"; do
  gh api "repos/$REPO/git/trees/$revision?recursive=1" > "$TMP/tree-$revision.json"
done

echo "========== READ-ONLY LIVE FILE COMPATIBILITY =========="
python3 - "$APP" "$TMP" "$BASE" "$MID" "$SOURCE" "$AUTH" <<'PY'
import pathlib,sys,json,hashlib,re
app,tmp=map(pathlib.Path,sys.argv[1:3])
base,mid,target,auth=sys.argv[3:]
def tree(sha):
    d=json.loads((tmp/('tree-'+sha+'.json')).read_text())
    if d.get('truncated'): raise SystemExit('BLOCKED_GITHUB_TREE_TRUNCATED')
    return {f['path']:f['sha'] for f in d['tree'] if f.get('type')=='blob'}
trees=[tree(k) for k in (base,mid,target)]
for rel in (tmp/'paths').read_text().splitlines():
    if rel not in trees[-1]:raise SystemExit('BLOCKED_TARGET_SOURCE='+rel)
    f=app/rel
    exists=f.exists() or f.is_symlink()
    if exists and (f.is_symlink() or not f.is_file()):
        raise SystemExit('BLOCKED_SYMLINK_OR_WRONG_TYPE='+rel)
    if rel==auth:
        if not exists:raise SystemExit('BLOCKED_AUTH_MISSING')
        b=f.read_bytes()
        if len(b)>1048576 or b'\x00' in b:raise SystemExit('BLOCKED_AUTH_NOT_TEXT')
        try:t=b.decode('utf8')
        except UnicodeDecodeError:raise SystemExit('BLOCKED_AUTH_NOT_UTF8')
        if len(re.findall(r'<head(?:\s[^>]*)?>',t,re.I))!=1 or len(re.findall(r'</head\s*>',t,re.I))!=1:
            raise SystemExit('BLOCKED_AUTH_HEAD')
        if 'partials.feedback' not in t or '@vite(' not in t:
            raise SystemExit('BLOCKED_AUTH_MISSING_REQUIRED_ASSETS')
        x=''
        if '<meta name="description"' not in t:
            x += '<meta name="description" content="@yield(' + "'description','Sign in or manage your Gomovie account.'" + ')">\n'
        if 'StudioAppearance::css()' not in t:
            x += '<style>{!! \\App\\Support\\StudioAppearance::css() !!}</style>\n'
        t=re.sub(r'</head\s*>',lambda m:x+m.group(0),t,count=1,flags=re.I)
        dest=tmp/'new'/rel
        dest.parent.mkdir(parents=True,exist_ok=True)
        dest.write_text(t,encoding='utf8')
        print('AUTH_LAYOUT=SAFE_CUSTOM_PRESERVATION')
        continue
    if not exists:
        if rel in trees[0]:raise SystemExit('BLOCKED_EXISTING_FILE_MISSING='+rel)
        print('NEW_FILE='+rel)
        continue
    raw=f.read_bytes()
    blob=hashlib.sha1(('blob '+str(len(raw))+'\x00').encode()+raw).hexdigest()
    allowed={d.get(rel) for d in trees}
    if blob not in allowed:
        if (rel.startswith('resources/views/auth/') or rel.startswith('resources/views/frontend/')) and rel.endswith('.blade.php'):
            if len(raw)>1048576 or b'\x00' in raw:
                raise SystemExit('BLOCKED_CUSTOM_AUTH_NOT_TEXT='+rel)
            try: raw.decode('utf-8')
            except UnicodeDecodeError: raise SystemExit('BLOCKED_CUSTOM_AUTH_NOT_UTF8='+rel)
            with (tmp/'custom-auth-merges').open('a') as f_list:
                f_list.write(rel+'\n')
            print('CUSTOM_VIEW_REQUIRES_SAFE_MERGE='+rel)
            continue
        print('BLOCKED_CUSTOM_FILE='+rel)
        print('CURRENT_BLOB='+blob)
        with (tmp/'unsupported-custom').open('a') as out:
            out.write(rel+'\n')
        continue
    print('KNOWN_SOURCE='+rel)
print('GOMOVIE_AAPANEL_SCAN=COMPLETE')
PY

# A customized authentication page must be merged with the pinned baseline
# and target without discarding local changes. The merge is prepared entirely
# in the private temporary directory, including during --check.
if [ -s "$TMP/custom-auth-merges" ]; then
  echo "========== VERIFY AND PREPARE CUSTOM VIEW MERGES =========="
  while IFS= read -r f; do
    for rev in "$BASE" "$SOURCE"; do
      if [ "$rev" = "$BASE" ]; then dest="$TMP/auth-base/$f"; else dest="$TMP/auth-target/$f"; fi
      mkdir -p "$(dirname "$dest")"
      gh api -H "Accept: application/vnd.github.raw+json" \
        "repos/$REPO/contents/$f?ref=$rev" > "$dest"
      test -s "$dest"
    done
    python3 - "$TMP" "$BASE" "$SOURCE" "$f" <<'PY_AUTH_HASH'
import hashlib,json,pathlib,sys
tmp=pathlib.Path(sys.argv[1])
base,target,rel=sys.argv[2:]
for rev,folder in ((base,'auth-base'),(target,'auth-target')):
    data=(tmp/folder/rel).read_bytes()
    got=hashlib.sha1(('blob '+str(len(data))+'\0').encode()+data).hexdigest()
    tree=json.loads((tmp/('tree-'+rev+'.json')).read_text())
    expected=next((x['sha'] for x in tree['tree'] if x.get('path')==rel and x.get('type')=='blob'),None)
    if got != expected: raise SystemExit('BLOCKED_AUTH_SOURCE_HASH='+rel)
PY_AUTH_HASH
    mkdir -p "$TMP/new/$(dirname "$f")"
    if ! git merge-file -p -- "$APP/$f" "$TMP/auth-base/$f" "$TMP/auth-target/$f" > "$TMP/new/$f"; then
      # Authentication pages often have intentionally customized form markup.
      # Apply ONLY the target's Appearance text slots to existing live elements;
      # never replace form markup, buttons, inputs, routes or layout attributes.
      if [[ "$f" != resources/views/auth/*.blade.php ]]; then
        echo "BLOCKED_CUSTOM_MERGE_CONFLICT=$f"
        echo "$f" >> "$TMP/merge-conflicts"
        continue
      fi
      echo "AUTH_MERGE_CONFLICT=TRYING_TEXT_ONLY_OVERLAY:$f"
      if ! python3 - "$APP/$f" "$TMP/auth-target/$f" "$TMP/new/$f" <<'PY_AUTH_OVERLAY'
import sys, pathlib, re, html
live_path, target_path, dest_path = map(pathlib.Path, sys.argv[1:])
original=live_path.read_text(encoding='utf-8')
target=target_path.read_text(encoding='utf-8')
rel=str(live_path)
def stop(reason):
    raise SystemExit('BLOCKED_AUTH_OVERLAY_'+reason+'='+live_path.name)
if len(original)>1048576 or '\x00' in original:
    stop('SIZE')
slots=re.findall(r"StudioAppearance::text\(\s*'([^']+)'\s*,\s*'([^']+)'",target)
slot_map={field:page for page,field in slots}
if len(slot_map)!=len(slots) or not {'seo_title','heading','description'}<=set(slot_map):
    stop('TARGET_SLOTS')
if len(set(slot_map.values()))!=1:
    stop('TARGET_PAGE_ID')
page=next(iter(slot_map.values()))
if not re.fullmatch('[a-z0-9-]+',page):
    stop('PAGE_ID')
def quote(text):
    if '\r' in text or '\n' in text or '\x00' in text:
        stop('MULTILINE_TEXT')
    return "'"+text.replace('\\','\\\\').replace("'","\\'")+"'"
section_pattern=r"(?m)^@section\(\s*(['\"])(?P<name>title|description)\1\s*,\s*(['\"])(?P<value>.*?)\3\s*\)[ \t]*$"
def section(doc,field,may_insert):
    matches=[m for m in re.finditer(section_pattern,doc) if m.group('name')==field]
    if len(matches)>1:
        stop('DUPLICATE_'+field.upper())
    if matches:
        m=matches[0]
        key='seo_title' if field=='title' else 'seo_description'
        arg=quote(html.unescape(m.group('value')))
        replacement="@section('"+field+"',\\App\\Support\\StudioAppearance::text("+quote(page)+","+quote(key)+","+arg+"))"
        return doc[:m.start()]+replacement+doc[m.end():]
    if not may_insert:
        stop('MISSING_'+field.upper())
    if re.search(r"@section\(\s*['\"]"+re.escape(field)+r"['\"]\s*,",doc):
        stop('DYNAMIC_'+field.upper())
    line=next((line for line in target.splitlines() if line.startswith("@section('description',") and 'StudioAppearance::text' in line),None)
    if line is None: stop('TARGET_DESCRIPTION')
    mark="@section('content')"
    if doc.count(mark)!=1: stop('CONTENT_SECTION')
    return doc.replace(mark,line+'\n'+mark,1)
modified=section(original,'title',False)
if 'seo_description' in slot_map:
    modified=section(modified,'description',True)
def replace_content(doc,tag,css_class,field):
    pat=re.compile(r'(<'+tag+r'\b[^>]*>)(.*?)(</'+tag+r'\s*>)',re.S|re.I)
    found=[]
    for m in pat.finditer(doc):
        cls=re.search(r'\bclass\s*=\s*(["\'])(.*?)\1',m.group(1),re.S)
        if cls and css_class in cls.group(2).split():
            found.append(m)
    if len(found)!=1:
        stop('MISSING_OR_DUPLICATE_'+field.upper())
    m=found[0]
    inner=m.group(2)
    if re.search(r'<|>|\{\{|\}\}|@(?:if|php|include|foreach|endif)',inner):
        stop('COMPLEX_'+field.upper())
    left=inner[:len(inner)-len(inner.lstrip())]
    right=inner[len(inner.rstrip()):]
    fallback=html.unescape(inner.strip())
    if not fallback: stop('EMPTY_'+field.upper())
    expression='{{ \\App\\Support\\StudioAppearance::text('+quote(page)+','+quote(field)+','+quote(fallback)+') }}'
    new_tag=m.group(1)+left+expression+right+m.group(3)
    return doc[:m.start()]+new_tag+doc[m.end():]
modified=replace_content(modified,'h1','gm-auth-title','heading')
modified=replace_content(modified,'p','gm-auth-subtitle','description')
actual=set(re.findall(r"StudioAppearance::text\(\s*'([^']+)'\s*,\s*'([^']+)'",modified))
if set(slots)-actual: stop('MISSING_SLOTS')
if original.count('@csrf')!=modified.count('@csrf'): stop('CSRF_CHANGED')
if original.count('<form')!=modified.count('<form') or original.count('</form>')!=modified.count('</form>'):
    stop('FORM_STRUCTURE')
if not re.search(r"@extends\(\s*['\"]layouts\.auth['\"]\s*\)",modified):
    stop('LAYOUT')
if re.search(r'^(<<<<<<<|=======|>>>>>>>)',modified,re.M):
    stop('CONFLICT_MARKERS')
dest_path.write_text(modified,encoding='utf-8')
print('SAFE_AUTH_TEXT_ONLY_OVERLAY_READY='+live_path.name)
PY_AUTH_OVERLAY
      then
        echo "BLOCKED_AUTH_OVERLAY_NEEDS_REVIEW=$f"
        echo "$f" >> "$TMP/merge-conflicts"
        continue
      fi
    fi
    if ! python3 - "$TMP" "$APP" "$f" <<'PY_AUTH_VERIFY'
import pathlib,re,sys
tmp=pathlib.Path(sys.argv[1]); app=pathlib.Path(sys.argv[2]); rel=sys.argv[3]
merged=(tmp/'new'/rel).read_text(encoding='utf8')
target=(tmp/'auth-target'/rel).read_text(encoding='utf8')
original=(app/rel).read_text(encoding='utf8')
if not merged or len(merged)>1048576:
    raise SystemExit('BLOCKED_AUTH_MERGE_SIZE='+rel)
if re.search(r'^(<<<<<<< |=======|>>>>>>> )',merged,re.M):
    raise SystemExit('BLOCKED_AUTH_MERGE_MARKERS='+rel)
layout='layouts.app' if rel.startswith('resources/views/frontend/') else 'layouts.auth'
if not re.search(r"@extends\s*\(\s*['\"]"+re.escape(layout)+r"['\"]",merged):
    raise SystemExit('BLOCKED_VIEW_MERGE_LAYOUT='+rel)
if not re.search(r"@section\s*\(\s*['\"]content['\"]",merged):
    raise SystemExit('BLOCKED_AUTH_MERGE_CONTENT='+rel)
slots=lambda txt:set(re.findall(r"StudioAppearance::text\(\s*'([^']+)'\s*,\s*'([^']+)'",txt))
required=slots(target)
if 'StudioAppearance::text' in target and not required:
    raise SystemExit('BLOCKED_AUTH_MERGE_SLOT_PATTERN='+rel)
if required-slots(merged):
    raise SystemExit('BLOCKED_AUTH_MERGE_MISSING_APPEARANCE_SLOTS='+rel)
if target.count('@csrf')>merged.count('@csrf'):
    raise SystemExit('BLOCKED_AUTH_MERGE_CSRF='+rel)
if rel.startswith('resources/views/frontend/'):
    # The release may update text and URLs, but must not change HTML
    # element structure, CSS classes, IDs, or interactive data attributes.
    def tag_signature(text):
        return re.findall(r'<\s*/?\s*[A-Za-z][A-Za-z0-9:-]*\b',text)
    def class_signature(text):
        return re.findall(r'\bclass="[^"]*"',text)
    def id_signature(text):
        return re.findall(r'\bid="[^"]*"',text)
    def hook_signature(text):
        return re.findall(r'\bdata-[a-zA-Z0-9-]+',text)
    for label,signature in (
        ('TAGS',tag_signature),('CLASSES',class_signature),
        ('IDS',id_signature),('DATA_HOOKS',hook_signature)
    ):
        if signature(original)!=signature(merged):
            raise SystemExit('BLOCKED_FRONTEND_STRUCTURE_'+label+'='+rel)
print('SAFE_CUSTOM_VIEW_MERGE_READY='+rel)
PY_AUTH_VERIFY
    then
      echo "$f" >> "$TMP/merge-conflicts"
      continue
    fi
  done < "$TMP/custom-auth-merges"
fi

if [ -s "$TMP/unsupported-custom" ] || [ -s "$TMP/merge-conflicts" ]; then
  echo "========== ALL DEPLOYMENT CONFLICTS =========="
  if [ -s "$TMP/unsupported-custom" ]; then
    echo "UNSUPPORTED_FILES:"
    cat "$TMP/unsupported-custom"
  fi
  if [ -s "$TMP/merge-conflicts" ]; then
    echo "MERGE_CONFLICTS_OR_STRUCTURE_CHANGES:"
    cat "$TMP/merge-conflicts"
  fi
  echo "PREFLIGHT=BLOCKED"
  echo "LIVE_FILES_CHANGED=NO"
  echo "DATABASE_CHANGED=NO"
  exit 1
fi
echo "GOMOVIE_AAPANEL_PREFLIGHT=PASS"

if [ "$MODE" = "--check" ]; then
  echo "LIVE_FILES_CHANGED=NO"
  echo "DATABASE_CHANGED=NO"
  exit 0
fi

echo "========== DOWNLOAD VERIFIED COMPILED CI PACKAGE =========="
gh run download "$RUN" --repo "$REPO" --name gomovie-installer-qa-package --dir "$TMP/package"
ZIP="$(find "$TMP/package" -maxdepth 1 -type f -name 'Gomovie-Installer-QA-*.zip' -print -quit)"
test -n "$ZIP" && test -f "$ZIP.sha256"
test "$(find "$TMP/package" -maxdepth 1 -type f -name 'Gomovie-Installer-QA-*.zip' | wc -l)" -eq 1
EXPECTED_ZIP_SHA="$(awk 'NR==1 {print $1}' "$ZIP.sha256")"
test "$EXPECTED_ZIP_SHA" = "$(sha256sum "$ZIP" | awk '{print $1}')" || { echo "BLOCKED_ZIP_CHECKSUM"; exit 1; }
echo "CI_PACKAGE_SHA256=VERIFIED"
unzip -tqq "$ZIP"
while IFS= read -r f; do
  if [ "$f" = "$AUTH" ]; then continue; fi
  mkdir -p "$TMP/ci-source/$(dirname "$f")" "$TMP/new/$(dirname "$f")"
  unzip -p "$ZIP" "$f" > "$TMP/ci-source/$f"
  test -s "$TMP/ci-source/$f"
  case "$f" in *.blade.php) ;; *.php) "$PHP" -l "$TMP/ci-source/$f" >/dev/null ;; esac
  if [ ! -f "$TMP/new/$f" ]; then
    cp "$TMP/ci-source/$f" "$TMP/new/$f"
  fi
done < "$TMP/paths"
python3 - "$TMP" "$SOURCE" "$AUTH" <<'PY'
import sys,pathlib,json,hashlib
tmp=pathlib.Path(sys.argv[1])
tree=json.loads((tmp/('tree-'+sys.argv[2]+'.json')).read_text())
refs={x['path']:x['sha'] for x in tree['tree'] if x.get('type')=='blob'}
for p in (tmp/'paths').read_text().splitlines():
    if p==sys.argv[3]:continue
    content=(tmp/'ci-source'/p).read_bytes()
    sha=hashlib.sha1(('blob '+str(len(content))+'\x00').encode()+content).hexdigest()
    if refs.get(p)!=sha:raise SystemExit('BLOCKED_CI_BLOB_MISMATCH='+p)
print('CI_SOURCE_BLOBS=VERIFIED')
PY

echo "========== VERIFY COMPILED FRONTEND ASSETS =========="
unzip -q "$ZIP" 'public/build/*' -d "$TMP/extract"
BUILD="$TMP/extract/public/build"
test -f "$BUILD/manifest.json" && test ! -L "$BUILD/manifest.json"
test -z "$(find "$BUILD" -type l -print -quit)"
"$PHP" -r '
$m=json_decode(file_get_contents($argv[1]."/manifest.json"),true,512,JSON_THROW_ON_ERROR);
foreach (["resources/css/app.css","resources/js/app.js"] as $name) {
 $f=$m[$name]["file"]??null;
 if (!$f || !is_file($argv[1]."/".$f)) exit(3);
}
' "$BUILD"
while IFS= read -r -d '' src; do
  rel="$(realpath --relative-to="$BUILD" "$src")"
  case "$rel" in manifest.json|fonts-manifest.json|assets/*) ;; *) echo "BLOCKED_ASSET_PATH=$rel"; exit 1;; esac
  dst="$APP/public/build/$rel"
  case "$rel" in
    assets/*)
      if [ -e "$dst" ] || [ -L "$dst" ]; then
        test -f "$dst" && test ! -L "$dst" && cmp -s "$src" "$dst" || { echo "BLOCKED_CHANGED_ASSET=$rel"; exit 1; }
      fi ;;
    fonts-manifest.json)
      if [ -e "$dst" ] || [ -L "$dst" ]; then test -f "$dst" && test ! -L "$dst"; fi ;;
  esac
done < <(find "$BUILD" -type f -print0)
echo "CI_FRONTEND_BUILD=VERIFIED"

echo "========== BACKUP LIVE SOURCES =========="
while IFS= read -r f; do
  if [ -f "$APP/$f" ]; then
    mkdir -p "$TMP/old/$(dirname "$f")"
    cp -p "$APP/$f" "$TMP/old/$f"
  fi
done < "$TMP/paths"
cp -p "$APP/public/build/manifest.json" "$TMP/old/manifest.json"
if [ -e "$APP/public/build/fonts-manifest.json" ]; then
  test -f "$APP/public/build/fonts-manifest.json" && test ! -L "$APP/public/build/fonts-manifest.json"
  cp -p "$APP/public/build/fonts-manifest.json" "$TMP/old/fonts-manifest.json"
fi

echo "========== PRIVATE MYSQL DATABASE BACKUP =========="
APP_PATH="$APP" BACKUP_PATH="$TMP" "$PHP" <<'PHP'
<?php
require_once getenv('APP_PATH').'/vendor/autoload.php';
$app=require getenv('APP_PATH').'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c=config('database.connections.'.config('database.default'));
if (($c['driver']??null)!=='mysql') {fwrite(STDERR,"BLOCKED_DATABASE_DRIVER\n");exit(8);}
$name=(string)($c['database']??'');
if (!preg_match('/\A[A-Za-z0-9_]+\z/D',$name)) exit(9);
$q=static fn($v):string=>'"'.str_replace(["\\",'"',"\n","\r"],["\\\\",'\\"','',''],(string)$v).'"';
$cfg="[client]\n";
foreach (['host','port','username'=>'user','password','unix_socket'=>'socket'] as $k=>$v) {
 $field=is_int($k)?$v:$k;
 $out=is_int($k)?$v:$v;
 if (isset($c[$field]) && (string)$c[$field]!=='') $cfg.=$out.'='.$q($c[$field])."\n";
}
$root=getenv('BACKUP_PATH');
file_put_contents($root.'/mysql.cnf',$cfg);chmod($root.'/mysql.cnf',0600);
file_put_contents($root.'/db-name',$name);chmod($root.'/db-name',0600);
PHP
NAME="$(cat "$TMP/db-name")"
"$DUMP" --defaults-extra-file="$TMP/mysql.cnf" --single-transaction --quick \
  --no-tablespaces --set-gtid-purged=OFF "$NAME" | gzip -1 > "$TMP/database.sql.gz"
test -s "$TMP/database.sql.gz" && gzip -t "$TMP/database.sql.gz"
rm -f -- "$TMP/mysql.cnf" "$TMP/db-name"
echo "DATABASE_BACKUP=VERIFIED"

echo "========== RECHECK ORIGINAL FILES =========="
while IFS= read -r f; do
  if [ -f "$TMP/old/$f" ]; then
    test -f "$APP/$f" && test ! -L "$APP/$f"
    cmp -s "$TMP/old/$f" "$APP/$f"
  else
    test ! -e "$APP/$f" && test ! -L "$APP/$f"
  fi
done < "$TMP/paths"
cmp -s "$TMP/old/manifest.json" "$APP/public/build/manifest.json"

echo "========== APPLY THREE ADDITIVE APPEARANCE MIGRATIONS =========="
CHANGED=1
# Repair only the known empty 0700 directory from the prior rollback,
# or create the new directory with readable/traversable permissions.
if [ ! -e "$APPEARANCE_VIEW_DIR" ]; then
  install -d -o root -g www -m 0755 "$APPEARANCE_VIEW_DIR"
  echo "APPEARANCE_VIEW_DIRECTORY=CREATED_0755"
elif ! runuser -u www -- test -x "$APPEARANCE_VIEW_DIR"; then
  test "$(stat -c '%u:%a' "$APPEARANCE_VIEW_DIR")" = "0:700"
  test -z "$(find "$APPEARANCE_VIEW_DIR" -mindepth 1 -maxdepth 1 -print -quit)"
  install -d -o root -g www -m 0755 "$APPEARANCE_VIEW_DIR"
  echo "APPEARANCE_VIEW_DIRECTORY=REPAIRED_0755"
fi
runuser -u www -- test -x "$APPEARANCE_VIEW_DIR" || {
  echo "BLOCKED_APPEARANCE_DIRECTORY_NOT_TRAVERSABLE"; exit 1;
}
echo "========== REPAIR TRUSTED BOOTSTRAP CACHE PERMISSIONS =========="
bootstrap_cache_audit
repair_bootstrap_cache
for migration in \
  database/migrations/2026_11_09_000400_create_studio_appearance_tables.php \
  database/migrations/2026_11_09_000500_create_studio_content_slots_table.php \
  database/migrations/2026_11_09_000600_add_studio_typography_variants.php; do
  prepare_live_parent "$migration"
  install -o root -g www -m 0644 "$TMP/new/$migration" "$APP/$migration"
  ( cd "$APP" && runuser -u www -- "$PHP" artisan migrate --path="$migration" --force --no-interaction )
done
echo "APPEARANCE_MIGRATIONS=APPLIED"

echo "========== INSTALL VITE ASSETS =========="
while IFS= read -r -d '' src; do
  rel="$(realpath --relative-to="$BUILD" "$src")"
  case "$rel" in
    manifest.json) continue ;;
    assets/*|fonts-manifest.json)
      dst="$APP/public/build/$rel"
      install -d -o root -g www -m 0755 "$(dirname "$dst")"
      if [ "$rel" = fonts-manifest.json ] || [ ! -e "$dst" ]; then
        install -o root -g www -m 0644 "$src" "$dst"
      fi ;;
  esac
done < <(find "$BUILD" -type f -print0)

echo "========== INSTALL SELECTIVE STUDIO SOURCE =========="
while IFS= read -r f; do
  case "$f" in database/migrations/*) continue;; esac
  prepare_live_parent "$f"
  install -o root -g www -m 0644 "$TMP/new/$f" "$APP/$f"
done < "$TMP/paths"
test ! -e "$APP/public/build/manifest.json.gomovie-next"
install -o root -g www -m 0644 "$BUILD/manifest.json" "$APP/public/build/manifest.json.gomovie-next"
mv -- "$APP/public/build/manifest.json.gomovie-next" "$APP/public/build/manifest.json"

echo "========== LARAVEL AND WEBSITE SMOKE TESTS =========="
runuser -u www -- test -r "$APPEARANCE_VIEW_DIR/index.blade.php" || {
  echo "BLOCKED_APPEARANCE_TEMPLATE_UNREADABLE"; exit 1;
}
(
 cd "$APP"
 runuser -u www -- "$PHP" artisan optimize:clear --no-interaction
 runuser -u www -- "$PHP" artisan view:cache --no-interaction
 runuser -u www -- "$PHP" artisan route:list --path=appearance
 runuser -u www -- "$PHP" artisan route:list --path=account
)
for uri in / /about /browse /login /register; do
  status="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' "https://$HOST$uri")"
  echo "PUBLIC_ROUTE=$uri HTTP=$status"
  test "$status" = 200
done
for uri in /dashboard/appearance /account; do
  status="$(curl --noproxy '*' -sS --connect-timeout 5 --max-time 20 \
    --resolve "$HOST:443:127.0.0.1" -o /dev/null -w '%{http_code}' "https://$HOST$uri")"
  echo "PROTECTED_ROUTE=$uri GUEST_HTTP=$status"
  test "$status" = 302
done

echo "========== VERIFY FINAL FILE INTEGRITY =========="
while IFS= read -r f; do
  cmp -s "$APP/$f" "$TMP/new/$f" || { echo "BLOCKED_INSTALLED_MISMATCH=$f"; exit 1; }
done < "$TMP/paths"
cmp -s "$APP/public/build/manifest.json" "$BUILD/manifest.json"
COMPLETE=1
echo "GOMOVIE_ACCOUNT_APPEARANCE=INSTALLED"
echo "CUSTOM_AUTH=CONTENT_PRESERVED"
echo "DATABASE_CHANGE=APPEARANCE_ONLY"
echo "PRIVATE_VIDEOS=UNCHANGED"
echo "PUBLIC_STREAMING=UNCHANGED"
echo "PAYMENTS=UNCHANGED"
echo "GOMOVIE_AAPANEL_DEPLOYMENT=SUCCESS"
echo "BACKUP_DIRECTORY=$TMP"
