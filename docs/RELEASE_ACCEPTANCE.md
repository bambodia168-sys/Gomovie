# Gomovie — production-release acceptance gates

All gates require reproducible operator evidence and explicit signoff. Passing CI does not mark this project ready for commercial release.

| Workstream | Repository implementation | Production acceptance |
| --- | --- | --- |
| Authentication | Registration, verified email, password recovery/settings, roles, profiles | SMTP provider delivery, cookies, abuse prevention, security test, privacy/data deletion, real-device acceptance |
| Content management | Admin metadata CRUD, seasons/episodes, artwork and draft TMDB import | Rights-review evidence, audit trail, provider quota/retry/attribution, moderation, localization |
| Secure uploads | Local quarantine, SHA-256 verification and operator-only intake | Malware scanning, rights attestation, private object storage, file size/codec/resource limits |
| Transcoding | FFmpeg HLS multi-rendition job plus synthetic fixture | Isolated workers, dedicated queue timeouts, authorized real-video test, captions, audio QC, adaptive ABR QA |
| Private media edge | Signed HLS grant + prototype Worker + Laravel internal authorization | Private R2 deployment, encrypted key/segment protection, load/concurrency, replay/revocation tests, WAF, region restrictions |
| DRM | None; DRM assets fail closed | Vendor contracts and Widevine/FairPlay/PlayReady licensing where required |
| Frontend | Dynamic carousel/cards, discover/detail, My List/history, gated HLS player | Responsive visual audit, keyboard/accessibility, physical mobile/tablet tests, native HLS retry/renewal |
| Subscriptions | Plan/rental schema, active-subscription read check, disabled checkout | Provider onboarding/eligibility, verified webhooks and idempotency, taxes, invoices, refunds, chargebacks, subscriptions |
| Hosting | Laravel source and CI DB suites | Repeatable independent installation and upgrade/rollback/restore on aaPanel/cPanel, TLS and mail/queue tuning |
| Security & legal | Basic access control, rights gate, read-only preflight, separate secrets | External pentest, license/policy approval, retention/deletion controls, observability, incident handling |

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
