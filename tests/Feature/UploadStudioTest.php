<?php

namespace Tests\Feature;

use App\Jobs\MirrorPrivateIngestToR2;
use App\Models\Movie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class UploadStudioTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'admin'])->save();
        return $user->fresh();
    }

    /**
     * Laravel's empty-size fake does not allocate physical bytes. This fixture
     * writes real private-upload bytes while retaining the explicit test MIME.
     */
    private function fakeVideo(string $name): UploadedFile
    {
        $file = UploadedFile::fake()->create($name, 5, 'video/mp4');
        file_put_contents($file->getRealPath(), str_repeat('x', 5120));
        return $file;
    }

    private function movie(): Movie
    {
        return Movie::create([
            'title' => 'Original Upload Studio QA',
            'slug' => 'original-upload-studio-qa',
            'status' => 'draft',
            'is_premium' => false,
        ]);
    }

    public function test_upload_studio_requires_verified_admin_and_is_simple_to_use(): void
    {
        $this->get('/upload-studio')->assertRedirect(route('login'));

        $viewer = User::factory()->create();
        $this->actingAs($viewer)->get('/upload-studio')->assertForbidden();
        $this->actingAs($viewer)->post('/upload-studio', [])->assertForbidden();

        $editor = User::factory()->create();
        $editor->forceFill(['role' => 'editor'])->save();
        $this->actingAs($editor)->get('/upload-studio')->assertForbidden();

        $movie = $this->movie();
        $this->actingAs($this->admin())
            ->get('/upload-studio')->assertOk()
            ->assertSee('Upload a video')
            ->assertSee('gm-upload-studio-form')
            ->assertSee('gm-upload-progress')
            ->assertSee('name="type"', false)
            ->assertSee('name="title_id"', false)
            ->assertSee('name="video"', false)
            ->assertSee('const payload = new FormData(form);', false)
            ->assertDontSee('MANAGEMENT STUDIO')
            ->assertDontSee('Cloudflare R2')
            ->assertDontSee('Review this upload')
            ->assertSee($movie->title);
        $this->get('/dashboard/upload-studio')->assertRedirect(route('upload-studio.index'));
        $this->post('/upload-studio', [])->assertSessionHasErrors(['type', 'title_id', 'video']);
    }

    public function test_upload_form_serializes_fields_before_disabling_controls(): void
    {
        $view = file_get_contents(resource_path('views/admin/upload-studio.blade.php'));
        self::assertIsString($view);
        self::assertStringContainsString('name="type"', $view);
        self::assertStringContainsString('name="title_id"', $view);
        self::assertStringContainsString('name="video"', $view);
        $serialized = strpos($view, 'const payload = new FormData(form);');
        $disabled = strpos($view, 'busy(true);');
        self::assertNotFalse($serialized);
        self::assertNotFalse($disabled);
        self::assertLessThan($disabled, $serialized,
            'The multipart upload must be captured before inputs are disabled.');
    }

    public function test_upload_is_private_quarantined_and_does_not_grant_media_rights(): void
    {
        Storage::fake('local');
        config()->set('gomovie.r2_enabled', false);
        $movie = $this->movie();
        $this->actingAs($this->admin());

        $this->postJson('/upload-studio', [
            'type' => 'movie',
            'title_id' => $movie->id,
            'video' => $this->fakeVideo('licensed-qa.mp4'),
        ])->assertCreated()->assertJsonPath('ok', true)
            ->assertJsonPath('library_url', route('admin.media.index'))
            ->assertJsonMissingPath('review_url');

        $upload = DB::table('media_ingests')->first();
        self::assertNotNull($upload);
        self::assertSame('quarantined', $upload->status);
        self::assertSame('pending', $upload->security_scan_status);
        self::assertSame('pending', $upload->rights_review_status);
        self::assertSame('local_only', $upload->cloud_status);
        self::assertSame('local', $upload->disk);
        self::assertMatchesRegularExpression(
            '#\Aingests/[0-9]{4}/[0-9]{2}/[0-9a-f-]{36}\.mp4\z#',
            $upload->object_key
        );
        Storage::disk('local')->assertExists($upload->object_key);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'media.private_ingest_staged']);
        $this->assertDatabaseCount('content_rights', 0);
        $this->assertDatabaseCount('media_assets', 0);
        self::assertFalse(config('gomovie.edge_enabled'));
        self::assertFalse(config('gomovie.transcoding_enabled'));
    }

    public function test_upload_checks_target_and_file_type_without_writing_records(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());
        $this->postJson('/upload-studio', [
            'type' => 'movie', 'title_id' => 99999,
            'video' => UploadedFile::fake()->create('not-video.txt', 1, 'text/plain'),
        ])->assertUnprocessable()->assertJsonValidationErrors('video');
        $this->assertDatabaseCount('media_ingests', 0);
    }

    public function test_old_manage_page_redirects_and_approval_actions_do_not_exist(): void
    {
        Storage::fake('local');
        $movie = $this->movie();
        $this->actingAs($this->admin());
        $this->postJson('/upload-studio', [
            'type' => 'movie',
            'title_id' => $movie->id,
            'video' => $this->fakeVideo('simple-upload.mp4'),
        ])->assertCreated()->assertJsonMissingPath('review_url');
        $id = (int) DB::table('media_ingests')->value('id');

        $this->get('/dashboard/upload-studio/'.$id)
            ->assertRedirect(route('admin.media.index'));
        $this->post('/dashboard/upload-studio/'.$id.'/approve', [
            'rights_reference' => 'TEST-RIGHTS-1', 'rights_confirmed' => '1',
        ])->assertNotFound();
        $this->post('/dashboard/upload-studio/'.$id.'/reject', [
            'reason' => 'No additional approval step should exist.',
        ])->assertNotFound();

        $this->get('/dashboard/media')
            ->assertOk()->assertSee('Uploaded videos')
            ->assertSee('simple-upload.mp4')
            ->assertSee('Saved privately')
            ->assertDontSee('Check and authorize')
            ->assertDontSee('Manage →');

        $this->assertDatabaseHas('media_ingests', ['id' => $id, 'status' => 'quarantined']);
        $this->assertDatabaseCount('content_rights', 0);
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_editor_cannot_open_legacy_review_link(): void
    {
        $editor = User::factory()->create();
        $editor->forceFill(['role' => 'editor'])->save();
        $this->actingAs($editor)->get('/dashboard/upload-studio/1')->assertForbidden();
    }

    public function test_r2_mirror_is_queued_isolated_and_remains_private(): void
    {
        Storage::fake('r2');
        Queue::fake();
        config()->set('gomovie.r2_enabled', true);
        config()->set('gomovie.r2_endpoint', 'https://'.str_repeat('a', 32).'.r2.cloudflarestorage.com');
        config()->set('gomovie.r2_bucket', 'qa-bucket');
        config()->set('filesystems.disks.r2.key', 'qa-access-key');
        config()->set('filesystems.disks.r2.secret', 'qa-secret-key');

        $movie = $this->movie();
        $this->actingAs($this->admin());
        $this->postJson('/upload-studio', [
            'type' => 'movie', 'title_id' => $movie->id,
            'video' => $this->fakeVideo('cloud.mp4'),
        ])->assertCreated();

        $ingest = DB::table('media_ingests')->first();
        self::assertSame('pending', $ingest->cloud_status);
        self::assertSame((int) $ingest->size_bytes,
            (int) Storage::disk('local')->size($ingest->object_key),
            'Local source size must match the recorded ingest size.');
        Queue::assertPushed(MirrorPrivateIngestToR2::class, fn ($job) =>
            $job->connection === 'database_media' && $job->queue === 'gomovie-media');
        $this->assertDatabaseCount('media_assets', 0);
        try {
            (new MirrorPrivateIngestToR2((int) $ingest->id))->handle();
            $updated = DB::table('media_ingests')->where('id', $ingest->id)->first();
            self::assertSame('ready', $updated->cloud_status);
            self::assertSame('r2', $updated->cloud_disk);
            Storage::disk('r2')->assertExists($updated->cloud_object_key);
            self::assertSame('private', Storage::disk('r2')->visibility($updated->cloud_object_key));
            $this->assertDatabaseCount('media_assets', 0);
            self::assertFalse(config('gomovie.edge_enabled'));
        } finally {
            Storage::disk('local')->delete($ingest->object_key);
            config()->set('gomovie.r2_enabled', false);
        }
    }
}
