# Private streaming edge — security integration (not deployed)

The example Cloudflare Worker in worker.js acts as an authorization boundary for a private R2 media bucket. It is **not DRM**, does not authorize film distribution, and must not be exposed as a live production service before independent review.

## Required bindings and secrets

- PRIVATE_MEDIA: private R2 bucket binding, with **no public bucket URL**
- MEDIA_SIGNING_KEY: same independent 32+ byte HMAC secret as Laravel GOMOVIE_MEDIA_SIGNING_KEY
- EDGE_SHARED_KEY: distinct 32+ byte secret matching Laravel GOMOVIE_EDGE_SHARED_KEY
- APP_ORIGIN: HTTPS Laravel application origin for server-to-server checks
- FRONTEND_ORIGIN: one explicitly allowed HTTPS application origin

Use Cloudflare secret bindings, never commit secret values. Restrict and monitor Worker routes, failure budgets and egress. Use a dedicated hostname where each path matches the private R2 object key. The entry manifest path must be contained in an asset-specific prefix.

## Authorization sequence

1. Laravel validates a signed-in viewer, ownership, verified email, content rights, current subscription and concurrent stream slot.
2. Laravel returns a short-lived signed URL to an asset-specific HLS manifest.
3. Worker validates HMAC, expiration and asset key isolation.
4. Worker calls the Laravel internal authorization endpoint with a separate shared secret on **every** media request.
5. Laravel rechecks active stream lease, account, profile, rights, asset and subscription.
6. Only after approval does Worker read from private R2. All manifest references for variant playlists, segments, captions and keys are rewritten to carry the original grant and manifest key.
7. All responses are no-store. There is no public fallback or unsigned origin redirect.

## Outstanding release blockers

- Account identity management, HLS player URL renewal and retry must be integrated.
- Load and concurrency tests against real Redis and the private media edge.
- CDN cache-key, token replay, revocation race, geo-rights, penetration and key-rotation testing.
- Content-appropriate licensed DRM (Widevine/FairPlay/PlayReady) and DRM license service if contracts require it.
- Support for larger files, range requests, remote manifests and non-HLS formats.
- Origin-region and territory validation. This prototype allows only globally licensed catalog rights.
- Actual signed media and provider acceptance with rights-holder permission.

Test local pure-function logic with: node --test workers/gomovie-media-edge/worker.test.mjs.
