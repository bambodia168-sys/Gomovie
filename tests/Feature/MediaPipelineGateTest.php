<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\User;
use App\Services\Media\TranscodeGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MediaPipelineGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_pipeline_preflight_reports_disabled_by_default(): void
    {
        config()->set('gomovie.transcoding_enabled', false);
        self::assertSame(1, Artisan::call('gomovie:media-preflight', ['--json' => true]));
        self::assertStringContainsString('"transcoding_enabled":false', Artisan::output());
    }

    public function test_quarantined_video_can_never_pass_the_encoding_gate(): void
    {
        config()->set('gomovie.transcoding_enabled', true);
        $user = User::factory()->create();
        $movie = Movie::create(['title' => 'Scan Pending', 'slug' => 'scan-pending']);
        $ingest = DB::table('media_ingests')->insertGetId([
            'ingestable_type' => 'movie', 'ingestable_id' => $movie->id,
            'uploaded_by' => $user->id, 'disk' => 'local',
            'object_key' => 'ingests/2026/11/test.mp4',
            'original_name' => 'test.mp4', 'mime' => 'video/mp4',
            'size_bytes' => 256, 'sha256' => str_repeat('f', 64),
            'status' => 'quarantined',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        try {
            app(TranscodeGate::class)->validate($ingest);
            self::fail('Quarantined video bypassed encoding gate.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
        }
        $this->assertDatabaseHas('media_ingests', ['id' => $ingest, 'status' => 'quarantined']);
        $this->assertDatabaseCount('media_assets', 0);
    }
}
