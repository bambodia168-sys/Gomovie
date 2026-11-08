# Gomovie — Movies & TV Streaming Platform

**Version:** v0.0.1 — active development  
**Author:** [Ton Samith](https://tonsamith.com)  
**License:** Proprietary Commercial License for original Gomovie code. Upstream components retain their individual licenses; see THIRD_PARTY_NOTICES.md.

Gomovie is a Laravel 13-based catalog and secure video streaming project for authorized movies and TV series. The repository contains **working development modules**, but is not an accepted or deployed commercial streaming service.

## Technology

Laravel 13, PHP 8.4+, Tailwind CSS 4, Alpine.js, hls.js 1.7, MySQL 8.0 / PostgreSQL 16, Sanctum, Redis for atomic stream coordination, FFmpeg/FFprobe for gated private HLS encoding, and a prototype private Cloudflare R2 Worker. Inertia/Vue and full DASH/DRM are not installed.

## Current functionality

- Viewer registration, login, logout, strong-password reset, email verification and account settings.
- Protected viewer profiles (maximum five), password changes with token revocation, personal watchlists and private watch history.
- Role-restricted admin dashboard, movie and series metadata CRUD, seasons and episodes editor, user management and audited administrative mutations.
- Draft-only TMDB metadata import, artwork uploads and nonpublic/quarantined video intake.
- Published catalog pages, responsive dark-first movie cards, database-driven featured-title carousel, title details, search and filters.
- HLS.js player shell with authenticated playback-grant requests, session renewal and stop controls; player is hidden until explicit media-edge activation.
- Expiring HMAC-signed playback grants, fail-closed license and entitlement checks, stream-session lease enforcement and Redis distributed locks.
- Prototype Cloudflare Worker for asset-scoped signed HLS, private R2 reads and per-request Laravel session authorization.
- FFmpeg private encoding service and isolated queue job for independently approved content. The transcode feature is **disabled by default**; encoded assets are not automatically streamed or published.
- Centralized content-rights and premium-playback policy, admin-only System Health, opt-in real worker queue probes and isolated long-running media queue configuration.
- Non-destructive installation/upgrade preflight, offline backup bundle SHA-256 verifier, synthetic SQLite restore rehearsal, and independently reviewed private S3/R2 media staging (disabled by default, not live playback).
- CI: Laravel/PHPUnit feature tests, JS Worker tests, frontend Vite build, FFmpeg synthetic HLS encoding fixture, independent MySQL 8 and PostgreSQL 16 acceptance suites.

## Runtime prerequisites

PHP 8.4+ and Laravel 13 supported extensions, Composer, Node.js 24 for frontend assets, MySQL 8 or PostgreSQL 16, Redis for production playback coordination, and a verified SMTP provider for real email delivery. FFmpeg/FFprobe plus isolated queue workers are needed only when the separately gated media workflow is approved.

## Local development

~~~sh
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
php artisan migrate
php artisan test
node --test workers/gomovie-media-edge/worker.test.mjs
php artisan gomovie:media-preflight
php artisan gomovie:install-preflight --mode=upgrade --json  # intentionally blocked without recovery evidence
bash support/recovery-rehearsal.sh  # disposable SQLite only
~~~

For development without email provider, Laravel's log mailer writes notifications to local logs rather than delivering messages. Do not treat email verification as operational until SMTP delivery is verified.

Admin access is NOT assigned through registration. A server operator can grant the highest role only to an existing verified account using an interactive CLI command:

~~~sh
php artisan gomovie:grant-admin verified@example.com
~~~

Never use development passwords, example emails or test keys in a live application. Serve the public/ directory only.

## Security defaults

**GOMOVIE_BILLING_ENABLED=false**, **GOMOVIE_EDGE_ENABLED=false**, **GOMOVIE_TRANSCODING_ENABLED=false**, and **GOMOVIE_MEDIA_STAGING_ENABLED=false**. They must remain disabled until verified independent integrations and acceptance tests are completed.

A TMDB metadata record or a published catalog title is **not evidence of streaming rights**. Media must be licensed for all served territories. Playback grants are not DRM; real content protection requires properly licensed DRM services and full segment/key coverage.

The application never bundles film footage or production secrets. Payments and pay-per-view remain unavailable until a real provider, sandbox webhook verification, refunds, taxes and account entitlements have passed review.

## Implementation and release notes

- [Architecture & access boundaries](docs/ARCHITECTURE.md)
- [Private video encoding pipeline](docs/MEDIA_PIPELINE.md)
- [Cloudflare private media edge](workers/gomovie-media-edge/README.md)
- [Production acceptance gates](docs/RELEASE_ACCEPTANCE.md)
- [Operations monitoring and queue supervision](docs/OPERATIONS.md)
- [Independent installation, upgrade and backup recovery engineering](docs/INSTALLATION_RECOVERY.md)

No aaPanel deployment or real-device acceptance has been performed by these source-control milestones. Never migrate production without backup, reviewed rollback and independent installation testing.
