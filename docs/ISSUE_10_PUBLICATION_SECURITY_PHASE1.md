# GoMovie Issue #10 — phase 1 security containment

**Date:** 2026-11-11  
**Branch:** security/issue-10-independent-publication-gates  
**Base:** feature/upload-studio-2-m2-resumable (Draft PR #9).  
**Status:** development-only, NOT a production release, NOT independent legal approval.

## Objective

Close the immediate *authorization bypass* that allows an uploader's own declaration and an automatic malware-scan result to grant public distribution and playback. No new upload, review, or publication permission is introduced.

### Changes

1. **New Free submissions cannot grant rights.** QuickUpload writes a self-declared OWNER-DECLARED reference as `content_rights.status=pending` and keeps the new movie as a draft and original media in private quarantine. Even with all automatic-publication flags enabled, Quick Upload does not enqueue the public worker or promise public playback. The screen defaults to Private and labels Free as Request review.
2. **Legacy declarations are refused at playback.** The central GlobalRights service rejects OWNER-DECLARED references, including incorrectly active records from earlier versions. A real active, non-declared global distribution-rights record is still needed.
3. **The automatic worker cannot self-approve.** AutomaticPublicationService.process is fail-closed. It records `independent_rights_and_publication_review_required` for quarantined ingests instead of treating ClamAV as a rights decision, marking its uploader as approver, encoding, or publishing.
4. **Ingest approval does not mint a license.** IntakeReviewService forbids same-person uploading/approving and declaration references. Operators must already have a separate active rights record. Successful malware scanning remains necessary but does not establish rights.
5. **Independent package review is enforced for public HLS.** Ready media_assets must link to a `media_publication_reviews` record with a matching ingest and an independently identified reviewer, and documented active rights. Legacy unlinked assets are not playable until reconciled. Publication finalization additionally checks current package fingerprints and rights reference. A changed manifest/segment invalidates a review; only a separately reviewed package can be activated.
6. **Private media staging remains private.** The staging service accepts reviewed draft packages and requires staging staff to differ from uploader, ingest approver and independent package reviewer.
7. **Separate publication administrator is required.** Activation takes a verified administrator distinct from uploader, approver and package reviewer; the media asset and audit event record this publisher. Public readiness also checks that a recorded publisher remains an active verified administrator. No new public activation route is exposed.

## What remains blocked

The application cannot independently determine the legal validity of a license, and Phase 1 does **not** introduce an operator-facing rights-evidence registry or a fully independently signed canonical publish activation process. External contract verification, scanning-worker hardening, an operator-facing publisher approval workflow and independently verified rights-evidence storage remain separate milestones under Issue #10. The internal publisher-role check alone does not certify the legality of uploaded media.

Turning on old GOMOVIE_AUTOMATIC_PUBLICATION_ENABLED / GOMOVIE_PUBLIC_HLS_ENABLED flags **must not** bypass this containment, nor does turning them on make a Free submission public.

### Required acceptance before merging

- Laravel unit/feature CI, MySQL 8 and PostgreSQL 16 acceptance, five-step installer tests, Upload Studio 2 browser CI.
- Negative tests for uploader self-attestation, role segregation, missing reviewed package, stale fingerprints, revocation, legacy active declarations, and missing rights.
- Independent source and legal review of evidence requirements; real aaPanel/cPanel isolated installation and physical mobile/Safari tests.
- Migration impact review for historical ready media, which intentionally lack the new review linkage and will be denied playback until independently reconciled.

**Production**, existing movies, uploads and account frontend layouts remain unchanged while this PR stays draft.
