# GoMovie Upload Studio 2.0 — Milestone 2: private resumable upload

**State:** Development / draft stacked PR. Not approved for deployment.  
**Base:** Milestone 1 feature branch (Draft PR #8); production and GitHub Main unchanged.  
**Feature switch:** GOMOVIE_RESUMABLE_UPLOADS_ENABLED=false unless explicitly enabled for isolated QA.

## What is implemented

- Existing one-screen Quick Upload optionally uses private resumable upload for new PRIVATE movies. It continues to use the existing direct upload path for Free and Paid requests.
- An authenticated, verified admin starts a UUID session with title, file name, size, description and upload-rights declaration. The session has a 24-hour expiry, 128 MiB current ceiling, 1 MiB chunks and a maximum of three open sessions per administrator.
- POST chunk validates the exact sequential offset, expected length, SHA-256 digest and session ownership before recording private storage. A verified duplicate chunk is idempotent. Out-of-order or conflicting bytes fail closed.
- GET status returns only the owner's confirmed received offset and private state. The browser keeps an optional tab-scoped pointer, requires re-selecting the original file, and resumes at the server's confirmed chunk boundary.
- After all chunks are assembled and verified, the existing QuickUploadController and PrivateVideoIntake perform normal server-side mime validation, catalog draft creation, local quarantine, integrity checks and audit recording. PRIVATE only.
- A unique nullable resumable session ID links final media ingests. Repeated completion returns the existing ingest ID instead of creating a second movie.
- The user may pause a request and resume later. DELETE cancels an unfinished uploading session. A scheduled bounded command removes expired temporary chunks; it does not delete accepted media.

## Security and operational limitations

- **No public publishing, R2 bucket exposure, new video playback or paid feature is enabled.** Resumable chunk upload is unrelated to rights/scan/approval. Existing opt-in automatic-publication code has separate BLOCKING findings recorded in UPLOAD_STUDIO_2_SECURITY_REVIEW.md.
- This version deliberately caps total uploads at 128 MiB, even when chunking. Raising the ceiling requires resource-limits, file-integrity and storage acceptance on real hosting.
- If the server dies after creating a draft but before an ingest is confirmed, the session remains fail-closed for operator reconciliation. The unique session-linked ingest supports recovery when one already exists; no automatic second title is created.
- Expired cleanup requires a working Laravel scheduler. File-system or database failures must be tested on the target host.
- No public read routes exist for temporary chunk paths. Session IDs are authenticated, owner-bound and expire. R2 remains an optional existing separate mirror operation.
- This design requires multi-process concurrency, quota/exhaustion, revoked-auth, CSRF, MIME, malicious file, lost-response and recovery acceptance before any enabling of the switch in production.

## Validation commands (development/CI only)

- php artisan test --filter=ResumableQuickUploadTest
- php artisan test --filter=QuickUploadTest
- node --test tests/js/quick-upload-m1.test.mjs
- Full CI, MySQL/PostgreSQL migrations and tests, installer package acceptance.
- Playwright browser acceptance on an isolated localhost Laravel instance with the feature flag enabled, using generated/synthetic video.
- Independent real aaPanel/cPanel and physical iOS/Android/tablet acceptance are still mandatory.

**No production deployment, flag change or migration is authorized by this development branch.**
