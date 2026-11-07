# Gomovie

**Version:** v0.0.1 (development)  
**Author:** [Ton Samith](https://tonsamith.com)  
**Stack:** Laravel 13, PHP 8.4+, Tailwind CSS 4, Alpine.js, MySQL/PostgreSQL, Redis  
**License:** Proprietary Commercial License (Gomovie-specific work). See `THIRD_PARTY_NOTICES.md` for upstream notices.

Gomovie is an on-demand movies and TV-series catalog and streaming application designed for licensed media. This repository is a **development baseline**, not a production-ready video delivery service.

## Scope

- Published movies, TV series, seasons, episodes, genres and catalog discovery
- Subscription-aware authorization and per-account playback concurrency controls
- Private video asset references, expiring playback grants, and CDN-edge authorization
- Personal profiles, watchlists and resumable playback progress
- Rights-window enforcement, territory restrictions and auditability
- Planned: TMDB metadata synchronization, audio tracks, subtitles, adaptive HLS/DASH transcoding and streaming player, rentals, verified billing, administrative content studio

## Critical safeguards

1. Only distribute films and series for which the operator holds appropriate streaming and regional distribution rights.
2. TMDB metadata does not grant the rights to distribute video content.
3. A short-lived signed manifest URL is **not DRM**. Segment/key requests must also be protected at the edge; Widevine/FairPlay/PlayReady require licensed integrations.
4. Paid billing and real-money transactions remain **disabled** until payment-provider eligibility, webhook verification, refunds, tax/invoice handling and compliance testing are complete.
5. Do not point the server's public document root at the project root. Serve `public/` only.
6. Do not commit production secrets, credentials, media originals, or API tokens.

## Development

Create `.env` from `.env.example`, install Composer and NPM dependencies, generate an application key, configure MySQL/PostgreSQL and Redis, migrate the database and build frontend assets. The application requires PHP extensions listed in the Laravel 13 deployment documentation.

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build
php artisan migrate
php artisan test
```

Never run migrations against production without backup, maintenance planning and acceptance checks. See `docs/ARCHITECTURE.md` for implementation status and security boundaries.
