# Gomovie — independent aaPanel and cPanel host acceptance

**Current status: not externally verified.** Green GitHub Actions, synthetic SQLite restore and operator-entered evidence IDs are not proof of a real-host installation or live customer readiness.

## Per-host acceptance procedure

Prepare one dedicated aaPanel host and a separate cPanel host, without shared databases, media objects, customer accounts or operational keys. Record a release commit, source checksum, database and PHP versions, operator, independent reviewer, timestamps and any sanitized error evidence.

1. Clean install: provision a fresh empty MySQL 8 or PostgreSQL 16 database, configure an application user with least privilege, private environment file and public/ document root. Run the read-only gomovie:install-preflight --mode=install --json and complete only the operator-approved installation steps. Collect resulting migration checks, HTTP denial tests for private files and actual TLS/cookie validation.
2. Data-preserving upgrade: create anonymized non-empty QA records, record prior schema migration and code release, and generate a consistent off-site recovery bundle with encrypted configuration. Run gomovie:recovery-check and preflight --mode=upgrade. Apply upgrades on isolated QA, verify all original records, permissions and application behavior.
3. Recovery and rollback: restore a different isolated host from the off-site backup. Compare relational data, private objects, user access controls, checksum lineage, and the old release's behavior. Rehearse interrupted upgrades and lost primary storage. Do not claim completion because an archive hash matches.
4. Runtime: check HTTPS, email verification and reset delivery at real SMTP, queue execution under supervised workers, scheduler and job recovery, monitoring alerts and owner response. Shared hosting without persistent worker supervision cannot accept unattended FFmpeg media processing.
5. Media, devices and legal: independently validate signed private edge denial/revocation and R2 ACL, authorized real licensed video output and malware scans, age/territorial licensing, DRM where applicable, accessibility and physical mobile/tablet browser tests. Legal localization and payment compliance remain independent gated workstreams.

Do NOT run PHPUnit RefreshDatabase or migrate:fresh against customer or production databases. CI tests and all rehearsal fixtures use isolated throwaway data.

## Audited evidence registry

The backend has a per-host acceptance register, visible in the administrator System Health page. An authenticated server operator using the CLI submits a **non-secret external ticket/reference identifier** only after performing the real host check. A different, verified super-admin independently reviews the evidence before using the CLI to accept or reject the item. Browser users cannot mutate these records. Decisions are preserved in admin audit events.

Examples (replace invalid placeholder addresses with existing verified admin accounts only):

~~~sh
php artisan gomovie:acceptance-submit aapanel offsite_restore \
  --actor=operator@example.invalid --reference=REAL-HOST-RECOVERY-TICKET-001

php artisan gomovie:acceptance-review aapanel offsite_restore \
  --reviewer=reviewer@example.invalid --decision=accept
~~~

Each operation is interactive and aborts unless confirmed. The reviewer cannot be the evidence submitter; accepted evidence cannot be silently rewritten, and rejected evidence may be replaced for re-review. Never use secrets, account identifiers, customer personal data, signed links or backup paths as reference values.

| Control ID | Real-world acceptance evidence |
| --- | --- |
| clean_install | Independent clean customer-style installation |
| docroot_tls | Restricted public document root, TLS and session cookies |
| schema_upgrade | Data-preserving migrations and upgrade rehearsal |
| offsite_restore | Off-site backup and verified isolated restoration |
| rollback | Failed-release rollback with data/media consistency |
| queue_supervision | Default and media queue worker monitoring/recovery |
| smtp | Delivered verification and account-recovery emails |
| private_storage | Private object bucket, signed edge and denial tests |
| media_rights | Actual malware scan, licensing and media output evidence |
| device_acceptance | Physical device, browser and accessibility coverage |
| legal_localization | Legal/policy approval and localized UX verification |
| payments | Provider onboarding, webhooks, refunds and reconciliation |
| monitoring | Delivered alerts and operational incident recovery |

Controls are tracked **separately** for aapanel and cpanel. Even 26 accepted references remain a manual attestation: the application always reports production_release_accepted=false and does not enable billing, DRM, protected streaming, transcoding or media staging from the register. Actual compliance, customer deployment and production activation require separate independent signoff.
