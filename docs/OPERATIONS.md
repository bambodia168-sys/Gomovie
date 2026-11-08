# Gomovie operations architecture

These facilities are source-code infrastructure, not proof of a production installation. A green application check does not mean that licensed DRM, paid billing, provider credentials, external SMTP, secure storage or real-device acceptance have been completed.

## Responsibility boundaries

- app/Services/Rights/GlobalRights.php — one strict active worldwide-distribution rights evaluator. Region-restricted grants remain blocked until trusted geographic enforcement is available.
- app/Services/Rights/PlaybackEligibility.php — verified and active accounts, owned non-kids profiles, published titles and parent series, global rights, and premium subscription eligibility.
- app/Services/Operations/OperationalSnapshot.php — sanitized read-only operational checks. Does not return secret values, profile information or detailed exceptions.
- app/Jobs/ProcessQueueProbe.php — harmless worker-execution marker, never media publishing or payment.
- app/Console/Commands/DispatchQueueProbe.php — explicit or optional scheduled dispatch; cannot use sync driver.
- app/Console/Commands/OperationsCheck.php — operator read-only diagnostics.
- app/Console/Commands/PruneQueueProbes.php — deletes only expired transient queue probes.
- app/Http/Controllers/Admin/OperationsController.php — system checks visible only to administrator roles.
- database/migrations/2026_11_08_000500_create_queue_probe_runs_table.php — migration supporting queue-probe history.

## Separate queue connections

Default queue: database or redis connection, short processing tasks such as notifications.

Private media queue: database_media or redis_media, named gomovie-media, retry_after 7800 seconds. Media worker timeout is 7500 seconds (125 minutes), ensuring the retry visibility interval is longer than the worker timeout.

The dedicated long-running connection is selected by GOMOVIE_MEDIA_QUEUE_CONNECTION. It defaults to database_media, which works with shared-hosting database queues when the operator explicitly configures long-running process management. For Redis installations use redis_media after validation.

Do not reuse the default short-visibility queue for FFmpeg. Do not run an unsupervised media queue worker on live licensed files. Keep a single long-running media process until storage limits, FFmpeg resource constraints, concurrency, and failure recovery are accepted.

Example supervisor planning template (adapt paths and user before use):

~~~ini
[program:gomovie-default]
directory=/app/gomovie
command=/usr/bin/php artisan queue:work redis --queue=default --sleep=3 --tries=3 --timeout=60 --max-time=3600
user=gomovie
autostart=true
autorestart=true
stopwaitsecs=90
redirect_stderr=true
stdout_logfile=/app/gomovie/storage/logs/queue-default.log

[program:gomovie-media]
directory=/app/gomovie
command=/usr/bin/php artisan queue:work redis_media --queue=gomovie-media --sleep=3 --tries=1 --timeout=7500 --max-jobs=1
user=gomovie
numprocs=1
autostart=false
autorestart=false
stopwaitsecs=7800
redirect_stderr=true
stdout_logfile=/app/gomovie/storage/logs/queue-media.log
~~~

The paths and OS identity are placeholders. A deployment must configure network isolation, storage quotas, non-root execution, file permissions, queue retry and stop windows, actual supervisor availability, and periodic worker restarts. Shared hosting without a persistent process runner is not sufficient for unattended long transcodes.

## Diagnostics and monitoring

Read-only, safe against an unconfigured platform:

~~~sh
php artisan gomovie:ops-check --json
php artisan gomovie:media-preflight --json
~~~

Once real supervised asynchronous workers are ready:

~~~sh
php artisan gomovie:queue-probe default
php artisan gomovie:queue-probe gomovie-media
~~~

The two probes verify individual execution, not uninterrupted worker uptime. To opt in to the five-minute queue checks, set GOMOVIE_QUEUE_PROBES_ENABLED=true only after workers are actually supervised. The scheduler requires a separately supervised cron invoking php artisan schedule:run every minute. When probe monitoring is enabled, the System Health page warns about missing or stale successful probes after 15 minutes.

Queue history older than 30 days is automatically pruned by the scheduler, while failed_jobs and admin_audit_events are never pruned by this feature.

## Safe release gates

Keep GOMOVIE_BILLING_ENABLED=false, GOMOVIE_EDGE_ENABLED=false and GOMOVIE_TRANSCODING_ENABLED=false during development. Streaming keys must be rotated in a secure secret manager. The FFmpeg pipeline does not publish ready media assets or license content. Obtain independent approval of distribution contracts, malware scans, media output, edge authorization, rollback, off-site backups, verified SMTP and physical-device tests.

Source-only operations described above have not modified any aaPanel or cPanel installation.
