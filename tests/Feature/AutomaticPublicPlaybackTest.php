<?php

namespace Tests\Feature;

use App\Jobs\AutoPublishUploadedVideo;
use App\Models\Movie;
use App\Models\Series;
use App\Models\Season;
use App\Models\Episode;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Media\AutomaticPublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class AutomaticPublicPlaybackTest extends TestCase
{
    use RefreshDatabase;

    private array $temp = [];

    protected function tearDown(): void
    {
        foreach ($this->temp as $path) {
            Storage::disk('local')->deleteDirectory($path);
        }
        parent::tearDown();
    }

    private function freeFilm(bool $premium = false): Movie
    {
        return Movie::create([
            'title' => 'Licensed Original Video QA',
            'slug' => 'licensed-original-video-'.strtolower(Str::random(8)),
            'is_premium' => $premium, 'status' => 'draft',
        ]);
    }

    private function enable(): void
    {
        config()->set('gomovie.automatic_publication_enabled', true);
        config()->set('gomovie.public_hls_enabled', true);
    }

    private function rights(string $type, int $id): void
    {
        DB::table('content_rights')->insert([
            'licensable_type' => $type, 'licensable_id' => $id,
            'rights_reference' => 'QA-ORIGINAL-WORLDWIDE-1',
            'status' => 'active', 'territories' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function package(string $type = 'movie'): array
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin', 'is_active' => true, 'email_verified_at' => now()])->save();
        if ($type === 'movie') {
            $movie = $this->freeFilm();
            $target = $movie;
            $parent = null;
        } else {
            $parent = Series::create([
                'title' => 'Original Series QA', 'slug' => 'original-series-'.strtolower(Str::random(7)),
                'is_premium' => false, 'status' => 'draft',
            ]);
            $season = Season::create([
                'series_id' => $parent->id, 'season_number' => 1, 'title' => 'Season One',
            ]);
            $target = Episode::create([
                'season_id' => $season->id, 'episode_number' => 1,
                'title' => 'Original Episode', 'status' => 'draft',
            ]);
        }
        $this->rights($type, (int) $target->id);
        $disk = Storage::disk('local');
        $sourceKey = 'ingests/2026/11/'.Str::uuid().'.mp4';
        $source = 'SYNTHETIC ORIGINAL VIDEO TEST BYTES';
        $disk->put($sourceKey, $source);
        $this->temp[] = dirname($sourceKey);
        $id = DB::table('media_ingests')->insertGetId([
            'ingestable_type' => $type, 'ingestable_id' => $target->id,
            'uploaded_by' => $admin->id, 'approved_by' => $admin->id,
            'approved_at' => now(), 'security_scan_status' => 'clean',
            'rights_review_status' => 'verified',
            'disk' => 'local', 'object_key' => $sourceKey,
            'original_name' => 'original-qa.mp4', 'mime' => 'video/mp4',
            'size_bytes' => strlen($source), 'sha256' => hash('sha256', $source),
            'status' => 'encoded', 'encoded_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $prefix = 'transcodes/staging/'.$id.'/'.Str::uuid();
        $this->temp[] = $prefix;
        $master = "#EXTM3U\n#EXT-X-VERSION:3\n#EXT-X-STREAM-INF:BANDWIDTH=1200000\n480p/index.m3u8\n";
        $variant = "#EXTM3U\n#EXT-X-VERSION:3\n#EXT-X-TARGETDURATION:6\n#EXT-X-MEDIA-SEQUENCE:0\n#EXT-X-PLAYLIST-TYPE:VOD\n#EXTINF:1.000,\nsegment_00000.ts\n#EXT-X-ENDLIST\n";
        $disk->put($prefix.'/master.m3u8', $master);
        $disk->put($prefix.'/480p/index.m3u8', $variant);
        $disk->put($prefix.'/480p/segment_00000.ts', 'SYNTHETIC MPEG-TS PACKET BYTES');
        DB::table('media_ingests')->where('id', $id)->update([
            'encoded_manifest_key' => $prefix.'/master.m3u8',
        ]);
        return [$id, $target, $parent, $prefix];
    }

    public function test_upload_is_queued_but_never_exposed_before_processing(): void
    {
        Storage::fake('local');
        Queue::fake();
        $this->enable();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $movie = $this->freeFilm();
        $video = UploadedFile::fake()->create('auto.mp4', 4, 'video/mp4');
        file_put_contents($video->getRealPath(), str_repeat('A', 4096));
        $this->actingAs($admin)->postJson('/upload-studio', [
            'type' => 'movie', 'title_id' => $movie->id,
            'video' => $video,
        ])->assertCreated()->assertJsonPath('automatic_publication_enabled', true);
        Queue::assertPushed(AutoPublishUploadedVideo::class, 1);
        $this->assertDatabaseHas('media_ingests', [
            'ingestable_type' => 'movie', 'status' => 'quarantined',
            'security_scan_status' => 'pending',
        ]);
        $this->assertDatabaseCount('media_assets', 0);
        $this->get('/watch/movie/'.$movie->slug)->assertNotFound();
    }

    public function test_missing_rights_remains_private_with_no_fake_approval(): void
    {
        $this->enable();
        config()->set('gomovie.transcoding_enabled', true);
        $movie = $this->freeFilm();
        $admin = User::factory()->create();
        $id = DB::table('media_ingests')->insertGetId([
            'ingestable_type' => 'movie', 'ingestable_id' => $movie->id,
            'uploaded_by' => $admin->id, 'disk' => 'local',
            'object_key' => 'ingests/2026/11/00000000-0000-4000-8000-000000000000.mp4',
            'original_name' => 'qa.mp4', 'mime' => 'video/mp4',
            'size_bytes' => 128, 'sha256' => str_repeat('f', 64),
            'status' => 'quarantined', 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(AutomaticPublicationService::class)->process($id);
        $this->assertDatabaseHas('media_ingests', [
            'id' => $id, 'status' => 'quarantined',
            'pipeline_error' => 'active_free_distribution_rights_required',
        ]);
        $this->assertDatabaseCount('content_rights', 0);
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_only_standalone_video_assets_use_the_guest_hls_route(): void
    {
        $this->enable();
        [$id, $video] = $this->package();
        $video->update(['is_standalone_video' => true, 'status' => 'published', 'published_at' => now()]);
        [$movieId] = $this->package();
        $auto = app(AutomaticPublicationService::class);
        $asset = $auto->finalize($id);
        $movieAsset = $auto->finalize($movieId);
        $manifest = route('public-hls.standalone', ['asset' => $asset, 'path' => 'master.m3u8']);

        $this->get('/watch/movie/'.$video->slug)->assertOk()
            ->assertSee('data-public-hls-url="/hls/standalone/'.$asset.'/master.m3u8"', false)
            ->assertDontSee('Sign In to Watch');
        $this->get($manifest)->assertOk()->assertHeader('Content-Type', 'application/vnd.apple.mpegurl');
        $this->get(route('public-hls.standalone', ['asset' => $asset, 'path' => '480p/segment_00000.ts']))
            ->assertOk()->assertHeader('Content-Type', 'video/mp2t');
        $this->get(route('public-hls.standalone', ['asset' => $movieAsset, 'path' => 'master.m3u8']))
            ->assertNotFound();

        DB::table('content_rights')->where('licensable_id', $video->id)->update(['status' => 'revoked']);
        $this->get($manifest)->assertNotFound();
    }

    public function test_public_free_movie_delivers_only_fingerprinted_hls_and_is_revocable(): void
    {
        $this->enable();
        [$id, $movie, , $prefix] = $this->package();
        $auto = app(AutomaticPublicationService::class);
        $asset = $auto->finalize($id);
        self::assertIsInt($asset);
        $this->assertDatabaseHas('media_assets', [
            'id' => $asset, 'status' => 'ready', 'storage_disk' => 'local',
        ]);
        $this->assertDatabaseCount('public_media_files', 3);
        $this->assertDatabaseHas('movies', ['id' => $movie->id, 'status' => 'published']);
        $manifest = route('public-hls.show', ['asset' => $asset, 'path' => 'master.m3u8']);
        $this->get($manifest)->assertRedirect(route('login'));
        $this->get('/watch/movie/'.$movie->slug)->assertOk()
            ->assertSee('Sign In to Watch')
            ->assertDontSee('data-public-hls-url=');
        $this->actingAs(User::factory()->create());
        $this->get($manifest)->assertOk()->assertHeader('Content-Type', 'application/vnd.apple.mpegurl');
        $this->get(route('public-hls.show', ['asset' => $asset, 'path' => '480p/segment_00000.ts']))
            ->assertOk()->assertHeader('Content-Type', 'video/mp2t');
        $this->get('/watch/movie/'.$movie->slug)->assertOk()
            ->assertSee('data-public-hls-url="/hls/'.$asset.'/master.m3u8"', false)
            ->assertSee('Watch now');
        $this->assertDatabaseHas('media_ingests', ['id' => $id, 'status' => 'encoded']);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'media.auto_publication_ready']);

        // A rights record with its evidence reference removed must not keep
        // previously published media accessible, even when status remains active.
        DB::table('content_rights')->where('licensable_type', 'movie')
            ->where('licensable_id', $movie->id)->update(['rights_reference' => null]);
        $this->get($manifest)->assertNotFound();
        $this->get('/watch/movie/'.$movie->slug)->assertOk()
            ->assertDontSee('data-public-hls-url=');
        DB::table('content_rights')->where('licensable_type', 'movie')
            ->where('licensable_id', $movie->id)->update([
                'rights_reference' => 'QA-ORIGINAL-WORLDWIDE-1',
            ]);
        $this->get($manifest)->assertOk();

        Storage::disk('local')->put($prefix.'/480p/segment_00000.ts', 'TAMPERED SEGMENT');
        $this->get(route('public-hls.show', ['asset' => $asset, 'path' => '480p/segment_00000.ts']))
            ->assertNotFound();
        DB::table('content_rights')->where('licensable_id', $movie->id)->update(['status' => 'revoked']);
        $this->get($manifest)->assertNotFound();
        $this->get('/watch/movie/'.$movie->slug)->assertOk()->assertDontSee('data-public-hls-url=');
    }

    public function test_episode_auto_publication_publishes_parent_series_but_never_premium(): void
    {
        $this->enable();
        [$id, $episode, $parent] = $this->package('episode');
        $asset = app(AutomaticPublicationService::class)->finalize($id);
        self::assertIsInt($asset);
        $this->assertDatabaseHas('episodes', ['id' => $episode->id, 'status' => 'published']);
        $this->assertDatabaseHas('series', ['id' => $parent->id, 'status' => 'published']);
        $url = '/watch/series/'.$parent->slug.'?episode='.$episode->id;
        $this->get($url)->assertOk()->assertSee('Sign In to Watch')
            ->assertDontSee('data-public-hls-url=');
        $this->actingAs(User::factory()->create());
        $this->get($url)->assertOk()->assertSee('data-public-hls-url=');

        $parent->forceFill(['is_premium' => true])->save();
        $this->get(route('public-hls.show', ['asset' => $asset, 'path' => 'master.m3u8']))
            ->assertNotFound();
        $this->get($url)->assertOk()->assertDontSee('data-public-hls-url=');
    }

    public function test_isolated_synthetic_video_completes_automatic_scan_encode_publish_pipeline(): void
    {
        $ffmpeg = '/usr/bin/ffmpeg';
        $ffprobe = '/usr/bin/ffprobe';
        if (!is_executable($ffmpeg) || !is_executable($ffprobe)) {
            if (getenv('GOMOVIE_REQUIRE_FFMPEG_SMOKE') === '1') {
                self::fail('FFmpeg and FFprobe must be available for isolated automatic-publication acceptance.');
            }
            $this->markTestSkipped('FFmpeg and FFprobe unavailable.');
        }

        $this->enable();
        config()->set('gomovie.transcoding_enabled', true);
        config()->set('gomovie.ffmpeg_binary', $ffmpeg);
        config()->set('gomovie.ffprobe_binary', $ffprobe);

        $movie = $this->freeFilm();
        $this->rights('movie', (int) $movie->id);
        $admin = User::factory()->create();
        $admin->forceFill([
            'role' => 'admin', 'is_active' => true, 'email_verified_at' => now(),
        ])->save();

        $key = 'ingests/2026/11/'.Str::uuid().'.mp4';
        Storage::disk('local')->makeDirectory(dirname($key));
        $this->temp[] = dirname($key);
        $path = Storage::disk('local')->path($key);
        $generate = new \Symfony\Component\Process\Process([
            $ffmpeg, '-hide_banner', '-loglevel', 'error', '-nostdin', '-y',
            '-f', 'lavfi', '-i', 'testsrc2=duration=1:size=640x360:rate=25',
            '-f', 'lavfi', '-i', 'sine=frequency=440:duration=1',
            '-c:v', 'libx264', '-preset', 'ultrafast', '-c:a', 'aac', '-shortest', $path,
        ]);
        $generate->setTimeout(90);
        $generate->run();
        self::assertTrue($generate->isSuccessful(), 'Synthetic video generator failed.');

        $id = DB::table('media_ingests')->insertGetId([
            'ingestable_type' => 'movie', 'ingestable_id' => $movie->id,
            'uploaded_by' => $admin->id, 'disk' => 'local', 'object_key' => $key,
            'original_name' => 'synthetic-original.mp4', 'mime' => 'video/mp4',
            'size_bytes' => filesize($path), 'sha256' => hash_file('sha256', $path),
            'status' => 'quarantined', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Isolated QA scanner stub proves status wiring, NOT malware-detection quality.
        // Production continues to require a real executable ClamAV scanner.
        $scannerDir = sys_get_temp_dir().'/gomovie-scanner-'.Str::random(12);
        mkdir($scannerDir, 0700);
        $scanner = $scannerDir.'/clamscan';
        file_put_contents($scanner, "#!/bin/sh\nexit 0\n");
        chmod($scanner, 0700);
        config()->set('gomovie.malware_scanner_binary', $scanner);

        try {
            app(AutomaticPublicationService::class)->process((int) $id);
            $asset = app(AutomaticPublicationService::class)->readyAsset('movie', (int) $movie->id);
            self::assertNotNull($asset);
            $this->assertDatabaseHas('media_ingests', [
                'id' => $id, 'status' => 'encoded',
                'security_scan_status' => 'clean', 'rights_review_status' => 'verified',
            ]);
            $this->assertDatabaseHas('movies', [
                'id' => $movie->id, 'status' => 'published',
            ]);
            $this->actingAs(User::factory()->create())
                ->get(route('public-hls.show', [
                    'asset' => $asset->id, 'path' => 'master.m3u8',
                ]))->assertOk();

            $retryMovie = $this->freeFilm();
            $this->rights('movie', (int) $retryMovie->id);
            $retryKey = dirname($key).'/'.Str::uuid().'.mp4';
            Storage::disk('local')->copy($key, $retryKey);
            $retryId = DB::table('media_ingests')->insertGetId([
                'ingestable_type' => 'movie', 'ingestable_id' => $retryMovie->id,
                'uploaded_by' => $admin->id, 'disk' => 'local', 'object_key' => $retryKey,
                'original_name' => 'interrupted-original.mp4', 'mime' => 'video/mp4',
                'size_bytes' => filesize($path), 'sha256' => hash_file('sha256', $path),
                'status' => 'processing', 'auto_publication_requested' => true,
                'security_scan_status' => 'clean', 'rights_review_status' => 'verified',
                'approved_by' => $admin->id, 'approved_at' => now()->subHours(3),
                'processing_started_at' => now()->subHours(3),
                'created_at' => now()->subHours(3), 'updated_at' => now(),
            ]);
            Queue::fake();
            $this->actingAs($admin)->post(route('admin.media.ingest-retry', $retryId))
                ->assertSessionHas('status', 'Processing retry queued.');
            $job = Queue::pushed(AutoPublishUploadedVideo::class)->first();
            $job->handle(app(AutomaticPublicationService::class));
            $retryAsset = app(AutomaticPublicationService::class)->readyAsset('movie', $retryMovie->id);
            self::assertNotNull($retryAsset);
            $this->temp[] = dirname($retryAsset->manifest_key);
            $this->assertDatabaseHas('media_ingests', ['id' => $retryId, 'publication_retry_token' => null]);
            $this->assertDatabaseHas('admin_audit_events', ['resource_id' => $retryId, 'action' => 'media.auto_scan_passed']);
        } finally {
            @unlink($scanner);
            @rmdir($scannerDir);
            $manifest = DB::table('media_ingests')->where('id', $id)->value('encoded_manifest_key');
            if ($manifest) $this->temp[] = dirname($manifest);
        }
    }

    public function test_retry_is_idempotent_and_can_finalize_an_existing_encoded_package(): void
    {
        $this->enable();
        config()->set('gomovie.transcoding_enabled', true);
        Queue::fake();
        [$id, $video] = $this->package();
        $video->update(['is_standalone_video' => true, 'status' => 'published', 'published_at' => now()]);
        DB::table('media_ingests')->where('id', $id)->update([
            'auto_publication_requested' => true, 'pipeline_error' => 'automatic_publication_failed',
        ]);

        $route = route('admin.media.ingest-retry', $id);
        $this->actingAs(User::factory()->create(['role' => 'editor']))->postJson($route)->assertForbidden();
        $admin = User::where('role', 'admin')->sole();
        $this->actingAs($admin)->get(route('admin.operations.index'))->assertOk()
            ->assertSee('Automatic processing failed')->assertSee(route('admin.media.index', ['ingest' => $id]));
        $this->get(route('admin.media.index', ['ingest' => $id]))->assertOk()->assertSee('Retry processing');

        $this->post($route)->assertRedirect(route('admin.media.index', ['ingest' => $id]));
        $this->post($route)->assertSessionHas('status', 'A processing retry is already queued or running.');
        Queue::assertPushed(AutoPublishUploadedVideo::class, 1);
        $this->assertDatabaseCount('admin_audit_events', 1);

        $job = Queue::pushed(AutoPublishUploadedVideo::class)->first();
        $service = app(AutomaticPublicationService::class);
        $job->handle($service);
        $job->handle($service);
        $this->assertDatabaseCount('media_assets', 1);
        $this->assertDatabaseHas('media_ingests', ['id' => $id, 'pipeline_error' => null, 'publication_retry_token' => null]);
        $asset = $service->readyAsset('movie', $video->id);
        self::assertNotNull($asset);
        $this->postJson($route)->assertConflict();
        $this->assertGuestHls($asset->id);
    }

    public function test_retry_preserves_rights_and_rejects_private_or_security_rejected_uploads(): void
    {
        $this->enable();
        config()->set('gomovie.transcoding_enabled', true);
        Queue::fake();
        [$id, $movie] = $this->package();
        $this->actingAs(User::where('role', 'admin')->sole());
        $route = route('admin.media.ingest-retry', $id);

        $query = DB::table('media_ingests')->where('id', $id);
        $query->update(['pipeline_error' => 'encoding_failed']);
        $this->postJson($route)->assertConflict();
        $query->update(['auto_publication_requested' => true, 'status' => 'rejected', 'security_scan_status' => 'infected']);
        $this->postJson($route)->assertConflict();
        $query->update(['status' => 'encoded', 'security_scan_status' => 'clean']);
        Queue::assertNothingPushed();

        $this->post($route)->assertRedirect(route('admin.media.index', ['ingest' => $id]));
        DB::table('content_rights')->where('licensable_id', $movie->id)->update(['status' => 'revoked']);
        $job = Queue::pushed(AutoPublishUploadedVideo::class)->first();
        try {
            $job->handle(app(AutomaticPublicationService::class));
            self::fail('Revoked rights must block recovery.');
        } catch (HttpException $error) {
            self::assertSame(409, $error->getStatusCode());
        }
        $this->assertDatabaseHas('media_ingests', ['id' => $id, 'publication_retry_token' => null]);
        $this->assertDatabaseCount('media_assets', 0);
    }

    private function assertGuestHls(int $asset): void
    {
        auth()->logout();
        $this->get(route('public-hls.standalone', ['asset' => $asset, 'path' => 'master.m3u8']))->assertOk();
    }

    public function test_continue_watching_is_private_profile_scoped_and_uses_the_saved_position(): void
    {
        $this->enable();
        [$id, $video] = $this->package();
        $video->update(['is_standalone_video' => true]);
        app(AutomaticPublicationService::class)->finalize($id);

        $viewer = User::factory()->create();
        $main = UserProfile::create(['user_id' => $viewer->id, 'name' => 'Main', 'slug' => 'main']);
        $other = UserProfile::create(['user_id' => $viewer->id, 'name' => 'Second', 'slug' => 'second']);
        $foreign = UserProfile::create(['user_id' => User::factory()->create()->id, 'name' => 'Foreign', 'slug' => 'foreign']);
        $watch = '/watch/movie/'.$video->slug;
        $this->get('/')->assertOk()->assertDontSee('continue-watching-heading');

        $this->actingAs($viewer)->postJson('/api/v1/user/watch-history/sync', [
            'profile_id' => $other->id, 'watchable_type' => 'movie', 'watchable_id' => $video->id,
            'progress_seconds' => 42, 'duration_seconds' => 100,
        ])->assertOk();
        $this->get('/?profile_id='.$main->id)->assertOk()->assertDontSee('42% watched');
        $this->get('/?profile_id='.$other->id)->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertSee('42% watched')->assertSee('profile_id='.$other->id);
        $this->get($watch)->assertOk()
            ->assertSee('data-profile-id="'.$other->id.'"', false)
            ->assertSee('data-resume-seconds="42"', false)->assertSee('Start over');
        $this->get($watch.'?restart=1')->assertOk()->assertSee('data-resume-seconds="0"', false);
        $this->get($watch.'?profile_id='.$foreign->id)->assertNotFound();

        DB::table('content_rights')->where('licensable_id', $video->id)->update(['status' => 'revoked']);
        $this->get('/?profile_id='.$other->id)->assertOk()->assertDontSee('42% watched');
        $this->get($watch)->assertOk()->assertSee('Currently unavailable')->assertDontSee('data-public-hls-url=');
    }

    public function test_catalog_labels_processing_and_unavailable_and_links_to_a_playable_episode(): void
    {
        $this->enable();
        config()->set('gomovie.transcoding_enabled', true);
        [$episodeUpload, $episode, $series] = $this->package('episode');
        $episode->update(['episode_number' => 2]);
        $first = Episode::create([
            'season_id' => $episode->season_id, 'episode_number' => 1,
            'title' => 'Missing Media Episode', 'status' => 'published',
        ]);
        app(AutomaticPublicationService::class)->finalize($episodeUpload);

        [$videoUpload, $video] = $this->package();
        $video->update(['is_standalone_video' => true, 'status' => 'published']);
        DB::table('media_ingests')->where('id', $videoUpload)->update([
            'status' => 'quarantined', 'auto_publication_requested' => true,
        ]);
        Movie::create(['title' => 'Metadata Only', 'slug' => 'metadata-only', 'status' => 'published']);

        $response = $this->get('/browse')->assertOk();
        $response->assertSee('View '.$series->title.' — series, Sign in to watch')
            ->assertSee('episode='.$episode->id)->assertDontSee('episode='.$first->id.'"', false)
            ->assertSee('View '.$video->title.' — video, Processing')
            ->assertSee('View Metadata Only — movie, Currently unavailable');
    }

    public function test_default_off_and_malicious_hls_paths_never_expose_private_video(): void
    {
        $this->enable();
        [$id, $movie] = $this->package();
        $asset = app(AutomaticPublicationService::class)->finalize($id);
        $this->actingAs(User::factory()->create());
        $this->get(route('public-hls.show', ['asset' => $asset, 'path' => 'original.mp4']))
            ->assertNotFound();
        $this->get(route('public-hls.show', ['asset' => $asset, 'path' => '480p/other.ts']))
            ->assertNotFound();
        config()->set('gomovie.automatic_publication_enabled', false);
        $this->get(route('public-hls.show', ['asset' => $asset, 'path' => 'master.m3u8']))
            ->assertOk();
        config()->set('gomovie.public_hls_enabled', false);
        $this->get(route('public-hls.show', ['asset' => $asset, 'path' => 'master.m3u8']))
            ->assertNotFound();
        $this->get('/watch/movie/'.$movie->slug)->assertOk()->assertDontSee('data-public-hls-url=');
    }
}
