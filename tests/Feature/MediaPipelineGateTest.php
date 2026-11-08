<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\User;
use App\Services\Media\TranscodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
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

    public function test_encoding_service_refuses_disabled_pipeline_before_any_media_access(): void
    {
        config()->set('gomovie.transcoding_enabled', false);
        try {
            app(TranscodingService::class)->encode(999);
            self::fail('Encoding was allowed with the kill switch off.');
        } catch (HttpException $exception) {
            self::assertSame(503, $exception->getStatusCode());
        }
    }

    public function test_quarantined_video_cannot_pass_encoding_gate(): void
    {
        config()->set('gomovie.transcoding_enabled', true);
        $user = User::factory()->create();
        $movie = Movie::create(['title' => 'Scan Pending', 'slug' => 'scan-pending']);
        $id = DB::table('media_ingests')->insertGetId([
            'ingestable_type' => 'movie', 'ingestable_id' => $movie->id,
            'uploaded_by' => $user->id, 'disk' => 'local',
            'object_key' => 'ingests/2026/11/test.mp4',
            'original_name' => 'test.mp4', 'mime' => 'video/mp4',
            'size_bytes' => 256, 'sha256' => str_repeat('f', 64),
            'status' => 'quarantined', 'created_at' => now(), 'updated_at' => now(),
        ]);
        try {
            app(TranscodingService::class)->encode($id);
            self::fail('Quarantined video bypassed encoding authorization.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->getStatusCode());
        }
        $this->assertDatabaseHas('media_ingests', ['id' => $id, 'status' => 'quarantined']);
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_operator_generated_synthetic_clip_can_be_encoded_to_private_hls(): void
    {
        $ffmpeg = '/usr/bin/ffmpeg';
        $ffprobe = '/usr/bin/ffprobe';
        if (!is_executable($ffmpeg) || !is_executable($ffprobe)) {
            if (getenv('GOMOVIE_REQUIRE_FFMPEG_SMOKE') === '1') {
                self::fail('FFmpeg and FFprobe must be installed for the required synthetic encoding smoke test.');
            }
            $this->markTestSkipped('FFmpeg and FFprobe not installed in this runner.');
        }

        $disk = Storage::disk('local');
        $key = 'ingests/2026/11/'.(string) Str::uuid().'.mp4';
        $disk->makeDirectory(dirname($key));
        $absolute = $disk->path($key);
        $generate = new Process([
            $ffmpeg,'-hide_banner','-loglevel','error','-nostdin','-y',
            '-f','lavfi','-i','testsrc2=duration=1:size=854x480:rate=25',
            '-f','lavfi','-i','sine=frequency=440:duration=1',
            '-c:v','libx264','-preset','ultrafast','-c:a','aac','-shortest',
            $absolute,
        ]);
        $generate->setTimeout(90);
        $generate->run();
        self::assertTrue($generate->isSuccessful(), 'Synthetic media creation failed.');

        $manifest = null;
        try {
            $user = User::factory()->create();
            $movie = Movie::create(['title'=>'Synthetic QA Film','slug'=>'synthetic-qa-film',
                'status'=>'published','is_premium'=>false]);
            DB::table('content_rights')->insert([
                'licensable_type'=>'movie','licensable_id'=>$movie->id,
                'status'=>'active','territories'=>null,'created_at'=>now(),'updated_at'=>now(),
            ]);
            $id = DB::table('media_ingests')->insertGetId([
                'ingestable_type'=>'movie','ingestable_id'=>$movie->id,
                'uploaded_by'=>$user->id,'approved_by'=>$user->id,
                'approved_at'=>now(),'security_scan_status'=>'clean',
                'rights_review_status'=>'verified','disk'=>'local',
                'object_key'=>$key,'original_name'=>'synthetic-qa.mp4',
                'mime'=>'video/mp4','size_bytes'=>filesize($absolute),
                'sha256'=>hash_file('sha256',$absolute),'status'=>'approved',
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            config()->set('gomovie.transcoding_enabled',true);
            config()->set('gomovie.ffmpeg_binary',$ffmpeg);
            config()->set('gomovie.ffprobe_binary',$ffprobe);
            $manifest = app(TranscodingService::class)->encode($id);
            self::assertTrue($disk->exists($manifest));
            self::assertStringContainsString('480p/index.m3u8',$disk->get($manifest));
            self::assertTrue($disk->exists(dirname($manifest).'/480p/index.m3u8'));
            $this->assertDatabaseHas('media_ingests',[
                'id'=>$id,'status'=>'encoded','encoded_manifest_key'=>$manifest,
            ]);
            $this->assertDatabaseCount('media_assets',0);
        } finally {
            $disk->delete($key);
            if ($manifest) $disk->deleteDirectory(dirname($manifest));
        }
    }
}
