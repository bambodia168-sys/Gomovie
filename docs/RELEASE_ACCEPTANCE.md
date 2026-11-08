# Gomovie — production-release acceptance gates

All gates require reproducible operator evidence and explicit signoff. Passing CI does not mark this project ready for commercial release.

| Workstream | Repository implementation | Production acceptance |
| --- | --- | --- |
| Authentication | Registration, verified email, password recovery/settings, roles, profiles | SMTP provider delivery, cookies, abuse prevention, security test, privacy/data deletion, real-device acceptance |
| Content management | Admin metadata CRUD, seasons/episodes, artwork and draft TMDB import | Rights-review evidence, audit trail, provider quota/retry/attribution, moderation, localization |
| Secure uploads | Local quarantine, SHA-256 verification, operator-only intake and independent evidence-reviewed HLS package inspection | Malware scanning, rights attestation, private object storage, file size/codec/resource limits |
| Transcoding | FFmpeg HLS multi-rendition job plus synthetic fixture | Isolated workers, dedicated queue timeouts, authorized real-video test, captions, audio QC, adaptive ABR QA |
| Private media edge | Signed HLS grant + prototype Worker + Laravel internal authorization | Private R2 deployment, encrypted key/segment protection, load/concurrency, replay/revocation tests, WAF, region restrictions |
| DRM | None; DRM assets fail closed | Vendor contracts and Widevine/FairPlay/PlayReady licensing where required |
| Frontend | Dynamic carousel/cards, discover/detail, My List/history, gated HLS player | Responsive visual audit, keyboard/accessibility, physical mobile/tablet tests, native HLS retry/renewal |
| Subscriptions | Plan/rental schema, active-subscription read check, disabled checkout | Provider onboarding/eligibility, verified webhooks and idempotency, taxes, invoices, refunds, chargebacks, subscriptions |
| Hosting | Laravel source and CI DB suites | Read-only installation preflight, offline recovery integrity verifier and synthetic SQLite restore drill; independently perform real aaPanel/cPanel install, upgrade, rollback and off-site restoration, TLS and mail/queue tuning |
| Operations & queue reliability | Read-only health diagnostics, opt-in worker probes, isolated long media queue | Real aaPanel/cPanel worker supervision, cron execution, Redis failover, queue contention and incident recovery acceptance |
| Security & legal | Basic access control, rights gate, read-only preflight, separate secrets | External pentest, license/policy approval, retention/deletion controls, observability, incident handling |

## New source-only acceptance checks

- `gomovie:install-preflight --mode=install|upgrade --json` validates runtime, schema and configuration safely; upgrade requires a checksum-verified offline recovery bundle.
- `gomovie:recovery-check /offline/bundle --json` validates strict manifests and SHA-256 without reading secrets or restoring data.
- `bash support/recovery-rehearsal.sh` runs disposable SQLite install and restore with synthetic data in CI; it is not off-site or production restoration.
- `gomovie:review-media` records external evidence attestation under separated administrator duties; `gomovie:stage-media` copies to **private, unreleased** object storage only after review and an explicit switch. No ready media assets are created.

## Non-negotiable default safeguards

- Billing, private media edge and transcoding remain disabled until their independent release gates are accepted.
- No production credentials or licensed media files are committed.
- No production server was accessed by this code-development milestone.
- No user-controlled request can self-grant super_admin, verify stream rights, approve an upload, or mark a paid subscription active.

## Operator readiness checks

For an appropriately isolated test machine:

~~~sh
composer install
npm install --ignore-scripts
npm run build
php artisan migrate:fresh --force
php artisan test
node --test workers/gomovie-media-edge/worker.test.mjs
php artisan gomovie:media-preflight --json
~~~

Do NOT execute migrate:fresh against live or shared production data. A failed media-preflight is expected while external streaming integration is disabled.

## Browser installer and package QA

The standalone customer installer now implements five browser steps and a private hosting-owner key. Its explicit QA workflow passes genuine HTTP form submissions, empty-database migration, initial administrator/audit creation, and setup-lock re-entry denial against isolated MySQL 8 and PostgreSQL 16 containers. A separate workflow creates a QA buyer ZIP including Composer vendor/, Vite bundles and generated dependency lock manifests. These **do not** represent an aaPanel/cPanel installation or legal signoff. See [web installer](WEB_INSTALLER.md) and [GitHub package QA artifact](https://github.com/bambodia168-sys/Gomovie/actions/runs/42444468713).

## Audited host acceptance evidence

A CLI-only two-person [aaPanel/cPanel acceptance registry](HOST_ACCEPTANCE.md) records external host-test ticket references, independent super-admin acceptance/rejection, and an administrator dashboard of missing/accepted items. This is a record of human-provided evidence; it **cannot** certify that a host, restore or legal/payment control actually passed, and never enables live features.
