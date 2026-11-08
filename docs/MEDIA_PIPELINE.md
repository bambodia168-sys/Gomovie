# Gomovie private media pipeline

## State of implementation

A gated FFmpeg/FFprobe HLS encoding pipeline, a private queue job, a server-only queue command, and an operator preflight check are implemented in source code. Encoding remains **disabled by default**, with no production deployment, automatic malware scanner, legal review provider or secure R2 publication workflow. A synthetic video smoke test runs only on test workers with installed FFmpeg binaries.

## Upload-to-delivery boundaries

1. Administrator uploads a permitted video into nonpublic storage. It starts in the quarantined state.
2. Independent malware-scanning evidence and verified distribution-rights approval are required. The CLI review step records external evidence references, but does not itself perform or verify malware scanning or obtain legal permission. No HTTP route may grant encoding or staging approval.
3. Only an ingest with status approved, security_scan_status clean, rights_review_status verified, approved_at, approved_by, and a valid active global content_rights row can pass the TranscodeGate. Direct database manipulation is NOT a substitute for independent approvals.
4. The server operator must explicitly enable GOMOVIE_TRANSCODING_ENABLED, configure trusted absolute paths for FFmpeg and FFprobe, and validate the isolated queue worker.
5. Run php artisan gomovie:media-preflight --json for nonmutating readiness diagnostics. The server-only command php artisan gomovie:queue-encoding ID requires confirmation before dispatch.
6. TranscodeApprovedIngest queues on gomovie-media. TranscodingService revalidates private paths, original SHA-256, source dimensions, duration, approval and rights. It stages H.264/AAC renditions in a local private directory, targeting 1080p/720p/480p no higher than source dimensions.
7. Media encoding uses a dedicated database_media or redis_media connection with retry_after 7800s (greater than 7500s worker timeout); a supervised isolated media worker is required.
8. The encoded master playlist is saved as encoded_manifest_key in media_ingests with status encoded. This is NOT a ready streaming asset and no public URL or Cloudflare object is created.
9. The source now includes a **server-only, two-person reviewed private-staging publisher**. MediaPackageInspector validates strict local HLS file references, original SHA-256, private paths and active global rights. gomovie:review-media records immutable review evidence, and gomovie:stage-media can copy reviewed packages to the private S3/R2 disk when GOMOVIE_MEDIA_STAGING_ENABLED=true. The commands require separate verified administrators. Staging remains **off by default** and never creates a media_assets row or enables playback. Independent edge/DRM and private-bucket acceptance are still required before any future activation capability.

## Release blockers

- Independent, audited malware scanning, operator approval and legal rights attestation
- Production transcoding QA with authorized video clips; codec, subtitles, audio, bitrates and segment inspection
- Dedicated queue worker with timeout/retry_after coordination, system resource isolation, concurrency limits and incident recovery
- Private R2 publishing and origin lock-down; signed edge Worker acceptance under load
- DRM provider licensing, territorial restrictions, entitlement revocation and watermarking where required
- Monitoring, security tests, rollback and backup/restore acceptance
- Verified payment-provider onboarding, webhooks, tax and refunds; billing disabled separately

No production media, credentials or commercial payment handling are included in this pipeline milestone.

## Operator-only, non-playable private staging

After external scan, rights verification and private encoding have genuinely completed, the operator reviews a master playlist checksum and uses:

~~~sh
php artisan gomovie:review-media INGEST_ID \
  --reviewer=independent-admin@example.invalid \
  --rights-ref=EXACT_ACTIVE_RIGHTS_REFERENCE \
  --scan-ref=EXTERNAL_SCAN_REPORT_REFERENCE \
  --quality-ref=EXTERNAL_QC_REPORT_REFERENCE \
  --manifest-sha=SHA256_OF_MASTER_M3U8

# Only in an isolated, approved environment with a separately verified private R2 bucket:
# GOMOVIE_MEDIA_STAGING_ENABLED=true
php artisan gomovie:stage-media INGEST_ID \
  --publisher=second-admin@example.invalid
~~~

Both commands demand interactive confirmation. The reviewer must not be the uploader or ingest approver, and the publisher must not be the reviewer. They refuse expired or regional rights, malformed playlists, external references, missing segments and changed original media; audit events capture approvals and stage result. Copying into R2 is **not CDN publication**: files are under gomovie/unreleased/, explicitly private, and remain unplayable. The real authorizer and DRM integration must be independently tested before activating any media asset.

These references are operator-supplied attestations; source code **does not establish their truth**. A mandatory external scanner, verified rights provider and QA proof must still be integrated/accepted. Failed staging is recorded; safe operator-led remote cleanup and retry recovery must be verified before production use.
