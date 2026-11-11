<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\User;
use App\Services\Media\AutomaticPublicationService;
use App\Services\Media\IntakeReviewService;
use App\Services\Rights\GlobalRights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class IndependentPublicationGatesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $actor = User::factory()->create();
        $actor->forceFill(['role' => 'admin', 'is_active' => true,
            'email_verified_at' => now()])->save();
        return $actor->fresh();
    }

    public function test_free_upload_creates_pending_declaration_and_no_job_even_if_automatic_flags_are_on(): void
    {
        Storage::fake('local');
        Queue::fake();
        config()->set('gomovie.automatic_publication_enabled', true);
        config()->set('gomovie.public_hls_enabled', true);
        config()->set('gomovie.transcoding_enabled', true);
        $video = UploadedFile::fake()->create('licensed.mp4', 3, 'video/mp4');
        file_put_contents($video->getRealPath(), str_repeat('X', 3200));
        $response = $this->actingAs($this->admin())->postJson('/upload-studio/quick', [
            'title' => 'Original awaiting independent review',
            'access' => 'free',
            'rights_confirmed' => true,
            'video' => $video,
        ]);
        $response->assertCreated()->assertJsonPath('will_publish_automatically', false);
        $film = Movie::query()->sole();
        $this->assertDatabaseHas('content_rights', [
            'licensable_type' => 'movie', 'licensable_id' => $film->id,
            'status' => 'pending',
        ]);
        self::assertFalse(app(GlobalRights::class)->allows('movie', (int) $film->id));
        Queue::assertNotPushed(\App\Jobs\AutoPublishUploadedVideo::class);
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_legacy_self_declared_active_license_is_not_playback_eligible(): void
    {
        $film = Movie::create([
            'title'=>'Legacy self declaration QA', 'slug'=>'legacy-owner-declared-qa',
            'status'=>'published', 'is_premium'=>false,
        ]);
        DB::table('content_rights')->insert([
            'licensable_type' => 'movie', 'licensable_id' => $film->id,
            'rights_reference' => 'OWNER-DECLARED-1234-SYNTHETIC',
            'status' => 'active', 'territories' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        self::assertFalse(app(GlobalRights::class)->allows('movie', (int) $film->id));
        config()->set('gomovie.public_hls_enabled', true);
        self::assertNull(app(AutomaticPublicationService::class)->readyAsset('movie', (int) $film->id));
    }

    public function test_owner_cannot_approve_own_quarantine_and_attestation_cannot_create_rights(): void
    {
        $operator = $this->admin();
        $film = Movie::create([
            'title'=>'Self approval probe','slug'=>'self-approval-probe','status'=>'draft',
            'is_premium'=>false,
        ]);
        $ingestId = DB::table('media_ingests')->insertGetId([
            'ingestable_type'=>'movie', 'ingestable_id'=>$film->id,
            'uploaded_by'=>$operator->id, 'status'=>'quarantined',
            'disk'=>'local', 'object_key'=>'ingests/2026/11/00000000-0000-4000-8000-000000000000.mp4',
            'original_name'=>'probe.mp4','mime'=>'video/mp4',
            'size_bytes'=>100,'sha256'=>str_repeat('a',64),
            'created_at'=>now(), 'updated_at'=>now(),
        ]);
        try {
            app(IntakeReviewService::class)->approve($ingestId,$operator,
                'QA-LEGAL-REFERENCE-2026',null);
            self::fail('Self approval must not be possible.');
        } catch (HttpException $e) {
            self::assertSame(403,$e->getStatusCode());
        }
        try {
            app(IntakeReviewService::class)->approve($ingestId,$operator,
                'OWNER-DECLARED-1234-SYNTHETIC',null);
            self::fail('Uploader declaration must not be approved as rights evidence.');
        } catch (HttpException $e) {
            self::assertSame(403,$e->getStatusCode());
        }
        $this->assertDatabaseCount('content_rights',0);
        $this->assertDatabaseHas('media_ingests',['id'=>$ingestId,'status'=>'quarantined']);
        $this->assertDatabaseCount('media_assets',0);
    }
}
