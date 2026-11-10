# GoMovie — approved next-development roadmap

**Approved by product owner:** 2026-11-11  
**Repository:** [bambodia168-sys/Gomovie](https://github.com/bambodia168-sys/Gomovie)  
**Planning status:** Recorded and prioritized; work on the seven new phases **has not started solely by recording this roadmap**.  
**Next milestone:** **1. Upload Studio 2.0**.

> This document preserves the owner-approved order. For each phase, extend existing Laravel functionality instead of duplicating modules. The approved clean premium dashboard and shared Account/Studio interface are the visual design reference. User workflows must remain easy to use, especially for future CodeCanyon and Codester customers.

## Roadmap overview

| Priority | Phase | Scope | Tracking | Status |
| --- | --- | --- | --- | --- |
| **1 — Top** | **Upload Studio 2.0** | Easy drag-and-drop upload; visible progress, retry/resume; optional Cloudflare R2; gated processing after approval | [#1](https://github.com/bambodia168-sys/Gomovie/issues/1) | Planned |
| **2** | **Premium Watch Experience** | Continue Watching; Next Episode; saved playback progress; subtitles; portrait/widescreen | [#2](https://github.com/bambodia168-sys/Gomovie/issues/2) | Planned |
| **3** | **Advanced Movie & Series Manager** | Bulk editing; duplicate detection; improved TMDB imports; seasons/episodes; trailers; translated metadata | [#3](https://github.com/bambodia168-sys/Gomovie/issues/3) | Planned |
| **4** | **Media Analytics & Operations** | Real viewing statistics; processing states; failed encodes; storage; pending approval; actionable alerts | [#4](https://github.com/bambodia168-sys/Gomovie/issues/4) | Planned |
| **5** | **User Management & Security** | Role/permission matrix; account activity; audit trail; stronger administrator security | [#5](https://github.com/bambodia168-sys/Gomovie/issues/5) | Planned |
| **6** | **Complete Localization** | Fully translated auth, accounts, dashboards, viewing, notifications and configurable catalog text | [#6](https://github.com/bambodia168-sys/Gomovie/issues/6) | Planned |
| **7** | **CodeCanyon & Codester Release Readiness** | Five-step installer; backup/restore; upgrades; rollback; aaPanel/cPanel; docs and first-run wizard | [#7](https://github.com/bambodia168-sys/Gomovie/issues/7) | Planned |

### Phase 1 — Upload Studio 2.0 [#1](https://github.com/bambodia168-sys/Gomovie/issues/1)

**Owner's intent:** "Simplify video uploads with drag-and-drop, progress and retry controls, optional Cloudflare R2 storage, and automatic processing after approval. Keep the experience as easy as YouTube."

- Improve existing **Upload Studio / QuickUpload**, not a second uploader.
- An administrator chooses video and title or episode with minimal required inputs.
- Show actual upload progress, validation, successful completion, failures, and safe retry/resume support where feasible.
- Keep local private storage usable; Cloudflare R2 is **optional** and securely configured.
- Preserve private quarantine, independent security evidence, verified distribution rights, authorized approval and isolated encoding queue.
- Do not equate upload completion with publish/playback approval. Never expose private objects directly.

**Ready when:** real upload, failure/retry, permissions, security review and device/hosting acceptance are recorded against #1. No arbitrary production encoding or media publication.

### Phase 2 — Premium Watch Experience [#2](https://github.com/bambodia168-sys/Gomovie/issues/2)

**Owner's intent:** "Improve the existing player with Continue Watching, Next Episode, automatic progress saving, subtitle selection, and better portrait/widescreen controls."

- Add accurate, privacy-conscious progress persistence and resume per viewing profile.
- Make Next Episode follow existing episode order and the current title's rights and visibility.
- Add accessible subtitle/caption and player controls for 9:16 and 16:9 watch modes.
- Preserve the guest sign-in-and-return flow; continue enforcing server-side playback eligibility.
- Test keyboard, Safari, iOS, Android, tablet, interrupted network and authorized test media.

**Ready when:** approved and playable media can resume safely, and unauthorized media remains unavailable.

### Phase 3 — Advanced Movie & Series Manager [#3](https://github.com/bambodia168-sys/Gomovie/issues/3)

**Owner's intent:** "Expand existing catalog tools with bulk editing, duplicate detection, improved TMDB imports, season and episode organization, trailer management, and multi-language metadata."

- Extend **Catalog**, **Episode Studio** and **TMDB import** controllers.
- Safe bulk editing with change preview and confirmation; review duplicate candidates instead of silently merging them.
- Preserve hand-edited metadata when importing; respect provider quota, attribution and retries.
- Organized seasons, episodes, trailers and approved artwork.
- Locale-specific titles and descriptions with consistent fallback.

**Ready when:** permissions and data integrity remain intact under imports, duplicates and bulk actions.

### Phase 4 — Media Analytics & Operations [#4](https://github.com/bambodia168-sys/Gomovie/issues/4)

**Owner's intent:** "Extend dashboard statistics with actual viewing activity, upload processing status, failed encodes, storage consumption, pending approvals, and actionable system alerts."

- Continue using the approved visual card and premium-table system.
- Ground metrics in actual records; show truthful empty states, not demo numbers.
- Read-only diagnostics for private media queue, storage, failed jobs, approval backlog and relevant activity.
- Administrator-scoped visibility and safe troubleshooting without leaking secrets, credentials or sensitive media information.

**Ready when:** metrics are reproducible, actionable and performant on supported hosting.

### Phase 5 — User Management & Security [#5](https://github.com/bambodia168-sys/Gomovie/issues/5)

**Owner's intent:** "Add an administrator-managed permission matrix, account activity history, stronger security controls, and audit logs for publishing, media approval, and administrative actions."

- Extend **User Manager** and existing authorization checks to a testable permission matrix.
- Log actor, action, target and time for privileged activities while protecting private data.
- Prevent role escalation, cross-account exposure, bypassing media review, and unauthorized actions.
- Implement permissions server-side, not merely by hiding buttons.

**Ready when:** negative permission tests pass across each role and sensitive operation.

### Phase 6 — Complete Localization [#6](https://github.com/bambodia168-sys/Gomovie/issues/6)

**Owner's intent:** "Expand the existing 11-language selector into full translations for authentication, accounts, dashboards, watch pages, notifications, and configurable catalog content."

**Supported set:** Chinese, English, French, Hindi, Indonesian, Japanese, Khmer, Korean, Malay, Thai, Vietnamese.

- Complete consistent translations of visible functions, forms, validation, alerts and settings.
- Support configurable localized catalog metadata, fallback and language-specific quality review.
- Keep locale consistent after redirects and avoid breaking permissions or changing user data.

**Ready when:** main user journeys work end-to-end for all 11 languages, with native-language review and accessibility checks.

### Phase 7 — CodeCanyon & Codester Release Readiness [#7](https://github.com/bambodia168-sys/Gomovie/issues/7)

**Owner's intent:** "Strengthen the five-step installer, backup/restore, upgrades, rollback, cPanel/aaPanel compatibility, documentation, and first-run configuration wizard."

- Harden the **existing five-step installer**, no duplicate installation process.
- Defaults must be sensible; clearly explain truly optional infrastructure.
- Test complete offline backups, restoration, clean installations, upgrades and rollbacks with isolated customer-like data.
- Independently test real aaPanel and cPanel, physical phones/tablets and security/accessibility.
- Document requirements, administration, troubleshooting, licensing, policies and release steps.
- **Commercial release remains gated:** real provider credentials/onboarding, paid billing, DRM, media edge access, legal rights, malware scanning and production encoding are **not** certified by CI alone.

**Ready when:** the customer package passes independent install, operation, recoverability, compliance and hosting evidence checks.

## Universal engineering and UI acceptance rules

1. **Do not duplicate existing features**. Inspect source, existing tests and live behavior before coding.
2. **Keep UI clean, premium, consistent and responsive**. Reuse the approved Studio Dashboard layout, SVG icon treatment, top-right feedback, compact header, grouped collapsible sidebar, accessible modals, actionable three-dot menus and 10-item pagination where relevant.
3. **Make core tasks easy**. Optional integrations and advanced safety/operations controls must not overcomplicate ordinary uploading and browsing.
4. **Role-aware and secure by default**. UI visibility and route/service permissions must match; no cross-user records or insecure uploads.
5. **No demo data or fake functions**. Use real records and truthful empty states.
6. **Test before deploying**. PHP/JS CI, Laravel/MySQL/PostgreSQL acceptance, installer acceptance and isolated browser/device checks as applicable. Do not run unreviewed migrations on live customer data.
7. **Preserve production**. Exact-source verification, read-only preflight, backups, custom-file conflict guard and rollback for releases.
8. **Respect media rights**. No automated, public, or paid playback of unlicensed content; independent malware/rights checks cannot be replaced by a checkbox or a successful unit test.
9. **Keep release gates honest**. Distinguish repository implementation, deployment and real hosting/device/security acceptance; never claim an unfinished gate is complete.
10. **Track progress in GitHub issues**. Update each issue with PRs, CI outcomes, known limitations, acceptance results and final owner signoff. Mark stages complete only with evidence.

## Architecture and release references

- [Private media pipeline](MEDIA_PIPELINE.md) — existing rights/scan/encoding and safe publication boundaries.
- [Release acceptance](RELEASE_ACCEPTANCE.md) — production and commercial acceptance gates.
- [Operations](OPERATIONS.md) — queue isolation, monitoring and recovery requirements.
- [Frontend design](FRONTEND_DESIGN.md) — approved frontend conventions.
- [Installer](WEB_INSTALLER.md) and [hosting](HOST_ACCEPTANCE.md) — environment-specific acceptance.

## Next action

**Start with [#1 Upload Studio 2.0](https://github.com/bambodia168-sys/Gomovie/issues/1).** Audit existing Upload Studio, QuickUpload, Media Library and private intake flow, then implement one small, independently tested milestone at a time. The other six phases remain recorded in the order above; this document is the source of truth for future GoMovie development planning.
