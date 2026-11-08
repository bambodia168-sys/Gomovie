# Gomovie private media pipeline

## State of implementation

A gated FFmpeg/FFprobe HLS encoding pipeline, a private queue job, a server-only queue command, and an operator preflight check are implemented in source code. Encoding remains **disabled by default**, with no production deployment, automatic malware scanner, legal review provider or secure R2 publication workflow. A synthetic video smoke test runs only on test workers with installed FFmpeg binaries.

## Upload-to-delivery boundaries

1. Administrator uploads a permitted video into nonpublic storage. It starts in the quarantined state.
2. An independent malware-scanning workflow and verified distribution-rights approval are required. Gomovie does NOT provide those approval workflows yet. No viewer or web administrator can grant approval through an HTTP endpoint.
3. Only an ingest with status approved, security_scan_status clean, rights_review_status verified, approved_at, approved_by, and a valid active global content_rights row can pass the TranscodeGate. Direct database manipulation is NOT a substitute for independent approvals.
4. The server operator must explicitly enable GOMOVIE_TRANSCODING_ENABLED, configure trusted absolute paths for FFmpeg and FFprobe, and validate the isolated queue worker.
5. Run php artisan gomovie:media-preflight --json for nonmutating readiness diagnostics. The server-only command php artisan gomovie:queue-encoding ID requires confirmation before dispatch.
6. TranscodeApprovedIngest queues on gomovie-media. TranscodingService revalidates private paths, original SHA-256, source dimensions, duration, approval and rights. It stages H.264/AAC renditions in a local private directory, targeting 1080p/720p/480p no higher than source dimensions.
7. Media encoding uses a dedicated database_media or redis_media connection with retry_after 7800s (greater than 7500s worker timeout); a supervised isolated media worker is required.
8. The encoded master playlist is saved as encoded_manifest_key in media_ingests with status encoded. This is NOT a ready streaming asset and no public URL or Cloudflare object is created.
9. A separate secured review and publisher must verify the output, encrypt or apply licensed DRM if required, upload the reviewed assets to a private R2 bucket, and activate media_assets only after rights, edge authorization and segment/key protections pass.

## Release blockers

- Independent, audited malware scanning, operator approval and legal rights attestation
- Production transcoding QA with authorized video clips; codec, subtitles, audio, bitrates and segment inspection
- Dedicated queue worker with timeout/retry_after coordination, system resource isolation, concurrency limits and incident recovery
- Private R2 publishing and origin lock-down; signed edge Worker acceptance under load
- DRM provider licensing, territorial restrictions, entitlement revocation and watermarking where required
- Monitoring, security tests, rollback and backup/restore acceptance
- Verified payment-provider onboarding, webhooks, tax and refunds; billing disabled separately

No production media, credentials or commercial payment handling are included in this pipeline milestone.
