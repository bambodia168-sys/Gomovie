<?php

namespace Tests\Feature;

use App\Jobs\AutoPublishUploadedVideo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class QuickUploadTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'admin', 'is_active' => true,
            'email_verified_at' => now()])->save();
        return $user->fresh();
    }

    private function video(): UploadedFile
    {
        $file = UploadedFile::fake()->create('my-original.mp4', 3, 'video/mp4');
        file_put_contents($file->getRealPath(), str_repeat('a', 3200));
        return $file;
    }

    private function enableFree(): void
    {
        config()->set('gomovie.automatic_publication_enabled', true);
        config()->set('gomovie.public_hls_enabled', true);
        config()->set('gomovie.transcoding_enabled', true);
        config()->set('gomovie.r2_enabled', false);
    }

    public function test_quick_upload_is_default_and_advanced_editor_remains_optional(): void
    {
        $this->get('/upload-studio')->assertRedirect(route('login'));
        $viewer = User::factory()->create();
        $this->actingAs($viewer)->get('/upload-studio')->assertForbidden();
        $this->actingAs($viewer)->postJson('/upload-studio/quick', [])->assertForbidden();

        $this->actingAs($this->admin())->get('/upload-studio')
            ->assertOk()
            ->assertSee('Upload a video')
            ->assertSee('quick-upload-form')
            ->assertSee('name="title"', false)
            ->assertSee('name="rights_confirmed"', false)
            ->assertSee('Paid video · Draft')
            ->assertSee('Advanced editor')
            ->assertDontSee('Details &amp; Meta', false)
            ->assertDontSee('Catalog Access');

        $this->get('/upload-studio/advanced')->assertOk()
            ->assertSee('gm-upload-studio-form');
    }

    public function test_upload_studio_2_first_milestone_has_safe_transfer_controls_and_private_receipt(): void
    {
        Storage::fake('local');
        Queue::fake();
        config()->set('gomovie.automatic_publication_enabled', false);
        config()->set('gomovie.public_hls_enabled', false);
        config()->set('gomovie.r2_enabled', false);

        $this->actingAs($this->admin())->get('/upload-studio')->assertOk()
            ->assertSee('id="upload-cancel"', false)
            ->assertSee('id="upload-retry"', false)
            ->assertSee('id="upload-review"', false)
            ->assertSee('role="progressbar"', false)
            ->assertSee('role="status" aria-live="polite"', false)
            ->assertSee('Private safety checks')
            ->assertSee('Your original stays private.')
            ->assertSee('request.onabort', false)
            ->assertSee('uncertainFailure(', false)
            ->assertSee('retryableFailure(', false)
            ->assertSee('form.requestSubmit()', false)
            ->assertDontSee('No manual approval');

        $result = $this->postJson('/upload-studio/quick', [
            'title' => 'Upload Studio 2 QA Private',
            'access' => 'private',
            'rights_confirmed' => true,
            'video' => $this->video(),
        ]);
        $result->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('will_publish_automatically', false);
        $id = $result->json('upload_id');
        $ingest = DB::table('media_ingests')->where('id', $id)->sole();
        self::assertSame('quarantined', $ingest->status);
        self::assertSame('pending', $ingest->security_scan_status);
        self::assertSame('pending', $ingest->rights_review_status);
        self::assertSame('local', $ingest->disk);
        Storage::disk('local')->assertExists($ingest->object_key);
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertDatabaseCount('content_rights', 0);
        Queue::assertNotPushed(AutoPublishUploadedVideo::class);

        $this->getJson(route('upload-studio.status', ['ingest' => $id]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'quarantined')
            ->assertJsonPath('public', false)
            ->assertJsonPath('watch_url', null);

        $other = $this->admin();
        $this->actingAs($other)->getJson(route('upload-studio.status', ['ingest' => $id]))
            ->assertNotFound();
    }

    public function test_public_original_creates_its_own_catalog_title_and_uploader_attestation(): void
    {
        Storage::fake('local');
        Queue::fake();
        $this->enableFree();
        $this->actingAs($this->admin());

        $result = $this->postJson('/upload-studio/quick', [
            'title' => 'My Original Short Film',
            'description' => 'A movie we made ourselves.',
            'access' => 'free',
            'rights_confirmed' => '1',
            'video' => $this->video(),
        ]);
        $result->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('access', 'free')
            ->assertJsonPath('will_publish_automatically', true);

        $movie = DB::table('movies')->sole();
        self::assertSame('My Original Short Film', $movie->title);
        self::assertFalse((bool) $movie->is_premium);
        self::assertSame('draft', $movie->status);
        self::assertStringStartsWith('my-original-short-film-', $movie->slug);
        $rights = DB::table('content_rights')->sole();
        self::assertSame('movie', $rights->licensable_type);
        self::assertSame((int) $movie->id, (int) $rights->licensable_id);
        self::assertSame('active', $rights->status);
        self::assertNull($rights->territories);
        self::assertStringStartsWith('OWNER-DECLARED-', $rights->rights_reference);
        $this->assertDatabaseHas('admin_audit_events', [
            'action' => 'media.quick_upload_attested',
            'resource_type' => 'movie',
            'resource_id' => $movie->id,
        ]);
        $this->assertDatabaseHas('media_ingests', [
            'ingestable_type' => 'movie',
            'ingestable_id' => $movie->id,
            'status' => 'quarantined',
        ]);
        Queue::assertPushed(AutoPublishUploadedVideo::class, 1);
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_private_video_requires_no_rights_record_and_does_not_launch_public_worker(): void
    {
        Storage::fake('local');
        Queue::fake();
        $this->enableFree();
        $this->actingAs($this->admin());
        $this->postJson('/upload-studio/quick', [
            'title' => 'My Personal Clip',
            'access' => 'private',
            'rights_confirmed' => true,
            'video' => $this->video(),
        ])->assertCreated()
            ->assertJsonPath('will_publish_automatically', false)
            ->assertJsonPath('access', 'private');

        $this->assertDatabaseCount('movies', 1);
        $this->assertDatabaseCount('media_ingests', 1);
        $this->assertDatabaseCount('content_rights', 0);
        Queue::assertNotPushed(AutoPublishUploadedVideo::class);
        $this->assertDatabaseHas('movies', ['title' => 'My Personal Clip', 'status' => 'draft',
            'is_premium' => false]);
    }

    public function test_paid_video_is_draft_only_and_never_claims_payment_is_enabled(): void
    {
        Storage::fake('local');
        Queue::fake();
        $this->enableFree();
        $this->actingAs($this->admin());
        $this->postJson('/upload-studio/quick', [
            'title' => 'Paid Original Documentary',
            'access' => 'paid',
            'rights_confirmed' => 'on',
            'video' => $this->video(),
        ])->assertCreated()
            ->assertJsonPath('access', 'paid')
            ->assertJsonPath('will_publish_automatically', false);

        $this->assertDatabaseHas('movies', [
            'title' => 'Paid Original Documentary',
            'is_premium' => true, 'status' => 'draft',
        ]);
        $this->assertDatabaseCount('content_rights', 0);
        $this->assertDatabaseCount('media_assets', 0);
        Queue::assertNotPushed(AutoPublishUploadedVideo::class);
    }

    public function test_missing_permission_checkbox_is_rejected_before_any_file_or_title_is_created(): void
    {
        Storage::fake('local');
        $this->enableFree();
        $this->actingAs($this->admin());
        $this->postJson('/upload-studio/quick', [
            'title' => 'Unattested Video',
            'access' => 'free',
            'video' => $this->video(),
        ])->assertUnprocessable()->assertJsonValidationErrors('rights_confirmed');
        $this->assertDatabaseCount('movies', 0);
        $this->assertDatabaseCount('media_ingests', 0);
        $this->assertDatabaseCount('content_rights', 0);
    }

    public function test_free_publishing_is_blocked_when_host_has_not_enabled_background_processing(): void
    {
        Storage::fake('local');
        config()->set('gomovie.automatic_publication_enabled', false);
        config()->set('gomovie.public_hls_enabled', false);
        config()->set('gomovie.transcoding_enabled', false);
        $this->actingAs($this->admin());
        $this->postJson('/upload-studio/quick', [
            'title' => 'Not Ready For Public Playback',
            'access' => 'free',
            'rights_confirmed' => true,
            'video' => $this->video(),
        ])->assertStatus(409);
        $this->assertDatabaseCount('movies', 0);
        $this->assertDatabaseCount('content_rights', 0);
    }

    public function test_quick_upload_cannot_modify_an_existing_movie_or_forge_id(): void
    {
        Storage::fake('local');
        Queue::fake();
        $this->enableFree();
        $this->actingAs($this->admin());
        $this->postJson('/upload-studio/quick', [
            'title' => 'Not Coco',
            'title_id' => 1,
            'type' => 'movie',
            'access' => 'private',
            'rights_confirmed' => 1,
            'video' => $this->video(),
        ])->assertCreated();
        $this->assertDatabaseHas('movies', ['title'=>'Not Coco', 'status'=>'draft']);
        $this->assertDatabaseCount('content_rights', 0);
        $this->assertDatabaseCount('movies', 1);
    }
}
