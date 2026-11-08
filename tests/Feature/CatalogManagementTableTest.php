<?php

namespace Tests\Feature;

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Season;
use App\Models\Series;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('class="gm-studio-toast"', false)
            ->assertSee('position:fixed')
            ->assertSee('getBoundingClientRect().bottom + 12')
            ->assertDontSee('fixed right-4 top-4')
            ->assertSee('Dismiss confirmation')
            ->assertSee('Title updated.');
    }
}
