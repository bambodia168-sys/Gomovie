# Gomovie — cinematic frontend design and acceptance

The Gomovie UI follows the owner-approved visual mockups as **visual design direction**, not copied screenshots or bundled movie-poster assets. It is real Laravel 13 + Blade, Tailwind 4 CSS, Alpine.js and the existing first-party HLS.js player integration. No external UI framework, hotlinked mockup screenshot, sample actor poster or unlicensed film media is bundled.

## Structure and components

- resources/css/app.css: consolidated Gomovie tokens and responsive layout rules prefixed \`gm-\`, coexisting with studio UI and preserving one version-free stylesheet.
- resources/js/app.js: existing Alpine boot and player.js, plus a real share/copy-link action.
- resources/views/layouts/app.blade.php: branded navigation, working movie/series/free/premium search links, mobile nav, current-language label, profile entry, footer, skip link.
- resources/views/layouts/auth.blade.php and resources/views/auth/: shared cinematic account screens: login, register, reset, forgot and verification. No inert Google sign-in button: OAuth integration is not yet configured.
- resources/views/frontend/home.blade.php: layered carousel and functional manual navigation using the current published catalog, latest releases, real thirty-day engagement rankings when available, and recently added rows. No static fake media records.
- resources/views/frontend/browse.blade.php: working title text and access filters backed by public published metadata.
- resources/views/frontend/partials/card.blade.php: reused poster thumbnail and safe text-only fallback, with real premium/free labels and no invented ratings.
- resources/views/frontend/detail.blade.php: two accessible display modes via query string \`?view=widescreen\` (16:9) and \`?view=portrait\` (9:16), validated published-series episode selection via \`?episode=<id>\`, authorized player shell, Save to My List and functional Share actions, and published-only related titles in the right sidebar.
- app/Support/CatalogArtwork.php: only approved TMDB file paths and existing local artwork keys are rendered; arbitrary remote URLs are rejected.

## Nonnegotiable functional limits

The portrait switch only changes the display container. Actual media file orientation, renditions, poster imagery, subtitle correctness, HLS authorization and device-specific playback require real approved media, R2 edge acceptance and device testing. No claim of 4K, DRM, free timed preview, credits, Google OAuth, paid purchase, subscription activation or shipping of movie files is made. Paid media and player delivery remain off until separate provider/rights approval.

The \`Top Picks for You\` section is currently editorial recency ordering, not an individualized recommendation engine. \`Trending Now\` is shown only when nonzero thirty-day watch history exists; it is not seeded with fake rankings. \`New Releases\` does not falsely say \`Today\` unless a real publication date is available.

## Automated verification

Run isolated QA only, never against the installed customer database:

~~~sh
npm install --ignore-scripts && npm run build
vendor/bin/phpunit --filter CinematicFrontendTest
vendor/bin/phpunit --filter PreInstallerFrontendBackendSmokeTest
~~~

CI also runs complete PHP suites with separate MySQL 8 and PostgreSQL 16 environments, JS Worker tests, a synthetic FFmpeg encoding fixture, and the extracted customer ZIP five-step installation acceptance. Customer ZIP builds are triggered when frontend source changes.

## Physical acceptance before production

- Desktop: Chrome/Firefox/Safari visual parity at 1280–1600px, carousel controls/keyboard focus and contrast.
- Mobile: real iPhone and Android layout at 320–430px, navigation, carousel, poster rails, auth forms, portrait video, 16:9 playback, right sidebar stacking.
- Tablet: at 768–1024px, portrait and landscape; no clipped controls or overlapping related cards.
- Real media: independently licensed 9:16 and 16:9 test content, verified edge authorization, manifest refresh, subtitles, DRM if applicable, session cleanup and logout.
- Accounts: real SMTP verification/reset, suspended users, watchlists and secure private session behavior.
- Hosting: real aaPanel and cPanel fresh installation from the generated QA ZIP with witnessed TLS, PHP-FPM, database, storage, backup, restore and rollback evidence.

The mockups are **not** proof of implementation on a physical screen. A passing CI build is **not** live deployment acceptance.
