<?php

declare(strict_types=1);

/**
 * Isolated on-host auto-publication acceptance using a newly generated, original
 * synthetic MP4. Not a web endpoint. This must only run as www from its verified
 * shell controller. Production .env and systemd workers are never changed.
 *
 * Modes: check, setup, queue, verify, cleanup.
 */
use App\Jobs\AutoPublishUploadedVideo;
use App\Models\Movie;
use App\Services\Media\AutomaticPublicationService;
use App\Services\Media\MediaPackageInspector;
use App\Services\Media\PrivateVideoIntake;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Symfony\Component\Process\Process;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(2);
}
$mode = $argv[1] ?? '';
$qa = $argv[2] ?? '';
if (!in_array($mode, ['check', 'setup', 'queue', 'verify', 'cleanup'], true)
    || !is_string($qa) || !preg_match('#\A/tmp/gomovie-autopub-qa\.[A-Za-z0-9]{8}\z#D', $qa)
    || !is_dir($qa) || is_link($qa) || realpath($qa) !== $qa) {
    fwrite(STDERR, "Invalid QA mode or workspace.\n");
    exit(2);
}

require_once getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Laravel's CLI exception handler can display errors without propagating a
// failure exit status for direct scripts. Explicitly guard all QA operations.
try {
$statePath = $qa.'/state.json';
$readState = static function () use ($statePath): array {
    if (!is_file($statePath) || is_link($statePath)) {
        return [];
    }
    $s = json_decode((string) file_get_contents($statePath), true);
    return is_array($s) ? $s : [];
};
$writeState = static function (array $state) use ($statePath): void {
    $encoded = json_encode($state, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    if (file_put_contents($statePath, $encoded, LOCK_EX) === false) {
        throw new RuntimeException('Unable to checkpoint QA records for cleanup.');
    }
    chmod($statePath, 0600);
};
$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};

if ($mode === 'check') {
    foreach (['movies','users','content_rights','media_ingests','media_assets',
              'public_media_files','admin_audit_events','jobs'] as $table) {
        $assert(Schema::hasTable($table), "Missing required table: $table");
    }
    $assert(config('gomovie.automatic_publication_enabled') !== true
        && config('gomovie.public_hls_enabled') !== true
        && config('gomovie.transcoding_enabled') !== true,
        'Production publication switches must stay disabled during isolated acceptance.');
    $assert(config('gomovie.media_queue_connection') === 'database_media',
        'Requires isolated database_media queue connection.');
    $assert((int) config('queue.connections.database_media.retry_after', 0) > 7500,
        'Unsafe media retry_after.');
    $admin = DB::table('users')->whereIn('role',['admin','super_admin'])
        ->where('is_active', true)->whereNotNull('email_verified_at')->orderBy('id')->first();
    $assert((bool) $admin, 'No active verified administrator for the QA upload audit.');
    $assert(is_writable(Storage::disk('local')->path('')),
        'Private original storage is not writable.');
    foreach (['ffmpeg_binary','ffprobe_binary','malware_scanner_binary'] as $name) {
        $path = (string) config('gomovie.'.$name);
        $assert(str_starts_with($path, '/') && is_file($path) && is_executable($path),
            "Unusable binary: $name");
        if ($name === 'malware_scanner_binary') {
            $assert(basename($path) === 'clamscan', 'Only real ClamAV clamscan is permitted.');
            $scan = new Process([$path, '--no-summary', '--infected', '/etc/hosts']);
            $scan->setTimeout(45);
            $scan->run();
            $assert($scan->getExitCode() === 0, 'ClamAV definitions or access are not ready.');
        }
    }
    echo "GOMOVIE_AUTO_QA_APP_PREFLIGHT=PASS\n";
    echo "EXISTING_ADMIN=VERIFIED\n";
    echo "REAL_CLAMAV=READY\n";
    echo "ISOLATED_QUEUE=database_media\n";
    echo "PUBLICATION_SWITCHES=OFF\n";
    exit(0);
}

if ($mode === 'queue') {
    $state = $readState();
    $queue = $state['queue'] ?? '';
    $assert(is_string($queue) && (bool) preg_match('/\Agomovie-qa-[a-f0-9]{16}\z/D', $queue),
        'Missing QA queue name.');
    echo $queue;
    exit(0);
}

if ($mode === 'setup') {
    $assert(!file_exists($statePath), 'A previous QA run already occupies this workspace.');
    $assert(config('gomovie.automatic_publication_enabled') !== true
        && config('gomovie.public_hls_enabled') !== true
        && config('gomovie.transcoding_enabled') !== true, 'Do not run on globally activated publication.');
    $admin = DB::table('users')->whereIn('role',['admin','super_admin'])
        ->where('is_active', true)->whereNotNull('email_verified_at')->orderBy('id')->first();
    $assert((bool) $admin, 'No eligible existing admin account.');
    $run = bin2hex(random_bytes(8));
    $state = [
        'run' => $run,
        'queue' => 'gomovie-qa-'.$run,
        'slug' => 'gomovie-autopub-qa-'.$run,
        'movie_id' => null,
        'rights_id' => null,
        'ingest_id' => null,
    ];
    $writeState($state);

    $path = $qa.'/original.mp4';
    $ffmpeg = (string) config('gomovie.ffmpeg_binary');
    $render = new Process([
        $ffmpeg, '-hide_banner','-loglevel','error','-nostdin','-y',
        '-f','lavfi','-i','testsrc2=duration=2:size=854x480:rate=24',
        '-f','lavfi','-i','sine=frequency=440:duration=2',
        '-c:v','libx264','-preset','ultrafast','-pix_fmt','yuv420p',
        '-c:a','aac','-shortest','-f','mp4',$path,
    ]);
    $render->setTimeout(100);
    $render->run();
    $assert($render->isSuccessful() && is_file($path)
        && filesize($path) > 1024 && filesize($path) < 2 * 1048576,
        'Original synthetic MP4 generation failed.');
    echo "ORIGINAL_TEST_VIDEO=GENERATED_BY_FFMPEG\n";
    echo "ORIGINAL_TEST_VIDEO_SHA256=".hash_file('sha256', $path)."\n";

    $movie = Movie::create([
        'title' => 'Gomovie Original QA Clip '.$run,
        'slug' => $state['slug'],
        'overview' => 'Original self-generated audiovisual test pattern; ephemeral acceptance fixture, not licensed third-party footage.',
        'runtime_minutes' => 1,
        'is_premium' => false,
        'status' => 'draft',
    ]);
    $state['movie_id'] = $movie->id;
    $writeState($state);

    $rightsId = DB::table('content_rights')->insertGetId([
        'licensable_type' => 'movie', 'licensable_id' => $movie->id,
        'rights_reference' => 'GOMOVIE-GENERATED-ORIGINAL-QA-'.$run.'-'.substr(hash_file('sha256', $path), 0, 16),
        'territories' => null, 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour(),
        'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $state['rights_id'] = $rightsId;
    $writeState($state);
    echo "ORIGINAL_QA_RIGHTS=DOCUMENTED_FOR_GENERATED_CLIP_ONLY\n";

    $video = new UploadedFile($path, 'gomovie-original-qa.mp4', 'video/mp4', null, true);
    $ingestId = app(PrivateVideoIntake::class)->store('movie', (int) $movie->id,
        $video, (int) $admin->id, null);
    $state['ingest_id'] = $ingestId;
    $writeState($state);
    $assert(DB::table('media_ingests')->where('id', $ingestId)
        ->where('status','quarantined')->where('security_scan_status','pending')->exists(),
        'Uploaded test source did not enter secure quarantine.');
    echo "REAL_UPLOAD_INTAKE=PRIVATE_QUARANTINE\n";

    // The normal long-running media worker never listens to this one-off queue.
    // The one-shot worker is the only consumer and receives runtime-only flags.
    $job = new AutoPublishUploadedVideo((int) $ingestId);
    DB::connection()->transaction(function () use ($job, $state): void {
        \Illuminate\Support\Facades\Queue::connection('database_media')
            ->pushOn($state['queue'], $job);
    });
    $assert(DB::table('jobs')->where('queue', $state['queue'])->count() === 1,
        'The single queued automatic-publication job was not created.');
    echo "ISOLATED_AUTO_PUBLICATION_JOB=QUEUED\n";
    echo "QA_MOVIE_SLUG=".$state['slug']."\n";
    exit(0);
}

if ($mode === 'verify') {
    $state = $readState();
    $id = (int) ($state['ingest_id'] ?? 0);
    $movieId = (int) ($state['movie_id'] ?? 0);
    $assert($id > 0 && $movieId > 0, 'Missing QA ingest or movie ID.');
    $assert(config('gomovie.automatic_publication_enabled') === true
        && config('gomovie.public_hls_enabled') === true
        && config('gomovie.transcoding_enabled') === true,
        'Isolated validation flags did not take effect.');
    $assert(DB::table('jobs')->where('queue', $state['queue'])->count() === 0,
        'QA queue did not finish its one-shot job.');
    $ingest = DB::table('media_ingests')->where('id', $id)->first();
    $assert($ingest && $ingest->status === 'encoded'
        && $ingest->security_scan_status === 'clean'
        && $ingest->rights_review_status === 'verified'
        && $ingest->approved_by && $ingest->approved_at,
        'Real scan/encoding pipeline did not complete: '.($ingest->pipeline_error ?? 'unknown'));
    $movie = DB::table('movies')->where('id', $movieId)->first();
    $assert($movie && $movie->status === 'published' && !$movie->is_premium,
        'Generated test movie is not published as a free title.');
    $asset = app(AutomaticPublicationService::class)->readyAsset('movie', $movieId);
    $assert((bool) $asset, 'Automatic HLS asset is not ready for playback.');
    $package = app(MediaPackageInspector::class)->inspect($id);
    $assert(count($package['files']) >= 3, 'Not enough verified HLS package files.');
    $assert(DB::table('public_media_files')->where('media_asset_id', $asset->id)->count()
        === count($package['files']), 'Public media fingerprints do not match the package.');
    echo "REAL_CLAMAV_SCAN=PASSED\n";
    echo "REAL_FFMPEG_ENCODE=PASSED\n";
    echo "HLS_INTEGRITY_FINGERPRINTS=VERIFIED\n";
    echo "AUTOMATIC_PUBLICATION=READY\n";

    $http = app(HttpKernel::class);
    $get = static function (string $path) use ($http): \Symfony\Component\HttpFoundation\Response {
        $request = Request::create($path, 'GET', [], [], [], [
            'HTTP_HOST' => 'gomovie.tonsamith.com', 'HTTPS' => 'on',
        ]);
        $response = $http->handle($request);
        $http->terminate($request, $response);
        return $response;
    };
    $master = $get('/hls/'.$asset->id.'/master.m3u8');
    $assert($master->getStatusCode() === 200
        && str_starts_with((string) $master->headers->get('Content-Type'), 'application/vnd.apple.mpegurl'),
        'Public master HLS route failed HTTP kernel acceptance.');
    $variant = collect(array_keys($package['files']))->first(
        fn ($file) => str_ends_with($file, '/index.m3u8'));
    $segment = collect(array_keys($package['files']))->first(
        fn ($file) => str_ends_with($file, '.ts'));
    $assert(is_string($variant) && is_string($segment), 'Encoded HLS playlist/segment unavailable.');
    foreach ([$variant, $segment] as $path) {
        $assert($get('/hls/'.$asset->id.'/'.$path)->getStatusCode() === 200,
            'Public HLS route rejected its verified media file.');
    }
    $watch = $get('/watch/movie/'.$state['slug']);
    $assert($watch->getStatusCode() === 200 && is_string($watch->getContent())
        && str_contains($watch->getContent(), 'data-public-hls-url'),
        'Guest watch page did not display the public video player.');
    echo "PUBLIC_HLS_MASTER_HTTP=200_IN_PROCESS\n";
    echo "PUBLIC_HLS_VARIANT_HTTP=200_IN_PROCESS\n";
    echo "PUBLIC_HLS_SEGMENT_HTTP=200_IN_PROCESS\n";
    echo "GUEST_WATCH_PAGE_HTTP=200_IN_PROCESS\n";

    DB::table('content_rights')->where('id', $state['rights_id'])
        ->where('licensable_type','movie')->where('licensable_id',$movieId)
        ->update(['status' => 'revoked', 'updated_at' => now()]);
    $assert($get('/hls/'.$asset->id.'/master.m3u8')->getStatusCode() === 404,
        'Revoking original QA rights failed to revoke public HLS.');
    echo "RIGHTS_REVOCATION_HTTP=404_IN_PROCESS\n";
    echo "GOMOVIE_REAL_AUTOPUBLIC_QA=PASS\n";
    exit(0);
}

if ($mode === 'cleanup') {
    $state = $readState();
    $movieId = (int) ($state['movie_id'] ?? 0);
    $ingestId = (int) ($state['ingest_id'] ?? 0);
    $slug = (string) ($state['slug'] ?? '');
    if ($state === []) {
        echo "QA_CLEANUP=NOTHING_TO_REMOVE\n";
        exit(0);
    }
    $assert(preg_match('/\Agomovie-autopub-qa-[a-f0-9]{16}\z/D', $slug) === 1,
        'Refusing cleanup without an exact original QA slug.');
    if ($movieId > 0) {
        $assert(DB::table('movies')->where('id',$movieId)->where('slug',$slug)->exists(),
            'Refusing cleanup of an unknown catalog movie.');
    }
    $rightsId = (int) ($state['rights_id'] ?? 0);
    $queueName = (string) ($state['queue'] ?? '');
    $assert((bool) preg_match('/\Agomovie-qa-[a-f0-9]{16}\z/D', $queueName),
        'Invalid QA queue for cleanup.');
    DB::table('jobs')->where('queue',$queueName)->delete();
    if ($ingestId > 0) {
        $ingest = DB::table('media_ingests')->where('id',$ingestId)
            ->where('ingestable_type','movie')->where('ingestable_id',$movieId)->first();
        if ($ingest) {
            $assets = DB::table('media_assets')->where('streamable_type','movie')
                ->where('streamable_id',$movieId)->pluck('id');
            DB::table('public_media_files')->whereIn('media_asset_id',$assets)->delete();
            DB::table('media_assets')->whereIn('id',$assets)->delete();
            DB::table('admin_audit_events')->where('resource_type','media_ingest')
                ->where('resource_id',$ingestId)->delete();
            DB::table('media_ingests')->where('id',$ingestId)->delete();

            $manifest = (string) ($ingest->encoded_manifest_key ?? '');
            if (preg_match('#\Atranscodes/staging/'.preg_quote((string) $ingestId,'#')
                .'/[0-9a-f-]{36}/master\.m3u8\z#iD', $manifest)) {
                Storage::disk('local')->deleteDirectory(dirname($manifest));
            }
            $source = (string) $ingest->object_key;
            if (preg_match('#\Aingests/[0-9]{4}/[0-9]{2}/[0-9a-f-]{36}\.mp4\z#iD', $source)) {
                Storage::disk('local')->delete($source);
            }
        }
    }
    if ($rightsId > 0) {
        DB::table('content_rights')->where('id',$rightsId)
            ->where('licensable_type','movie')->where('licensable_id',$movieId)->delete();
    }
    if ($movieId > 0) DB::table('movies')->where('id',$movieId)->where('slug',$slug)->delete();
    echo "GENERATED_QA_CATALOG_AND_RIGHTS=REMOVED\n";
    echo "GENERATED_QA_ORIGINAL_AND_HLS=REMOVED\n";
    echo "EXISTING_MOVIES_AND_UPLOADS=UNCHANGED\n";
    echo "GOMOVIE_AUTO_QA_CLEANUP=COMPLETE\n";
    exit(0);
}
} catch (\Throwable $error) {
    // Avoid reporting a successful test after an exception was printed.
    fwrite(STDERR, 'GOMOVIE_AUTO_QA_FATAL='.get_class($error)
        .': '.$error->getMessage()."\n");
    exit(89);
}
