# GoMovie Upload Studio 2.0 — independent security and rights review

**Date:** 2026-11-11  
**Scope:** Source review of opt-in automatic-publication path and separate private resumable intake.  
**Status:** Source-level review with specific findings; **NOT** an external malware/rights audit, physical-device approval, or production activation certificate.

## Architecture observed

- QuickUploadController accepts a rights_confirmed checkbox. For Free uploads it creates an active global content_rights row with an OWNER-DECLARED reference and records rights_basis=uploader_declaration_only in the audit event.
- PrivateVideoIntake writes original bytes to PRIVATE local storage, verifies server-stored size and SHA-256, records media_ingests.status=quarantined, defaults security/rights states to pending, then optionally queues R2 mirroring or AutoPublishUploadedVideo when switches permit.
- AutomaticPublicationService.process requires opt-in flags, an active rights row, a trusted ClamAV binary, original SHA-256 and successful scan. But its transaction also **marks both security_scan_status=clean and rights_review_status=verified, and sets approved_by to the UPLOADER** without independently verified rights evidence.
- AutomaticPublicationService.finalize can publish HLS based on these statuses. The separate server-reviewed MediaPublicationReviewer/PrivateMediaStagingPublisher path has stronger separation of duties, but the automatic path does not depend on it.
- IntakeReviewService.approve requires an administrator and rights reference but does not reject when the approver and uploader are the same identity. The reference remains an operator attestation, not proof of legal rights.
- Default configuration switches for automatic publication, public HLS, transcoding and media edge are OFF in source. Whether a particular installed environment has enabled them requires separate authorized operator verification.

## Findings and required remediation

### BLOCKER S1 — uploader attestation is not independently verified distribution rights

A checkbox and generated OWNER-DECLARED reference cannot substitute for a verified license. The current active-rights row can satisfy GlobalRights even though the audit identifies the reference as a declaration.

**Required:** independent evidence review before rights can qualify for public publishing; record the exact title, rights holder, territories, dates and evidence. Revocation must be effective immediately. Do not enable automatic publication as currently implemented.

### BLOCKER S2 — automatic workflow self-grants approval

AutomaticPublicationService.process assigns approved_by=uploaded_by and rights_review_status=verified based on a successful scan and database attestation. Malware scanning cannot independently determine copyright or distribution rights.

**Required:** separate trusted malware scan evidence from legal rights review, and enforce reviewer independence with attributable evidence before encoding/publication.

### BLOCKER S3 — automatic publication bypasses independent package review

The independent MediaPublicationReviewer/PrivateMediaStagingPublisher has immutable fingerprints and separated duties, but the opt-in automatic code has a direct media_assets ready/publication path.

**Required:** a canonical publication gateway requiring independent review, package integrity, effective licenses and playback eligibility for every public asset.

### MAJOR S4 — scanner success does not certify production safety

ClamAV executable checks and source SHA-256 validation are useful, but release acceptance also needs scanner signature freshness, FFmpeg isolation, bounded resources, operator ownership, fault injection and properly licensed test media.

### POSITIVE S5 — new resumable staging is private-only

The proposed resumable_uploads_enabled switch defaults OFF. Private chunks:
- remain in local private storage, not public assets or R2;
- are limited to authenticated verified administrators with owner-checked UUIDs on every request;
- use sequential 1 MiB offsets, per-chunk SHA-256, a current 128 MiB ceiling, a bounded active-session quota and expiry;
- allow only PRIVATE intake through the existing QuickUploadController/PrivateVideoIntake;
- never self-approve, encode or publish media;
- use a unique session ID on completed ingests to prevent duplicate replay;
- fail closed if finalization is interrupted and the server has not confirmed an ingest.

**Still required:** multi-process race and storage-exhaustion testing, real browser network-loss/resume tests, interrupted-finalization recovery drills and isolated aaPanel/cPanel acceptance before the resumable feature can be enabled in production.

## Release decision

**Do not enable** automatic publication, public HLS, transcoding, DRM, paid playback or public R2 based on this review. **Do not merge or deploy** the new resumable feature until full automated tests, mobile/browser emulation, security regression, hosting and owner acceptance are documented.

This is a source-level review with explicit blockers, not a claim of completed independent legal or malware/provider verification. Remediate S1–S3 before approving any automatic-publication activation.
