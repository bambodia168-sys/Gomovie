# Gomovie architecture and acceptance contract

## Status — initial source baseline (2026-11-08)

This repository contains a Laravel 13 project scaffold, relational catalog and account migrations, public catalog API, protected account APIs, and an authorization service that can issue signed private-edge grants **when** licensed media and the edge are configured. It is not a complete production streaming service. No production environment was accessed or deployed in this milestone.

## Stack and design system

- Laravel 13, PHP 8.4+, Composer; MySQL 8 or PostgreSQL 16 (SQLite only for CI)
- Tailwind 4, Alpine.js, dark-first CSS tokens: crimson #E50914, gold #F5C518, obsidian #0A0A0C
- Redis-backed distributed locks are mandatory for stream-slot allocation
- Video engine planned: HLS with adaptive bitrate, signed CDN edge, optional DASH/DRM and authorized subtitles
- Inertia/Vue is a future option, not installed in this initial Alpine starter

## Data and source boundaries

Movies and episodes **do not** contain a public hls_stream_url column. media_assets.manifest_key is an origin-private object key and must never be serialized to public API responses. The upstream original video, master playlist, renditions, encryption keys, and subtitles belong in non-public origin storage, not public/.

Content may be streamed only if:
1. The movie or episode and its parent series are published.
2. A valid ACTIVE content_rights record exists. This baseline currently permits only rights with territories=NULL (global). Territory-restricted content deliberately fails closed until a trusted geo-authorization layer is shipped.
3. The media asset is ready and its stream path is valid. DRM-labelled assets fail closed until DRM integrations are implemented.
4. Premium content has an active provider-verified subscription. Billing is NOT implemented here, and external clients cannot create active subscriptions.
5. A requester owns the requested profile. Kids-profile playback is disabled pending age rating and parental controls.
6. A Redis-distributed lock and database transaction reserve the stream slot without exceeding tier limits.

## Media-edge signature contract (prototype, not deployed)

The PlaybackGrantService signs this canonical LF-separated string:
    gomovie-hls-v1
    {manifest_key}
    {user_id}
    {profile_id}
    {session_uuid}
    {expires_unix}

The HMAC algorithm is SHA-256 using GOMOVIE_MEDIA_SIGNING_KEY, a separate random 32+-character secret. Returned URL query values: v, uid, pid, sid, exp, sig. Edge URL must be HTTPS. Token TTL defaults to 120 seconds. The stream session has a 90-second lease refreshed through an authenticated heartbeat. Media-edge workers are NOT included in this initial milestone.

**Before streaming:** implement and independently test a private edge/origin that validates HMAC in constant time, expiration, stream-session ownership and revocation, subscription and territory rights, and restrictions on *every* manifest, media segment, encryption-key, subtitle and download request. Ensure that manifests never redirect into a public unsigned bucket. CDN cache keys must not expose private content. Add renewal/retry behavior to the video player. Signed URLs alone are not DRM. Licensed DRM services and content encryption are a separate project.

## API v1

Public discovery (rate limited):
- GET /api/v1/movies, /api/v1/movies/{slug}
- GET /api/v1/series, /api/v1/series/{slug}
- GET /api/v1/series/{slug}/seasons/{number}
- Filters on listing endpoints: q, genre, country, year, actor, min_rating

Authenticated Sanctum endpoints:
- GET/POST /api/v1/user/profiles, DELETE /api/v1/user/profiles/{id}
- GET /api/v1/user/watch-history?profile_id=..., POST /api/v1/user/watch-history/sync
- GET/POST /api/v1/user/watchlist, DELETE /api/v1/user/watchlist/{id}
- POST /api/v1/user/ratings
- GET /api/v1/user/subscription; POST /api/v1/user/subscription/subscribe returns 503 (disabled)
- POST /api/v1/stream/movie/{id}/token, POST /api/v1/stream/episode/{id}/token
- POST /api/v1/stream/session/{sessionId}/heartbeat, DELETE /api/v1/stream/session/{sessionId}

## Not yet delivered

- Account sign-up, sign-in, password-reset and email verification interface
- Admin CMS for metadata, rights verification, publishing, genres, actors and media jobs
- TMDB API mapping/synchronization, localization and attribution
- Secure media ingest, transcoding pipelines (1080/720/480 HLS/DASH), encrypted object storage, CDN edge workers
- Player with VTT/SRT captions, adaptive bitrate selection, retry/heartbeat and full-screen controls
- DRM licenses (Widevine/FairPlay/PlayReady), watermarking and content fingerprinting
- Paid subscriptions, rental checkout, verified webhook ingestion, merchant onboarding, taxes, refunds, chargebacks
- Trusted geo-IP, multi-region rights, age classification, parental controls
- Production-ready UI (actual catalog cards, carousel, search, profile flows, player)
- Authentication provisioning, onboarding installer and aaPanel/cPanel customer-acceptance tests
- Composer/npm lockfiles (must be generated/committed in a dependency-enabled environment before any release)

## Verification and release gates

1. Validate Composer dependencies for Laravel 13 and PHP 8.4; commit composer.lock and package-lock.json.
2. Run php artisan migrate:fresh against disposable MySQL 8 and PostgreSQL 16 databases, not production.
3. Run PHPUnit feature tests and real Redis contention tests using multiple concurrent clients.
4. Verify media edge denies expired, altered, replayed, revoked, segment and key requests; test geo / subscription revocation.
5. Complete video licensing audit before ingesting any film or show.
6. Perform sandbox/payment-provider legal eligibility and webhook acceptance before activating subscriptions.
7. Test desktop/mobile responsive interface and shared-host installation/upgrades independently.
8. Block production deployment until all relevant gates have passed.
