<?php

namespace Tests\Feature;

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Season;
use App\Models\Series;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CatalogManagementTableTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role])->save();
        return $user;
    }

    public function test_upload_returns_artwork_url_before_movie_is_saved_and_reuses_existing_storage(): void
    {
        Storage::fake('public');
        $admin = $this->staff();
        $this->actingAs($admin);

        $url = $this->postJson('/dashboard/media/artwork', [
            'type' => 'movie', 'slot' => 'poster', 'upload_only' => '1',
            'image' => UploadedFile::fake()->image('poster.jpg', 80, 120),
        ])->assertCreated()->json('url');

        $files = Storage::disk('public')->allFiles('artwork/movie');
        self::assertCount(1, $files);
        self::assertSame(Storage::disk('public')->url($files[0]), $url);
        $this->assertDatabaseCount('movies', 0);
        $this->assertDatabaseHas('admin_audit_events', [
            'actor_id' => $admin->id, 'action' => 'media.artwork_stored', 'resource_id' => null,
        ]);

        $this->post('/dashboard/catalog/movies', [
            'title' => 'Immediate Artwork QA', 'release_year' => 2026,
            'is_premium' => '1', 'status' => 'draft', 'poster_path' => $url,
        ])->assertRedirect();

        $movie = Movie::query()->where('slug', 'immediate-artwork-qa')->firstOrFail();
        self::assertSame($files[0], $movie->poster_path);
        self::assertSame($files, Storage::disk('public')->allFiles('artwork/movie'));
        $this->get(route('admin.catalog.edit', ['movies', $movie->id]))->assertOk()
            ->assertSee('value="'.e($url).'"', false);
    }

    public function test_media_library_keeps_existing_attachment_behavior_and_rejects_invalid_upload_only_requests(): void
    {
        Storage::fake('public');
        $this->actingAs($this->staff());
        $movie = Movie::create(['title' => 'Media Library QA', 'slug' => 'media-library-qa', 'status' => 'draft']);
        $payload = ['type' => 'movie', 'slot' => 'backdrop'];

        $this->postJson('/dashboard/media/artwork', $payload + [
            'image' => UploadedFile::fake()->image('backdrop.png'),
        ])->assertUnprocessable()->assertJsonValidationErrors('title_id');
        $invalid = tempnam(sys_get_temp_dir(), 'gomovie-invalid-artwork-');
        try {
            file_put_contents($invalid, 'not an image');
            $this->postJson('/dashboard/media/artwork', $payload + [
                'upload_only' => '1', 'image' => new UploadedFile($invalid, 'fake.png', 'image/png', UPLOAD_ERR_OK, true),
            ])->assertUnprocessable()->assertJsonValidationErrors('image');
        } finally {
            unlink($invalid);
        }
        self::assertSame([], Storage::disk('public')->allFiles('artwork/movie'));

        $this->post('/dashboard/media/artwork', $payload + [
            'title_id' => $movie->id, 'image' => UploadedFile::fake()->image('backdrop.png'),
        ])->assertRedirect();
        Storage::disk('public')->assertExists($movie->fresh()->backdrop_path);
    }

    public function test_movie_artwork_can_mix_upload_and_url_and_be_updated_independently(): void
    {
        Storage::fake('public');
        $this->actingAs($this->staff());
        $this->get('/dashboard/catalog/movies/create')->assertOk()
            ->assertSee('name="poster_upload"', false)->assertSee('name="backdrop_upload"', false);

        $data = ['title' => 'Artwork QA', 'release_year' => 2026, 'is_premium' => '1', 'status' => 'draft'];
        $url = 'https://images.example.test/backdrop?id=123&size=full';
        $response = $this->post('/dashboard/catalog/movies', $data + [
            'poster_path' => '/old-tmdb-poster.jpg',
            'poster_upload' => UploadedFile::fake()->image('poster.jpg', 80, 120),
            'backdrop_path' => $url,
        ]);
        $movie = Movie::query()->where('slug', 'artwork-qa')->firstOrFail();
        $response->assertRedirect(route('admin.catalog.edit', ['movies', $movie->id]));
        self::assertStringStartsWith('artwork/movie/', $movie->poster_path);
        $poster = $movie->poster_path;
        Storage::disk('public')->assertExists($poster);
        self::assertSame($url, $movie->backdrop_path);

        $this->get(route('admin.catalog.edit', ['movies', $movie->id]))->assertOk()
            ->assertSee('src="'.e($url).'"', false);
        $this->put('/dashboard/catalog/movies/'.$movie->id, $data + [
            'backdrop_upload' => UploadedFile::fake()->image('backdrop.png', 120, 80),
        ])->assertRedirect();
        $movie->refresh();
        self::assertSame($poster, $movie->poster_path);
        self::assertStringStartsWith('artwork/movie/', $movie->backdrop_path);
        Storage::disk('public')->assertExists($movie->backdrop_path);
    }

    public function test_invalid_artwork_does_not_store_a_file_or_create_a_title(): void
    {
        Storage::fake('public');
        $this->actingAs($this->staff());
        $this->post('/dashboard/catalog/movies', [
            'title' => 'Invalid Artwork QA', 'release_year' => 2026, 'is_premium' => '1', 'status' => 'draft',
            'poster_upload' => UploadedFile::fake()->createWithContent('poster.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
            'backdrop_path' => 'https://127.0.0.1/internal.png',
        ])->assertSessionHasErrors('poster_upload');

        $this->post('/dashboard/catalog/movies', [
            'title' => 'Invalid URL QA', 'release_year' => 2026, 'is_premium' => '1', 'status' => 'draft',
            'poster_upload' => UploadedFile::fake()->image('poster.jpg'),
            'backdrop_path' => 'https://127.0.0.1/internal.png',
        ])->assertSessionHasErrors('backdrop_path');
        self::assertSame([], Storage::disk('public')->allFiles('artwork'));
        $this->assertDatabaseCount('movies', 0);
    }

    public function test_compact_movies_table_paginates_ten_by_default_with_search_and_filters(): void
    {
        $this->actingAs($this->staff());
        for ($index = 1; $index <= 22; $index++) {
            Movie::create([
                'title' => sprintf('Catalog Movie %02d', $index),
                'slug' => sprintf('catalog-movie-%02d', $index),
                'release_year' => 2020,
                'status' => 'draft',
                'poster_path' => '/test-poster.jpg',
            ]);
        }

        $this->get('/dashboard/catalog/movies')
            ->assertOk()
            ->assertSee('Manage Table')
            ->assertSee('Rows per page')
            ->assertSee('Select all items on this page')
            ->assertSee('gm-catalog-floating-menu')
            ->assertSee('gm-catalog-title-link')
            ->assertSee('https://image.tmdb.org/t/p/w342/test-poster.jpg')
            ->assertSee('Showing')
            ->assertSee('22')
            ->assertSee('Catalog Movie 22')
            ->assertDontSee('Catalog Movie 11');

        $this->get('/dashboard/catalog/movies?per_page=20&page=2')
            ->assertOk()
            ->assertSee('Catalog Movie 01')
            ->assertSee('Catalog Movie 02')
            ->assertDontSee('Catalog Movie 22');

        $this->get('/dashboard/catalog/movies?q=Catalog+Movie+03&per_page=10')
            ->assertOk()
            ->assertSee('Catalog Movie 03')
            ->assertDontSee('Catalog Movie 02');

        $this->get('/dashboard/catalog/movies?per_page=11')
            ->assertSessionHasErrors('per_page');
    }

    public function test_bulk_publish_requires_explicit_metadata_artwork_approval_and_is_atomic(): void
    {
        $this->actingAs($this->staff());
        $one = Movie::create(['title' => 'Bulk Movie A', 'slug' => 'bulk-movie-a', 'status' => 'draft']);
        $two = Movie::create(['title' => 'Bulk Movie B', 'slug' => 'bulk-movie-b', 'status' => 'draft']);

        $url = '/dashboard/catalog/movies/bulk';
        $this->post($url, ['ids' => [$one->id, $two->id], 'action' => 'published'])
            ->assertSessionHasErrors('approve_metadata');
        $this->assertDatabaseHas('movies', ['id' => $one->id, 'status' => 'draft']);

        $this->post($url, ['ids' => [$one->id, 987654], 'action' => 'published', 'approve_metadata' => '1'])
            ->assertSessionHasErrors('ids');
        $this->assertDatabaseHas('movies', ['id' => $one->id, 'status' => 'draft']);

        $this->post($url, ['ids' => [$one->id, $two->id], 'action' => 'published',
            'approve_metadata' => '1', 'per_page' => 10])
            ->assertRedirect();

        $this->assertDatabaseHas('movies', ['id' => $one->id, 'status' => 'published']);
        $this->assertDatabaseHas('movies', ['id' => $two->id, 'status' => 'published']);
        $this->assertNotNull($one->fresh()->published_at);
        $this->assertDatabaseHas('admin_audit_events', ['resource_id' => $one->id,
            'action' => 'catalog.bulk.published']);

        $this->get('/')->assertOk()->assertSee('Bulk Movie A')->assertSee('Bulk Movie B');

        $this->post($url, ['ids' => [$one->id, $two->id], 'action' => 'draft'])
            ->assertRedirect();
        $this->assertNull($one->fresh()->published_at);
        $this->assertDatabaseHas('movies', ['id' => $one->id, 'status' => 'draft']);
        $this->get('/')->assertOk()->assertDontSee('Bulk Movie A');
    }

    public function test_private_preview_and_safe_archive_preserve_series_and_episodes(): void
    {
        $owner = $this->staff();
        $series = Series::create([
            'title' => 'Sample Preview Series', 'slug' => 'sample-preview-series',
            'status' => 'draft', 'first_air_date' => '2020-01-01',
            'poster_path' => '/preview-series.jpg',
        ]);
        $season = Season::create([
            'series_id' => $series->id, 'season_number' => 1, 'title' => 'Season One',
        ]);
        Episode::create([
            'season_id' => $season->id, 'episode_number' => 1,
            'title' => 'First Real Episode', 'status' => 'draft',
        ]);

        $this->get('/dashboard/catalog/series/'.$series->id.'/preview')->assertRedirect('/login');
        $this->get('/watch/series/'.$series->slug)->assertNotFound();

        $this->actingAs($owner)->get('/dashboard/catalog/series/'.$series->id.'/preview')
            ->assertOk()->assertSee('Private staff preview')
            ->assertSee('First Real Episode')->assertSee('preview-series.jpg');

        $this->post('/dashboard/catalog/series/bulk', [
            'ids' => [$series->id], 'action' => 'published', 'approve_metadata' => '1',
        ])->assertRedirect();
        $this->assertDatabaseHas('series', ['id' => $series->id, 'status' => 'published']);
        $this->get('/watch/series/'.$series->slug)->assertOk();

        $this->post('/dashboard/catalog/series/'.$series->id.'/archive')
            ->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('series', ['id' => $series->id, 'status' => 'archived']);
        $this->assertDatabaseCount('episodes', 1);
        $this->get('/watch/series/'.$series->slug)->assertNotFound();

        $this->get('/dashboard/catalog/series?status=archived')
            ->assertOk()->assertSee('Sample Preview Series');
    }

    public function test_editor_cannot_archive_and_viewer_cannot_manage_catalog(): void
    {
        $movie = Movie::create(['title' => 'Protected Movie', 'slug' => 'protected-movie', 'status' => 'draft']);
        $this->actingAs($this->staff('editor'))
            ->post('/dashboard/catalog/movies/'.$movie->id.'/archive')
            ->assertForbidden();
        $this->actingAs($this->staff('viewer'))
            ->get('/dashboard/catalog/movies')->assertForbidden();
        $this->assertDatabaseHas('movies', ['id' => $movie->id, 'status' => 'draft']);
    }

    public function test_confirmation_notification_is_fixed_without_shifting_layout(): void
    {
        $this->actingAs($this->staff())
            ->withSession(['status' => 'Title updated.'])
            ->get('/dashboard/catalog/movies')
            ->assertOk()
            ->assertSee('window.GomovieFeedback', false)
            ->assertSee('name="gm-feedback-success"', false)
            ->assertSee('#gm-feedback-stack{position:fixed')
            ->assertDontSee('class="gm-studio-toast"', false)
            ->assertSee('Dismiss notification')
            ->assertSee('Title updated.');
    }
}
