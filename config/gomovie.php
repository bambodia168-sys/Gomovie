<?php

return [
    // HTTPS base URL of a private media edge. No public originals.
    'media_edge_url' => env('GOMOVIE_MEDIA_EDGE_URL'),
    // Independent high-entropy signing key, not the application key.
    'media_signing_key' => env('GOMOVIE_MEDIA_SIGNING_KEY'),
    'stream_token_ttl' => (int) env('GOMOVIE_STREAM_TOKEN_TTL', 120),
    'heartbeat_ttl' => 90,
    // Kept disabled unless provider eligibility, webhooks and compliance are accepted.
    'billing_enabled' => filter_var(env('GOMOVIE_BILLING_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'tmdb_token' => env('GOMOVIE_TMDB_TOKEN'),
];
