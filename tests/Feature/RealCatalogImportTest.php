<?php

namespace Tests\Feature;

use App\Models\Episode;
use App\Models\Genre;
use App\Models\Movie;
use App\Models\Season;
use App\Models\Series;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class RealCatalogImportTest extends TestCase
{
    use RefreshDatabase;

    private function fakeTmdb(): void
    {
        config()->set('gomovie.tmdb_token', str_repeat('t', 55));
        Http::fake(function (Request $request) {
            $url = $request->url();
            if (str_contains($url, '/movie/popular')) {
                return Http::response(['results' => [
                    ['id' => 11001, 'release_date' => '2020-02-10', 'adult' => false],
                ]]);
            }
            if (str_contains($url, '/tv/popular')) {
                return Http::response(['results' => [
                    ['id' => 22001, 'first_air_date' => '2021-06-01', 'adult' => false],
                ]]);
            }
            if (preg_match('#/movie/11001(?:\?|$)#', $url)) {
                return Http::response([
                    'id' => 11001, 'title' => 'Verified Fixture Movie', 'release_date' => '2020-02-10',
                    'runtime' => 96, 'overview' => 'Movie metadata confirmed by API',
                    'poster_path' => '/real.jpg', 'backdrop_path' => '/realbackdrop.jpg',
                    'production_countries' => [['iso_3166_1' => 'US']],
                    'genres' => [['id' => 18, 'name' => 'Drama']],
                ]);
            }
            if (preg_match('#/tv/22001/season/1(?:\?|$)#', $url)) {
                return Http::response([
                    'name' => 'Season 1', 'poster_path' => '/season.jpg',
                    'episodes' => [
                        ['episode_number' => 1, 'name' => 'Actual Episode One',
                            'air_date' => '2021-06-01', 'runtime' => 45,
                            'overview' => 'Official episode details', 'still_path' => '/episode.jpg'],
                        ['episode_number' => 2, 'name' => 'Actual Episode Two',
                            'air_date' => '2021-06-08', 'runtime' => 47],
                        ['episode_number' => 3, 'name' => 'Future Episode',
                            'air_date' => '2099-01-01'],
                    ],
                ]);
            }
            if (preg_match('#/tv/22001(?:\?|$)#', $url)) {
                return Http::response([
                    'id' => 22001, 'name' => 'Verified Fixture Series',
                    'first_air_date' => '2021-06-01',
                    'overview' => 'Series metadata confirmed by API',
                    'poster_path' => '/show.jpg', 'origin_country' => ['KR'],
                    'seasons' => [['season_number' => 1, 'name' => 'Season 1']],
                    'genres' => [['id' => 18, 'name' => 'Drama']],
                ]);
            }
            return Http::response(['status_message' => 'Not found'], 404);
        });
    }

    public function test_bulk_preview_does_not_write_any_catalog_data(): void
    {
        $this->fakeTmdb();
        $this->artisan('gomovie:catalog:import-popular', [
            '--movies' => 1, '--series' => 1, '--episodes' => 2,
        ])->assertExitCode(0);
        $this->assertDatabaseCount('movies', 0);
        $this->assertDatabaseCount('series', 0);
        $this->assertDatabaseCount('episodes', 0);
    }

    public function test_bulk_apply_creates_real_drafts_and_episodes_without_streams(): void
    {
        $this->fakeTmdb();
        $args = ['--movies' => 1, '--series' => 1, '--episodes' => 2, '--apply' => true];
        $this->artisan('gomovie:catalog:import-popular', $args)->assertExitCode(0);
        $this->artisan('gomovie:catalog:import-popular', $args)->assertExitCode(0);

        $this->assertDatabaseCount('movies', 1);
        $this->assertDatabaseCount('series', 1);
        $this->assertDatabaseCount('seasons', 1);
        $this->assertDatabaseCount('episodes', 2);
        $this->assertDatabaseCount('genres', 1);
        $this->assertDatabaseCount('content_rights', 0);
        $this->assertDatabaseCount('media_assets', 0);

        $movie = Movie::firstOrFail();
        self::assertSame(11001, (int) $movie->tmdb_id);
        self::assertSame('draft', $movie->status);
        self::assertSame('/real.jpg', $movie->poster_path);
        self::assertSame('draft', Series::firstOrFail()->status);
        self::assertSame('draft', Episode::firstOrFail()->status);
        $this->get('/')->assertOk()->assertDontSee('Verified Fixture Movie')
            ->assertDontSee('Verified Fixture Series');
        $this->get('/watch/movie/'.$movie->slug)->assertNotFound();
    }

    public function test_metadata_publication_requires_explicit_rights_approval_and_enables_only_catalog(): void
    {
        $this->fakeTmdb();
        $args = [
            '--movies' => 1, '--series' => 1, '--episodes' => 2,
            '--apply' => true, '--publish-metadata' => true,
        ];
        $this->artisan('gomovie:catalog:import-popular', $args)->assertExitCode(1);
        $this->assertDatabaseCount('movies', 0);

        $args['--confirm-metadata-rights'] = true;
        $this->artisan('gomovie:catalog:import-popular', $args)->assertExitCode(0);

        $movie = Movie::firstOrFail();
        $series = Series::firstOrFail();
        self::assertSame('published', $movie->status);
        self::assertSame('published', $series->status);
        self::assertSame('published', Episode::firstOrFail()->status);
        $this->get('/')->assertOk()
            ->assertSee('Verified Fixture Movie')
            ->assertSee('Verified Fixture Series');
        $this->get('/browse?type=movie')->assertOk()->assertSee('Verified Fixture Movie');
        $this->get('/watch/movie/'.$movie->slug)->assertOk()
            ->assertSee('Playback opens when the licensed media service is approved.');
        $this->get('/watch/series/'.$series->slug.'?view=portrait')->assertOk()
            ->assertSee('Actual Episode One')->assertSee('Actual Episode Two')
            ->assertDontSee('Future Episode');
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertDatabaseCount('content_rights', 0);
    }

    public function test_missing_token_blocks_import_without_database_modification(): void
    {
        config()->set('gomovie.tmdb_token', null);
        $this->artisan('gomovie:catalog:import-popular', [
            '--movies' => 1, '--series' => 0, '--apply' => true,
        ])->assertExitCode(1);
        $this->assertDatabaseCount('movies', 0);
    }
}
