# Upload Studio 2.0 — Milestone 1: reliable single-request upload

**Tracking:** [GoMovie Issue #1](https://github.com/bambodia168-sys/Gomovie/issues/1) · [Approved roadmap](PRODUCT_ROADMAP_2026.md)

**State:** Development branch / draft PR. Not merged or installed on aaPanel.

## Why this milestone

The existing **/upload-studio** Quick Upload already implements drag-and-drop, an upload XHR progress bar, authenticated role-scoped input validation and a 128 MiB server ceiling. Rebuilding those features or adding a second uploader would complicate the product.

Milestone 1 strengthens the simple first-time upload experience without changing the private-intake service, storage configuration, underlying database schema, rights checks, approval state or public delivery implementation.

## Changes implemented

- A clearly visible **Cancel transfer** control while upload bytes are being sent.
- A **Retry upload** action only for identifiable 400/409/413/415/422 client/server rejections, after the operator fixes the issue. It is never triggered automatically.
- On timeout, lost network, explicit cancel, unrecognized response, or expired session, the app **does not automatically resend**. It shows **Check Media Library first**, because the server may already have committed the ingest, and blind retries could produce duplicate catalog titles.
- Percent progress explicitly distinguishes **bytes transferred** from **private ingest confirmed by the server**, so reaching 100% never falsely means published or playable.
- Input controls are disabled during the active request and restored on failure; multipart data is captured before disabling them.
- File-size/extension validation and accessible status/error messages are displayed before upload.
- The summary distinguishes the private quarantine step from optional security, review, conversion and publication processing.
- Exact existing route **/upload-studio/quick** and its CSRF header remain. No new endpoint, release toggle, media provider, or fake operation is added.

## Boundaries

**This is a single-request first milestone, not resumable chunk upload.** The effective maximum is still 128 MiB or the smaller PHP/web-server limit. A canceled request may still reach the server; do not promise server-side cancellation or safe automatic retry. True chunk resumption and durable idempotency keys are future development with a separate database design and security review.

**Cloudflare R2:** Already supported as opt-in private mirroring by the existing intake. This milestone does not change R2 settings or introduce public buckets. Local quarantine must work without R2.

**Media security:** All uploads continue through the existing PrivateVideoIntake. The original remains in private local storage, with pending security and rights review; the UI never changes approval fields, creates public playback access, or grants licensed-media entitlements.

**Existing opt-in free-public workflow:** Its self-declaration and automatic publication design predate this milestone. This change does not alter, certify or switch on that workflow. The independently verified rights and scanning/approval requirements documented in [MEDIA_PIPELINE.md](MEDIA_PIPELINE.md) and [RELEASE_ACCEPTANCE.md](RELEASE_ACCEPTANCE.md) remain separate release gates.

## Isolated acceptance

- Node test suite: 'node --test tests/js/quick-upload-m1.test.mjs' checks the actual inline browser script against a simulated DOM and XMLHttpRequest for syntax, file validation, progress-before-receipt, cancel, deliberate retry, ambiguous network results, and confirmed private intake.
- Laravel feature 'tests/Feature/QuickUploadTest.php' checks rendered controls, authenticated private upload, local quarantine, pending review flags, no public media asset, no automatic publishing for private content, and uploader-only status.
- Existing full PHP CI, MySQL/PostgreSQL suites and installer acceptance remain mandatory.
- Manual browser checks after code review: desktop drag/drop, click-select, keyboard focus, file-too-large, cancel, 422 retry, lost network, 201 receipt, signed-out and non-admin access. Test only in a disposable environment.

## Next planned sub-milestones

1. **Milestone 2:** server-side durable upload sessions with safe idempotency and resumable chunk support for larger files, including quotas, expiry and orphan cleanup.
2. **Milestone 3:** optional private R2 destination configuration with validated credentials and nonpublic acceptance.
3. **Milestone 4:** visible approval/processing lifecycle tied to independently verified scanning, rights, and supervised encoding/stream publication gates.

No production migration, merge, approval or deployment is implied by this document.
