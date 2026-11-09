<?php

namespace Tests\Feature;

use App\Jobs\MirrorPrivateIngestToR2;
use App\Jobs\TranscodeApprovedIngest;
use App\Models\Movie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        ])->assertCreated()->assertJsonPath('ok', true);

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

    public function test_review_requires_scanner_and_prevents_unverified_rights_records(): void
    {
        Storage::fake('local');
        $movie = $this->movie();
        $this->actingAs($this->admin());
        $this->postJson('/upload-studio', [
            'type' => 'movie', 'title_id' => $movie->id,
            'video' => $this->fakeVideo('review.mp4'),
        ])->assertCreated();

        $id = (int) DB::table('media_ingests')->latest('id')->value('id');
        config()->set('gomovie.malware_scanner_binary', '/nonexistent/clamscan');
        $this->post('/dashboard/upload-studio/'.$id.'/approve', [
            'rights_reference' => 'QA-TEST-RIGHTS-1', 'rights_confirmed' => 1,
        ])->assertStatus(503);
        $this->assertDatabaseHas('media_ingests', ['id' => $id, 'status' => 'quarantined']);
        $this->assertDatabaseCount('content_rights', 0);
        $this->get('/dashboard/upload-studio/'.$id)->assertOk()
            ->assertSee('Review video')->assertSee('ClamAV');
    }

    public function test_operator_attested_review_requires_real_scanner_success_and_stays_unpublished(): void
    {
        // This test uses a self-contained test-only executable named clamscan.
        // It must never be configured on a real server as a malware scanner.
        $tmp = sys_get_temp_dir().'/gomovie-clam-test-'.Str::uuid();
        mkdir($tmp, 0700, true);
        file_put_contents($tmp.'/clamscan', "#!/bin/sh\nexit 0\n");
        chmod($tmp.'/clamscan', 0700);

        $movie = $this->movie();
        $this->actingAs($this->admin());
        try {
            $this->postJson('/upload-studio', [
                'type' => 'movie', 'title_id' => $movie->id,
                'video' => $this->fakeVideo('approval.mp4'),
            ])->assertCreated();
            $upload = DB::table('media_ingests')->first();
            $disk = Storage::disk('local');
            try {
                config()->set('gomovie.malware_scanner_binary', $tmp.'/clamscan');
                $this->post('/dashboard/upload-studio/'.$upload->id.'/approve', [
                    'rights_reference' => 'QA-TEST-RIGHTS-1', 'rights_confirmed' => 1,
                ])->assertRedirect();
                $this->assertDatabaseHas('media_ingests', [
                    'id' => $upload->id, 'status' => 'approved', 'security_scan_status' => 'clean',
                    'rights_review_status' => 'verified',
                ]);
                $this->assertDatabaseHas('content_rights', [
                    'licensable_type' => 'movie', 'licensable_id' => $movie->id,
                    'rights_reference' => 'QA-TEST-RIGHTS-1', 'status' => 'active',
                ]);
                $this->assertDatabaseCount('media_assets', 0);
                self::assertFalse(config('gomovie.edge_enabled'));
                $this->assertDatabaseHas('admin_audit_events', ['action' => 'media.intake_approved']);
                // The kill switch remains off: approval does not queue encoding.
                self::assertFalse(config('gomovie.transcoding_enabled'));
            } finally {
                $disk->delete($upload->object_key);
            }
        } finally {
            @unlink($tmp.'/clamscan');
            @rmdir($tmp);
        }
    }


    public function test_approved_video_queues_private_encoding_automatically_only_when_enabled(): void
    {
        Queue::fake();

        $scannerFolder = sys_get_temp_dir().'/gomovie-clam-queue-'.Str::uuid();
        mkdir($scannerFolder, 0700, true);
        file_put_contents($scannerFolder.'/clamscan', "#!/bin/sh\nexit 0\n");
        chmod($scannerFolder.'/clamscan', 0700);

        $this->actingAs($this->admin());
        $movie = $this->movie();

        try {
            $this->postJson('/upload-studio', [
                'type' => 'movie',
                'title_id' => $movie->id,
                'video' => $this->fakeVideo('queued-original.mp4'),
            ])->assertCreated();
            $ingest = DB::table('media_ingests')->first();
            config()->set('gomovie.malware_scanner_binary', $scannerFolder.'/clamscan');
            config()->set('gomovie.transcoding_enabled', true);

            $this->post('/dashboard/upload-studio/'.$ingest->id.'/approve', [
                'rights_reference' => 'TEST-PRIVATE-QA-2026',
                'rights_confirmed' => 1,
            ])->assertRedirect();

            Queue::assertPushed(TranscodeApprovedIngest::class, fn ($job) =>
                $job->ingestId === (int) $ingest->id
                && $job->connection === 'database_media'
                && $job->queue === 'gomovie-media'
            );
            $this->assertDatabaseHas('media_ingests', [
                'id' => $ingest->id, 'status' => 'approved',
            ]);
            $this->assertDatabaseCount('media_assets', 0);
            self::assertFalse(config('gomovie.edge_enabled'));
        } finally {
            if (isset($ingest) && $ingest && is_string($ingest->object_key)) {
                Storage::disk('local')->delete($ingest->object_key);
            }
            @unlink($scannerFolder.'/clamscan');
            @rmdir($scannerFolder);
        }
    }

    public function test_admin_can_reject_upload_without_making_it_playable(): void
    {
        Storage::fake('local');
        $movie = $this->movie();
        $this->actingAs($this->admin());
        $this->postJson('/upload-studio', [
            'type' => 'movie', 'title_id' => $movie->id,
            'video' => $this->fakeVideo('reject.mp4'),
        ])->assertCreated();

        $id = (int) DB::table('media_ingests')->latest('id')->value('id');
        $this->post('/dashboard/upload-studio/'.$id.'/reject', [
            'reason' => 'Wrong uploaded video for this title.',
        ])->assertRedirect();
        $this->assertDatabaseHas('media_ingests', ['id' => $id, 'status' => 'rejected']);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'media.intake_rejected']);
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertDatabaseCount('content_rights', 0);
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
