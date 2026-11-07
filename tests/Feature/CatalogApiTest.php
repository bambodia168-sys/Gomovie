<?php

namespace Tests\Feature;

use App\Models\Episode;
use App\Models\Genre;
use App\Models\Movie;
use App\Models\Season;
use App\Models\Series;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_movies_are_not_discoverable(): void
    {
        Movie::create(['title' => 'Private Draft', 'slug' => 'private-draft', 'status' => 'draft']);
        $this->getJson('/api/v1/movies')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/movies/private-draft')->assertNotFound();
    }

    public function test_published_movie_can_be_filtered_by_genre_and_country(): void
    {
        $movie = Movie::create([
            'title' => 'Sample Movie', 'slug' => 'sample-movie',
            'status' => 'published', 'release_year' => 2025, 'country_code' => 'KH',
        ]);
        $genre = Genre::create(['name' => 'Drama', 'slug' => 'drama']);
        $movie->genres()->attach($genre->id);
        $this->getJson('/api/v1/movies?genre=drama&country=KH&year=2025')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'sample-movie');
        $this->getJson('/api/v1/movies?genre=comedy')->assertJsonCount(0, 'data');
    }

    public function test_series_season_hides_draft_episodes(): void
    {
        $series = Series::create(['title' => 'Sample Series', 'slug' => 'sample-series', 'status' => 'published']);
        $season = Season::create(['series_id' => $series->id, 'season_number' => 1, 'title' => 'Season One']);
        Episode::create(['season_id' => $season->id, 'episode_number' => 1, 'title' => 'Episode One', 'status' => 'published']);
        Episode::create(['season_id' => $season->id, 'episode_number' => 2, 'title' => 'Private Episode', 'status' => 'draft']);

        $this->getJson('/api/v1/series/sample-series/seasons/1')
            ->assertOk()->assertJsonCount(1, 'data.episodes')
            ->assertJsonPath('data.episodes.0.title', 'Episode One');
    }
}
