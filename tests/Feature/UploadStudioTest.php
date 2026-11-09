<?php

namespace Tests\Feature;

use App\Jobs\MirrorPrivateIngestToR2;
use App\Models\Movie;
use App\Models\Episode;
use App\Models\Season;
use App\Models\Series;
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

    public function test_sample_inspired_studio_has_real_optional_steps_without_fake_launch(): void
    {
        $this->actingAs($this->admin());
        $this->movie();
        $this->get('/upload-studio')->assertOk()
            ->assertSee('File &amp; Ingest', false)
            ->assertSee('Details &amp; Meta', false)
            ->assertSee('Poster &amp; Assets', false)
            ->assertSee('Catalog Access')
            ->assertSee('Finish')
            ->assertSee('gm-save-details')
            ->assertSee('gm-save-artwork')
            ->assertSee('gm-save-access')
            ->assertDontSee('Launch to Gomovie')
            ->assertDontSee('0 Infringements Found')
            ->assertDontSee('256 GB accepted');
    }

    public function test_private_upload_optional_details_artwork_access_and_resume_are_real(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $movie = $this->movie();
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->postJson('/upload-studio', [
            'type'=>'movie','title_id'=>$movie->id,
            'video'=>$this->fakeVideo('real-studio.mp4'),
        ])->assertCreated()->assertJsonPath('ok', true);

        $id = (int) DB::table('media_ingests')->value('id');
        $this->assertDatabaseHas('media_ingests', ['id'=>$id,'status'=>'quarantined']);
        $this->get('/upload-studio?upload='.$id)->assertOk()
            ->assertSee('data-resume-id="'.$id.'"', false);

        $this->patchJson('/upload-studio/'.$id.'/details', [
            'title'=>'Updated Draft Title',
            'overview'=>'A real synopsis saved through the Laravel backend.',
            'release_year'=>2026,
            'runtime_minutes'=>91,
        ])->assertOk()->assertJsonPath('title','Updated Draft Title');
        $this->assertDatabaseHas('movies', [
            'id'=>$movie->id,'title'=>'Updated Draft Title','release_year'=>2026,
            'runtime_minutes'=>91,'status'=>'draft',
        ]);
        self::assertSame('original-upload-studio-qa', $movie->fresh()->slug);

        $this->patchJson('/upload-studio/'.$id.'/access', [
            'is_premium'=>true,
        ])->assertOk()->assertJsonPath('is_premium',true);
        $this->assertDatabaseHas('movies',['id'=>$movie->id,'is_premium'=>1]);

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9WlSxggAAAAASUVORK5CYII=',
            true
        );
        $path = tempnam(sys_get_temp_dir(), 'gomovie-art-');
        file_put_contents($path, $png);
        try {
            $this->postJson('/upload-studio/'.$id.'/artwork', [
                'slot'=>'poster',
                'artwork'=>new UploadedFile($path, 'poster.png', 'image/png', null, true),
            ])->assertOk()->assertJsonPath('slot','poster');
        } finally {
            @unlink($path);
        }
        $movie->refresh();
        self::assertNotEmpty($movie->poster_path);
        Storage::disk('public')->assertExists($movie->poster_path);

        $this->get('/upload-studio/'.$id.'/status')->assertOk()
            ->assertJsonPath('status','quarantined')
            ->assertJsonPath('catalog_status','draft');
        $this->get('/dashboard/media')->assertOk()
            ->assertSee('Edit upload')
            ->assertSee('real-studio.mp4');
        $this->assertDatabaseCount('content_rights',0);
        $this->assertDatabaseCount('media_assets',0);
        $this->assertDatabaseHas('media_ingests',['id'=>$id,'status'=>'quarantined']);
    }

    public function test_optional_studio_changes_are_owned_and_never_publish_media(): void
    {
        Storage::fake('local');
        $movie = $this->movie();
        $owner = $this->admin();
        $this->actingAs($owner)->postJson('/upload-studio', [
            'type'=>'movie','title_id'=>$movie->id,
            'video'=>$this->fakeVideo('private-owned.mp4'),
        ])->assertCreated();
        $id=(int) DB::table('media_ingests')->value('id');
        $other=$this->admin();
        $this->actingAs($other)->get('/upload-studio?upload='.$id)->assertNotFound();
        $this->actingAs($other)->get('/upload-studio/'.$id.'/status')->assertNotFound();
        $this->actingAs($other)->patchJson('/upload-studio/'.$id.'/details',[
            'title'=>'Unauthorized change',
        ])->assertNotFound();
        $this->actingAs($other)->patchJson('/upload-studio/'.$id.'/access',[
            'is_premium'=>true,
        ])->assertNotFound();

        $movie->forceFill(['status'=>'published','published_at'=>now()])->save();
        $this->actingAs($owner)->patchJson('/upload-studio/'.$id.'/details',[
            'title'=>'Should not change published title',
        ])->assertStatus(409);
        $this->actingAs($owner)->patchJson('/upload-studio/'.$id.'/access',[
            'is_premium'=>true,
        ])->assertStatus(409);
        $this->assertDatabaseHas('media_ingests',['id'=>$id,'status'=>'quarantined']);
        $this->assertDatabaseCount('content_rights',0);
        $this->assertDatabaseCount('media_assets',0);
    }

    public function test_episode_still_and_series_access_are_saved_to_real_models(): void
    {
        Storage::fake('local');
        $series=Series::create(['title'=>'Studio QA Series','slug'=>'studio-qa-series',
            'status'=>'draft','is_premium'=>false]);
        $season=Season::create(['series_id'=>$series->id,'season_number'=>1,'title'=>'Season One']);
        $episode=Episode::create(['season_id'=>$season->id,'episode_number'=>1,
            'title'=>'Pilot','status'=>'draft']);
        $this->actingAs($this->admin());
        $this->postJson('/upload-studio',[
            'type'=>'episode','title_id'=>$episode->id,'video'=>$this->fakeVideo('episode.mp4'),
        ])->assertCreated();
        $id=(int) DB::table('media_ingests')->value('id');
        $this->patchJson('/upload-studio/'.$id.'/details',[
            'title'=>'Pilot Extended',
            'overview'=>'Episode overview saved.',
            'runtime_minutes'=>42,
        ])->assertOk();
        $this->patchJson('/upload-studio/'.$id.'/access',[
            'is_premium'=>true,
        ])->assertOk();
        $this->assertDatabaseHas('episodes',['id'=>$episode->id,
            'title'=>'Pilot Extended','runtime_minutes'=>42,'status'=>'draft']);
        $this->assertDatabaseHas('series',['id'=>$series->id,'is_premium'=>1,'status'=>'draft']);
        $this->assertDatabaseCount('media_assets',0);
    }

    public function test_upload_studio_displays_existing_movie_and_episode_artwork(): void
    {
        $movie = $this->movie();
        $movie->forceFill([
            'poster_path' => '/qa-poster.jpg',
            'backdrop_path' => '/qa-backdrop.jpg',
        ])->save();
        $series = Series::create([
            'title'=>'Artwork QA Series', 'slug'=>'artwork-qa-series', 'status'=>'draft',
            'is_premium'=>false,
        ]);
        $season = Season::create([
            'series_id'=>$series->id, 'season_number'=>1, 'title'=>'Season One',
        ]);
        Episode::create([
            'season_id'=>$season->id, 'episode_number'=>1,
            'title'=>'Episode with still', 'status'=>'draft',
            'still_path'=>'artwork/episode/qa-still.png',
        ]);

        $this->actingAs($this->admin())->get('/upload-studio')->assertOk()
            ->assertSee('data-art-poster="https://image.tmdb.org/t/p/w342/qa-poster.jpg"', false)
            ->assertSee('data-art-backdrop="https://image.tmdb.org/t/p/w780/qa-backdrop.jpg"', false)
            ->assertSee('data-art-still=', false)
            ->assertSee('gm-existing-artwork-preview')
            ->assertSee("entry.artwork[el('gm-artwork-slot').value] = result.artwork_url;", false);
    }

    public function test_global_feedback_partial_is_available_to_upload_and_admin_and_public_pages(): void
    {
        $this->actingAs($this->admin())->withSession([
            'status' => 'Video uploaded successfully. You can finish now or add optional details.',
        ]);
        $this->get('/upload-studio')->assertOk()
            ->assertSee('window.GomovieFeedback', false)
            ->assertSee('gm-feedback-success')
            ->assertSee('Video uploaded successfully. You can finish now or add optional details.');
        $this->get('/dashboard')->assertOk()
            ->assertSee('window.GomovieFeedback', false);
        $this->get('/about')->assertOk()
            ->assertSee('window.GomovieFeedback', false);
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
