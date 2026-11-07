<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\Season;
use App\Models\Series;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CatalogController extends Controller
{
    public function movies(Request $request): JsonResponse
    {
        $query = $this->filtered(Movie::query(), 'movie', $request);
        $items = $query->orderByDesc('published_at')->paginate(20)->through(
            fn (Movie $movie) => $this->movieData($movie)
        );
        return response()->json($items);
    }

    public function series(Request $request): JsonResponse
    {
        $query = $this->filtered(Series::query(), 'series', $request);
        $items = $query->orderByDesc('published_at')->paginate(20)->through(
            fn (Series $series) => $this->seriesData($series)
        );
        return response()->json($items);
    }

    public function movie(string $slug): JsonResponse
    {
        $movie = Movie::query()->where('slug', $slug)->where('status', 'published')->firstOrFail();
        return response()->json(['data' => $this->movieData($movie) + [
            'genres' => $movie->genres()->pluck('name'),
        ]]);
    }

    public function showSeries(string $slug): JsonResponse
    {
        $series = Series::query()->where('slug', $slug)->where('status', 'published')->firstOrFail();
        return response()->json(['data' => $this->seriesData($series) + [
            'genres' => $series->genres()->pluck('name'),
            'seasons' => $series->seasons()->orderBy('season_number')->get(['id', 'season_number', 'title', 'poster_path']),
        ]]);
    }

    public function season(string $slug, int $number): JsonResponse
    {
        $series = Series::query()->where('slug', $slug)->where('status', 'published')->firstOrFail();
        $season = Season::query()->where('series_id', $series->id)->where('season_number', $number)->firstOrFail();
        return response()->json(['data' => [
            'series' => $this->seriesData($series),
            'season_number' => $season->season_number,
            'title' => $season->title,
            'episodes' => $season->episodes()->where('status', 'published')
                ->get(['id', 'episode_number', 'title', 'overview', 'runtime_minutes', 'still_path']),
        ]]);
    }

    private function filtered(Builder $query, string $type, Request $request): Builder
    {
        $values = $request->validate([
            'q' => ['sometimes', 'string', 'max:100'],
            'genre' => ['sometimes', 'string', 'max:80'],
            'country' => ['sometimes', 'string', 'size:2', 'alpha'],
            'year' => ['sometimes', 'integer', 'between:1888,2100'],
            'actor' => ['sometimes', 'string', 'max:100'],
            'min_rating' => ['sometimes', 'numeric', 'between:1,10'],
        ]);
        $table = $type === 'movie' ? 'movies' : 'series';
        $query->where('status', 'published')
            ->where(function (Builder $q): void {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
        if (isset($values['q'])) $query->where('title', 'like', '%'.$values['q'].'%');
        if (isset($values['genre'])) $query->whereHas('genres', fn (Builder $q) => $q->where('slug', $values['genre']));
        if (isset($values['country'])) $query->where('country_code', strtoupper($values['country']));
        if (isset($values['year'])) {
            $type === 'movie'
                ? $query->where('release_year', $values['year'])
                : $query->whereYear('first_air_date', $values['year']);
        }
        if (isset($values['actor'])) {
            $query->whereExists(function ($sub) use ($table, $type, $values): void {
                $sub->selectRaw('1')->from('credits')
                    ->join('people', 'people.id', '=', 'credits.person_id')
                    ->whereColumn('credits.creditable_id', $table.'.id')
                    ->where('credits.creditable_type', $type)
                    ->where('credits.role', 'cast')
                    ->where('people.name', 'like', '%'.$values['actor'].'%');
            });
        }
        if (isset($values['min_rating'])) {
            $query->whereRaw(
                "(SELECT AVG(rating) FROM ratings WHERE rateable_type = ? AND rateable_id = {$table}.id) >= ?",
                [$type, $values['min_rating']]
            );
        }
        return $query;
    }

    private function movieData(Movie $movie): array
    {
        return $movie->only(['id', 'title', 'slug', 'overview', 'release_year', 'runtime_minutes',
            'country_code', 'poster_path', 'backdrop_path', 'is_premium']);
    }

    private function seriesData(Series $series): array
    {
        return $series->only(['id', 'title', 'slug', 'overview', 'first_air_date',
            'country_code', 'poster_path', 'backdrop_path', 'is_premium']);
    }
}
