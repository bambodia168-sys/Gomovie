# Gomovie — independent installation, upgrades, and recovery

## Scope and non-negotiable limits

The source includes **read-only preflight**, **read-only offline backup checksum verification**, and a **synthetic SQLite recovery rehearsal**. None proves a real aaPanel or cPanel installation, an off-site backup, a MySQL/PostgreSQL restoration, deliverable SMTP, or a legal/commercial release. Never run a production migration, restore, or permission change solely because a command here prints PASS.

PHP 8.4+ and the Laravel 13 required extensions, compatible Composer dependencies, and precompiled Vite assets are required. Production must have a real HTTPS origin, APP_DEBUG=false, a private .env, and an isolated web document root pointing to **public/**. The production database must be MySQL 8 or PostgreSQL 16 until other engines are independently accepted; SQLite is a developer/CI rehearsal only.

## Fresh independent installation (candidate isolated host)

1. Provision a **new isolated database** and an OS account with least privilege. Do not share a live application's database. Keep application source, vendor files, .env and private media **outside the served document root**. For aaPanel, confirm the nginx vhost root; for cPanel, independently confirm that the document root can safely target the Laravel public/ folder. Do not move .env or index.php into an arbitrary public_html directory to bypass this requirement.
2. From a vetted release archive, install vendor dependencies, include the built public/build assets, and create a non-world-readable .env from .env.example. Set real domain, APP_ENV=production, APP_DEBUG=false, database credentials, an independently generated APP_KEY, mail and filesystem configuration. Keep billing/edge/transcoding/staging toggles **false**.
3. Verify permissions for storage/framework/views, storage/logs and storage/app/private, and confirm the PHP process does not have broad write access to repository source. Use the host's supported PHP runtime.
4. Before any mutation run **php artisan gomovie:install-preflight --mode=install --json**. A failure means correct the host or isolate another database. The command must see an unused schema; it intentionally refuses to treat an existing installation as fresh.
5. Once independently approved, apply **php artisan migrate --force**, inspect the migration register and the resulting application from its intended HTTPS domain. Configure verified SMTP, worker/scheduler supervision, monitoring and operator account promotion only after acceptance. Do not grant super_admin over HTTP, through a fixture, or from registration.

The preflight is read-only. No installer writes .env, provisions MySQL accounts, automatically migrates a production schema, or creates a default administrator.

## Production upgrade — backup-first, no destructive shortcuts

1. Record the exact old and candidate Git commit, release artifact hash, installed PHP version, MySQL/PG version, and current migration status. Verify **independently** that the maintenance window, worker drain, and rollback owner are assigned.
2. From a trusted backup system, take a **consistent database backup**, a private-media archive (even if empty), and an **encrypted** configuration backup (secrets must never enter CI, chat, logs or source control). Store these artifacts off the application host with separate access controls and retention.
3. Construct a restricted offline bundle containing manifest.json and all three backup artifacts. See the schema below. SHA-256 checks only detect mismatch relative to the manifest. A maliciously replaced manifest can also carry different hashes: independently authenticate/sign the manifest or rely on a trusted immutable inventory.
4. On the operator's isolated backup machine, run **php artisan gomovie:recovery-check /secure/offline-bundle --json**. Then perform a **full restore drill on a different isolated host** using the target database engine, with private objects, encrypted configuration, migration consistency, playback disabled and tests for real record counts and access segregation. Save signed human acceptance evidence.
5. Run **php artisan gomovie:install-preflight --mode=upgrade --recovery-bundle=/secure/offline-bundle --json** on the candidate host. It intentionally requires existing schema and a verified artifact bundle; it **cannot** certify a restore drill.
6. Only after approved restore evidence: stop writes, drain workers, deploy code and built assets to a separate release directory, switch to maintenance, apply **php artisan migrate --force**, check queues, site HTTPS and application routes, then release traffic. Use atomic release switching if supported; do not remove the previous release until rollback acceptance.
7. If checks fail, keep traffic blocked and **restore the matching prior code, schema and private media together** from the independently verified backup on an isolated recovery path. Never run migrate:fresh or an improvised down migration against customer data. Validate audit rows, authorizations, and entitlements before re-opening service.

**Do not run php artisan test against production**: RefreshDatabase and migration fixtures can destroy data. Run tests against isolated QA databases only. For cPanel without safe persistent queue supervisors, keep media transcoding off; cron alone is not an accepted replacement for isolated FFmpeg workers.

## Offline manifest contract: gomovie-recovery-v1

The manifest contains **only hashes and file metadata**; no connection credentials, tokens, secret values, or application userdata.

~~~json
{
  "format": "gomovie-recovery-v1",
  "revision": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
  "artifacts": [
    {"role": "database", "filename": "database.sql.gz", "size_bytes": 1000, "sha256": "REPLACE_WITH_64_LOWERCASE_HEX"},
    {"role": "private_media", "filename": "private-media.tar.gz", "size_bytes": 1000, "sha256": "REPLACE_WITH_64_LOWERCASE_HEX"},
    {"role": "configuration", "filename": "config.age", "size_bytes": 1000, "sha256": "REPLACE_WITH_64_LOWERCASE_HEX"}
  ]
}
~~~

Use the actual 40-character Git commit SHA, positive byte counts, and actual lowercase SHA-256 values. Artifact filenames must be **single basenames**, not paths or symlinks. The configuration artifact must have a .age or .gpg filename. The checker verifies presence, name safety, sizes and hashes; it **does not** verify that the archive is decryptable, is correctly encrypted, is a valid SQL dump, is an off-site copy, or can actually be restored.

Both recovery and preflight commands return nonzero when checks fail and emit only safe status keys (not credentials or archive contents).

## Synthetic automated acceptance

~~~sh
bash support/recovery-rehearsal.sh
~~~

This script creates disposable SQLite databases exclusively inside a new private temporary directory, migrates an empty installation, inserts synthetic data, copies an offline snapshot, restores to another file and verifies both schema and data. It never accepts a production database path and deliberately prints **PRODUCTION_OR_OFFSITE_RESTORATION_VERIFIED=NO**.

GitHub CI also tests source migrations and features against MySQL 8 / PostgreSQL 16. These are not substitutes for an independent **physical** restore of real anonymized backup artifacts in actual aaPanel and cPanel environments.

## Real-environment evidence to collect

For **each** aaPanel and cPanel host, capture evidence with timestamps and responsible reviewer for: clean install; secure public/ document root; release ZIP and dependency checksum; minimum PHP extensions; TLS/HSTS/cookies; existing installation upgrade; migration and runtime logs; consistent database/media/config backups; verified off-site restore; rollback; default worker plus media worker supervision; cron and queue latency; email delivery; edge private bucket ACL; malware/rights attestation; device/browser coverage; and legal/payment requirements.

Mark untested items as **not accepted**. A CI pass or operator-entered reference is not a production signoff.

## Independent host-evidence register

For actual aaPanel and cPanel acceptance, a two-person CLI evidence registry and read-only System Health matrix are now available. Source CI cannot populate genuine host evidence. See [host acceptance](HOST_ACCEPTANCE.md) for exact per-host control IDs, responsibilities and manual signoff procedures.
