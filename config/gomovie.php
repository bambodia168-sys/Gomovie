<?php

return [
    'media_staging_enabled' => filter_var(env('GOMOVIE_MEDIA_STAGING_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'media_queue_connection' => env('GOMOVIE_MEDIA_QUEUE_CONNECTION', 'database_media'),
    'queue_probes_enabled' => filter_var(env('GOMOVIE_QUEUE_PROBES_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'edge_enabled' => filter_var(env('GOMOVIE_EDGE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'transcoding_enabled' => filter_var(env('GOMOVIE_TRANSCODING_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'ffmpeg_binary' => env('GOMOVIE_FFMPEG_BINARY', '/usr/bin/ffmpeg'),
    'ffprobe_binary' => env('GOMOVIE_FFPROBE_BINARY', '/usr/bin/ffprobe'),
    'media_edge_url' => env('GOMOVIE_MEDIA_EDGE_URL'),
    'media_signing_key' => env('GOMOVIE_MEDIA_SIGNING_KEY'),
    'edge_shared_key' => env('GOMOVIE_EDGE_SHARED_KEY'),
    'stream_token_ttl' => (int) env('GOMOVIE_STREAM_TOKEN_TTL', 120),
    'heartbeat_ttl' => 90,
    'billing_enabled' => filter_var(env('GOMOVIE_BILLING_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'tmdb_token' => env('GOMOVIE_TMDB_TOKEN'),
];
