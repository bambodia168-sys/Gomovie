# Issue #10 — Phase 2: Rights Evidence & Reviewed Publication

**State:** Development-only Draft PR #12, stacked on Draft PRs #11, #9 and #8. GitHub Main and production must remain unchanged until signoff.

## Purpose

A creator's rights claim is not legal proof. The protected Rights & Publication workspace at /dashboard/rights-evidence records externally reviewed license documents without automatically certifying a legal contract's authenticity. A separate administrator must attest to verification.

The public HLS lookup and final activation paths require current approved evidence with matching worldwide active rights, distinct submitter/reviewer, an active verified reviewer, valid license dates, and separately reviewed media package. Revocation and expiry are checked on every access.

## Added functionality

- Private evidence intake: PDF/JPEG/PNG up to 8 MiB, server MIME validation, SHA-256, configured real clamscan call, local private storage, no public URL and failure cleanup.
- Protected attachment download with no-store and nosniff headers, verified admin only.
- Independent legal verification by another administrator with external reference, exact document digest, holder, scope and validity dates.
- Rights issuance only after verification, with conflict checks against existing license records.
- Expiry and irreversible revocation, revoking matching package reviews and blocking playback immediately.
- Encoded HLS package inspector showing original, manifest and full-package digests.
- Separate publication administrator distinct from uploader, approver and package reviewer.
- Existing admin layout and sidebar, ten-row pagination, grouped actions and centered dialogs. Public layouts unchanged.

## What this does not certify

A successful malware scan is not a legal rights determination. Neither a reviewer checkbox nor an external reference automatically proves a contract is authentic. The real issuer, agreement, geographic rights and dates require independent human or legal review. GitHub CI scanner stubs test control flow but NOT deployed ClamAV signatures. The old unattended automatic-publication worker remains retired.

Historical HLS assets without an independent package review and publisher identity remain unavailable until reconciled. Do not automatically approve old records.

## Read-only historical reconciliation

On a separately authorized QA checkout with independent database credentials, run:

    php support/gomovie-rights-reconciliation-readonly.php

It produces aggregate counts of ready assets without review/publisher links, active uploader declarations, rights lacking matching evidence, expired evidence and revoked reviews. It never mutates data and prints no customer records.

## Real aaPanel and cPanel isolated acceptance

1. Create a separate authorized QA checkout, isolated MySQL/PostgreSQL database with restricted credentials, isolated media storage and no live customer media.
2. Pin the exact GitHub commit and CI artifacts. Keep credentials and license documents out of public logs.
3. Set APP_ENV=staging and disable automatic publication, public HLS and resumable uploading for initial installation checks.
4. Run READ-ONLY mode: bash support/gomovie-rights-qa-preflight.sh --check /absolute/path/to/isolated-qa
5. After explicit operator authorization, run synthetic real ClamAV detection: bash support/gomovie-rights-qa-preflight.sh --scan-eicar /absolute/path/to/isolated-qa. This writes and deletes only a harmless industry-standard antivirus test file under /tmp; it never tests customer media.
6. Rehearse the five-step installer, database backup and restore, rollback, app upgrade, lost network, rights expiry, role revocation, download access and scanner failure.
7. Keep public publication disabled until independent rights verification, all role-separated manual reviews and actual signed-off host/device acceptance.

The test above establishes only that the chosen ClamAV binary and signatures detect this synthetic sample at that moment, not broad malware protection.

## Real browser and physical-device matrix

| Environment | Acceptance |
|---|---|
| Desktop Chrome and Edge | Auth, protected license download, dialogs, sidebar, review and revoke |
| Firefox desktop | Same flows, keyboard access and redirects |
| Safari on macOS | Dialog, file picker, private download, table actions |
| iPhone Safari | Input focus/zoom, centered modals, no horizontal overflow, file picker |
| Android Chrome | Upload forms, lost network, verified resume and private attachments |
| iPad/tablet | Portrait and landscape layout, vertical action menus and media inspector |

Record the actual device, OS/browser version, evidence, date, results and tester in private QA. Chromium mobile emulation is not physical iPhone, Android, tablet, or Safari acceptance.

## Release decision

Do not merge or deploy Draft PR #12 until CI, real aaPanel/cPanel, actual scanner acceptance, independent legal verification, historical reconciliation, physical device testing and owner release approval all pass. No production migrations or configuration changes are authorized.
