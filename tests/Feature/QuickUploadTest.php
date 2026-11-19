<?php

namespace Tests\Feature;

use App\Jobs\AutoPublishUploadedVideo;
use App\Models\Movie;
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

    public function test_upload_studio_shows_all_three_upload_paths(): void
    {
        $this->get('/upload-studio')->assertRedirect(route('login'));
        $viewer = User::factory()->create();
        $this->actingAs($viewer)->get('/upload-studio')->assertForbidden();
        $this->actingAs($viewer)->postJson('/upload-studio/quick', [])->assertForbidden();

        $this->actingAs($this->admin())->get('/upload-studio')
            ->assertOk()
            ->assertSee('Upload Studio')
            ->assertSee('quick-upload-form')
            ->assertSee('name="title"', false)
            ->assertSee('name="rights_confirmed"', false)
            ->assertSee('Normal Video')
            ->assertSee('TV Show')
            ->assertSee('Upload settings')
            ->assertDontSee('name="access"', false)
            ->assertDontSee('Details &amp; Meta', false)
            ->assertDontSee('Catalog Access');

        $this->get('/upload-studio/advanced')->assertOk()
            ->assertSee('gm-upload-studio-form');
    }

    public function test_public_original_creates_its_own_catalog_title_and_uploader_attestation(): void
    {
        Storage::fake('local');
        Queue::fake();
        $this->enableFree();
        $this->actingAs($this->admin());

        $result = $this->postJson('/upload-studio/quick', [
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
        self::assertSame('my original', $movie->title);
        self::assertFalse((bool) $movie->is_premium);
        self::assertSame('published', $movie->status);
        self::assertTrue((bool) $movie->is_standalone_video);
        $this->getJson(route('upload-studio.status', $result->json('upload_id')))
            ->assertOk()->assertJsonPath('public', false)->assertJsonPath('watch_page_public', true)
            ->assertJsonPath('watch_url', $result->json('watch_url'));
        $this->get('/dashboard/media')->assertOk()->assertSee('Public page · Processing');
        $this->deleteJson('/upload-studio/'.$result->json('upload_id'))->assertConflict();
        $this->app['auth']->forgetGuards();
        $this->get($result->json('watch_url'))->assertOk()
            ->assertSee('Video is processing')->assertDontSee('data-public-hls-url=');
        $this->get('/browse?type=video')->assertOk()->assertSee('my original');
        $this->get('/browse?type=movie')->assertOk()->assertDontSee('my original');
        self::assertStringStartsWith('my-original-', $movie->slug);
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

    public function test_normal_upload_rejects_private_and_paid_access(): void
    {
        Storage::fake('local');
        Queue::fake();
        $this->enableFree();
        $this->actingAs($this->admin());
        foreach (['private', 'paid'] as $access) {
            $this->postJson('/upload-studio/quick', [
                'access' => $access,
                'rights_confirmed' => true,
                'video' => $this->video(),
            ])->assertUnprocessable()->assertJsonValidationErrors('access');
        }

        $this->assertDatabaseCount('movies', 0);
        $this->assertDatabaseCount('media_ingests', 0);
        $this->assertDatabaseCount('content_rights', 0);
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
        $movie = Movie::create(['title' => 'Existing Movie', 'slug' => 'existing-movie', 'status' => 'draft']);
        $this->postJson('/upload-studio/quick', [
            'title' => 'My Clip',
            'title_id' => $movie->id,
            'type' => 'movie',
            'rights_confirmed' => 1,
            'video' => $this->video(),
        ])->assertCreated();
        $this->assertDatabaseHas('movies', ['id' => $movie->id, 'title' => 'Existing Movie', 'status' => 'draft']);
        $video = Movie::where('is_standalone_video', true)->sole();
        $this->assertDatabaseHas('media_ingests', ['ingestable_id' => $video->id]);
        $this->postJson('/upload-studio', [
            'type' => 'movie', 'title_id' => $video->id, 'video' => $this->video(),
        ])->assertNotFound();
        $this->assertDatabaseCount('media_ingests', 1);

        $this->put(route('admin.catalog.update', ['movies', $video->id]), [
            'title' => 'Updated Clip', 'slug' => $video->slug, 'status' => 'published', 'is_premium' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('movies', ['id' => $video->id, 'title' => 'Updated Clip', 'is_premium' => false]);
    }
}
