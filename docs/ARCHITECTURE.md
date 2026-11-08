# Gomovie — architecture and source-code implementation status

Status date: 2026-11-08. This file describes the repository implementation, NOT a production deployment or legal certification.

## 1. Application architecture

- Laravel 13 / PHP 8.4+, Sanctum and server-rendered Blade UI.
- Tailwind CSS 4, Alpine.js, hls.js 1.7 for adaptive HLS playback.
- MySQL 8 or PostgreSQL 16 application database. CI also tests SQLite.
- Redis distributed locks for simultaneous stream-slot control when enabled.
- Private local video ingest and gated FFmpeg/FFprobe encoding; private R2 edge Worker is a separate uninstalled component.
- Dark-first Gomovie design system: crimson #E50914, gold #F5C518, obsidian #0A0A0C.

Vue/Inertia, MPEG-DASH, and commercial DRM integrations are not installed.

## 2. Account security (implemented in source)

Viewer registration, login/logout, verification, reset email, password rotation, account settings and up to five profiles. Roles: viewer, editor, admin, super_admin. Admin role elevation requires a server-side interactive command. Authenticated endpoints require active and verified accounts. The staff UI and user management operate only under guarded Laravel middleware. API tokens are revoked upon password change.

Production requirements remain: validated SMTP delivery, proper session cookies and secure HTTPS, abuse/security testing, real-device test, account-data deletion and privacy policy acceptance.

## 3. Catalog and editorial system (implemented in source)

Movies, TV series, seasons, episodes, genres, actors, ratings, media assets, rights-window schema and metadata-based publishing. Responsive homepage hero is driven by published entries; draft titles are not publicly discoverable. Administrator can CRUD metadata and nested episodes, stage artwork, and manually import TMDB records AS DRAFTS. Editor and administrator boundaries are enforced.

Still missing: a signed/audited legal rights-review UI, scheduled metadata synchronization and its retry/error reporting, full translations and content localization, content moderation and full role-permission configuration beyond fixed roles.

## 4. Private media and playback

New video files are quarantined in nonpublic storage; no upload is served directly to viewers.

The private encoding service is implemented behind a disabled feature switch and requires clean malware-scan and approved rights states, operator approval, and active global content rights. It can encode up to 1080p, 720p and 480p H.264/AAC HLS variants, capped at source resolution, into isolated private local storage. Encoding does not set media_assets.status=ready and does not upload assets to R2. See docs/MEDIA_PIPELINE.md.

When and only when the edge is independently deployed and enabled, authenticated playback grants use a signed short-lived manifest URL and Redis atomic reservation of a stream slot. Stream heartbeats refresh the database lease. The Worker prototype validates signature/expiration/asset scope before every manifest/segment/key read and asks Laravel to verify account, entitlement, active license, and session. Unknown, suspended, unauthorized or expired access fails closed. Edge and transcoding remain switched off in source defaults.

Security review still required: trusted geo-restrictions (currently only global rights), key rotation, private R2 object publishing, entitlements under multi-server concurrency, token replay attacks, HTTPS/CORS policy, CDN cache keys, video entitlement revocation, signed encryption-key paths, worker WAF/rate limits, and licensed Widevine/FairPlay/PlayReady if contractually required. A signed stream URL alone is NOT DRM.

## 5. Account-specific library

Profile-scoped saved titles and watch history are exposed through authenticated API routes and viewer-facing account pages. Published movie and series pages provide My List actions; watch-history pages show owned-profile progress. API login tokens are not automatically issued to new accounts; session authentication is first-party and requires testing of cookie/CSRF behavior under the chosen production domain.

## 6. Billing and legal

Subscription and rental record schemas exist, and premium playback checks for an active subscription. However no provider is integrated: checkout endpoint returns HTTP 503, and GOMOVIE_BILLING_ENABLED defaults to false. No checkout page or route may activate access using unverified client input. Payment handling, refunds, taxes, receipts, webhooks, payouts and pay-per-view are not implemented.

Obtain rights to every film, show, poster, subtitle and audio track. TMDB metadata access does not imply film distribution rights.

## 7. API and applications

Public v1 catalog: /api/v1/movies, /api/v1/series, title details and series season discovery, with filters.

Authenticated v1: profiles, watchlist, watch history sync, ratings, subscription status, stream grant, heartbeat and stop. Billing subscription creation is disabled.

Private v1: /api/v1/internal/media/authorize — protected by distinct edge shared key and fails closed if the edge feature flag is off. It is NOT a public streaming URL.

Web: home, browse, title details, login, register, email verification, password recovery, account, settings, My List, history, and /dashboard administration workspace.

## 8. QA / release status

GitHub Actions verifies PHP syntax, dependencies, Vite build, Laravel PHPUnit, pure Worker signature/playlist checks, and synthetic FFmpeg private HLS encoding. Separate workflows verify fresh schema migrations and PHP feature tests on MySQL 8 and PostgreSQL 16 with disposable services.

Source-code CI cannot substitute for an independently installed aaPanel/cPanel server, live Redis contention, functional SMTP delivery, real encrypted media/DRM, real external TMDB connectivity, actual iPhone/Android/tablet behavior, or payment sandbox/provider acceptance.

Live production remains unchanged; source code alone is not a deployment, a film license, or a payment-provider certification. Refer to docs/RELEASE_ACCEPTANCE.md.
