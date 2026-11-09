<?php

namespace Tests\Feature;

use App\Jobs\AutoPublishUploadedVideo;
use App\Models\Movie;
use App\Models\Series;
use App\Models\Season;
use App\Models\Episode;
use App\Models\User;
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
        $this->get($manifest)->assertOk()->assertHeader('Content-Type', 'application/vnd.apple.mpegurl');
        $this->get(route('public-hls.show', ['asset' => $asset, 'path' => '480p/segment_00000.ts']))
            ->assertOk()->assertHeader('Content-Type', 'video/mp2t');
        $this->get('/watch/movie/'.$movie->slug)->assertOk()
            ->assertSee('data-public-hls-url=', false)->assertSee('Watch now');
        $this->assertDatabaseHas('media_ingests', ['id' => $id, 'status' => 'encoded']);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'media.auto_publication_ready']);

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
        $this->get($url)->assertOk()->assertSee('data-public-hls-url=');

        $parent->forceFill(['is_premium' => true])->save();
        $this->get(route('public-hls.show', ['asset' => $asset, 'path' => 'master.m3u8']))
            ->assertNotFound();
        $this->get($url)->assertOk()->assertDontSee('data-public-hls-url=');
    }

    public function test_default_off_and_malicious_hls_paths_never_expose_private_video(): void
    {
        $this->enable();
        [$id, $movie] = $this->package();
        $asset = app(AutomaticPublicationService::class)->finalize($id);
        $this->get(route('public-hls.show', ['asset' => $asset, 'path' => 'original.mp4']))
            ->assertNotFound();
        $this->get(route('public-hls.show', ['asset' => $asset, 'path' => '480p/other.ts']))
            ->assertNotFound();
        config()->set('gomovie.public_hls_enabled', false);
        $this->get(route('public-hls.show', ['asset' => $asset, 'path' => 'master.m3u8']))
            ->assertNotFound();
        $this->get('/watch/movie/'.$movie->slug)->assertOk()->assertDontSee('data-public-hls-url=');
    }
}
