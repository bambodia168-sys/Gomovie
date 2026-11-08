<?php

namespace App\Console\Commands;

use App\Models\Genre;
use App\Models\Movie;
use App\Models\Series;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Retrieves actual, canonical TMDB metadata, never fabricated fixtures.
 * Imports metadata only: no videos, rights grants, payment activation, or streaming.
 */
final class ImportPopularCatalog extends Command
{
    protected $signature = 'gomovie:catalog:import-popular
        {--movies=20 : Number of real movies to fetch (0-20)}
        {--series=20 : Number of real TV series to fetch (0-20)}
        {--episodes=12 : Maximum first-season episodes to import per TV series (0-30)}
        {--apply : Save imported catalog metadata to the current database}
        {--publish-metadata : Make metadata catalog pages publicly visible (never enables streams)}
        {--confirm-metadata-rights : Confirm TMDB commercial usage, images, and attribution approval}';

    protected $description = 'Import up to 20 real movies and 20 real series from TMDB, safely and idempotently';

    public function handle(): int
    {
        $movies = $this->wholeNumber('movies', 20);
        $series = $this->wholeNumber('series', 20);
        $episodes = $this->wholeNumber('episodes', 30);
        if ($movies === null || $series === null || $episodes === null || $movies + $series === 0) {
            $this->error('Choose 0-20 movies, 0-20 series, 0-30 episodes, and at least one catalog type.');
            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $publish = (bool) $this->option('publish-metadata');
        if ($publish && (!$apply || !$this->option('confirm-metadata-rights'))) {
            $this->error('Publication requires --apply --publish-metadata --confirm-metadata-rights and actual licensing/attribution approval.');
            return self::FAILURE;
        }

        $token = trim((string) config('gomovie.tmdb_token'));
        if (strlen($token) < 20) {
            $this->error('TMDB is not configured. Set GOMOVIE_TMDB_TOKEN privately in the installed .env; do not paste it into chat.');
            return self::FAILURE;
        }

        $this->line('MODE='.($apply ? ($publish ? 'PUBLISH_METADATA' : 'IMPORT_DRAFTS') : 'PREVIEW_ONLY'));
        $this->line('VIDEO_STREAMING=UNCHANGED');
        $this->line('MEDIA_RIGHTS=NOT_GRANTED');
        $this->line('BILLING=UNCHANGED');

        $failures = 0;
        foreach (['movie' => $movies, 'series' => $series] as $kind => $target) {
            if ($target === 0) {
                continue;
            }
            $candidates = $this->candidates($token, $kind, $target);
            $processed = 0;
            $created = 0;
            $existing = 0;
            $episodeCount = 0;
            foreach ($candidates as $id) {
                $detail = $this->request($token, '/'.$this->endpoint($kind).'/'.$id);
                if (!$detail || (int) ($detail['id'] ?? 0) !== $id) {
                    $failures++;
                    continue;
                }
                $metadata = $this->normalize($detail, $kind);
                if (!$metadata) {
                    continue;
                }
                $firstSeason = null;
                $seasonEpisodes = [];
                if ($kind === 'series' && $episodes > 0) {
                    $seasonInfo = collect($detail['seasons'] ?? [])
                        ->first(fn ($s) => is_array($s) && (int) ($s['season_number'] ?? 0) === 1);
                    if (is_array($seasonInfo)) {
                        $seasonDetail = $this->request($token, '/tv/'.$id.'/season/1');
                        if (is_array($seasonDetail) && is_array($seasonDetail['episodes'] ?? null)) {
                            $firstSeason = [
                                'season_number' => 1,
                                'title' => Str::limit((string) ($seasonDetail['name'] ?? 'Season 1'), 255, ''),
                                'poster_path' => $this->artworkPath($seasonDetail['poster_path'] ?? null),
                            ];
                            foreach ($seasonDetail['episodes'] as $episode) {
                                if (count($seasonEpisodes) >= $episodes) {
                                    break;
                                }
                                if (!is_array($episode)
                                    || (int) ($episode['episode_number'] ?? 0) < 1
                                    || trim((string) ($episode['name'] ?? '')) === '') {
                                    continue;
                                }
                                $airDate = (string) ($episode['air_date'] ?? '');
                                if (!$this->isAired($airDate)) {
                                    continue;
                                }
                                $seasonEpisodes[] = [
                                    'episode_number' => (int) $episode['episode_number'],
                                    'title' => Str::limit((string) $episode['name'], 255, ''),
                                    'overview' => Str::limit((string) ($episode['overview'] ?? ''), 12000, ''),
                                    'air_date' => $airDate,
                                    'runtime_minutes' => $this->runtime($episode['runtime'] ?? null),
                                    'still_path' => $this->artworkPath($episode['still_path'] ?? null),
                                ];
                            }
                        }
                    }
                }
                $processed++;
                $this->line(strtoupper($kind).'_'.str_pad((string) $processed, 2, '0', STR_PAD_LEFT)
                    .'=TMDB:'.$id.' '.Str::limit($metadata['title'], 90));
                if (!$apply) {
                    if ($processed === $target) {
                        break;
                    }
                    continue;
                }

                try {
                    $added = DB::transaction(function () use (
                        $kind, $metadata, $detail, $publish, $firstSeason, $seasonEpisodes
                    ): array {
                        $model = $kind === 'movie' ? Movie::class : Series::class;
                        $id = $metadata['tmdb_id'];
                        $existing = $model::query()->where('tmdb_id', $id)->first();
                        if ($existing) {
                            // Never overwrite operator-edited records or upgrade existing drafts.
                            return ['created' => false, 'episodes' => 0];
                        }

                        $slug = Str::slug(Str::limit($metadata['title'], 200, '')).'-'.$id;
                        if ($model::query()->where('slug', $slug)->exists()) {
                            throw new \RuntimeException('Catalog slug collision');
                        }

                        $metadata['slug'] = $slug;
                        $metadata['status'] = $publish ? 'published' : 'draft';
                        $metadata['published_at'] = $publish ? now() : null;
                        $metadata['is_premium'] = false; // Never fabricate a paid entitlement.
                        $title = $model::query()->create($metadata);
                        foreach (($detail['genres'] ?? []) as $genre) {
                            $name = trim((string) ($genre['name'] ?? ''));
                            $genreSlug = Str::slug($name);
                            if ($name === '' || $genreSlug === '' || strlen($genreSlug) > 255) {
                                continue;
                            }
                            $savedGenre = Genre::query()->firstOrCreate(
                                ['slug' => $genreSlug], ['name' => Str::limit($name, 255, '')]
                            );
                            $title->genres()->syncWithoutDetaching([$savedGenre->id]);
                        }

                        $addedEpisodes = 0;
                        if ($kind === 'series' && $firstSeason !== null) {
                            $season = $title->seasons()->create($firstSeason);
                            foreach ($seasonEpisodes as $episode) {
                                $episode['status'] = $publish ? 'published' : 'draft';
                                $episode['published_at'] = $publish ? now() : null;
                                $season->episodes()->create($episode);
                                $addedEpisodes++;
                            }
                        }
                        return ['created' => true, 'episodes' => $addedEpisodes];
                    });
                    if ($added['created']) {
                        $created++;
                        $episodeCount += $added['episodes'];
                    } else {
                        $existing++;
                    }
                } catch (Throwable $exception) {
                    // No secrets or raw responses in stdout.
                    $this->warn(strtoupper($kind).'_IMPORT_FAILED=TMDB:'.$metadata['tmdb_id']
                        .' REASON='.class_basename($exception));
                    $failures++;
                }
                if ($processed === $target) {
                    break;
                }
            }

            $this->line(strtoupper($kind).'_PROCESSED='.$processed);
            $this->line(strtoupper($kind).'_CREATED='.$created);
            $this->line(strtoupper($kind).'_EXISTING='.$existing);
            if ($kind === 'series') {
                $this->line('FIRST_SEASON_EPISODES_CREATED='.$episodeCount);
            }
            if ($processed !== $target) {
                $this->warn(strtoupper($kind).'_TARGET_NOT_MET='.$target);
                $failures++;
            }
        }
        $this->line('CATALOG_IMPORT_FAILURES='.$failures);
        $this->line($apply ? 'CATALOG_WRITES=METADATA_ONLY' : 'CATALOG_WRITES=NONE');
        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function wholeNumber(string $option, int $maximum): ?int
    {
        $value = filter_var($this->option($option), FILTER_VALIDATE_INT);
        return $value !== false && $value >= 0 && $value <= $maximum ? $value : null;
    }

    private function endpoint(string $kind): string
    {
        return $kind === 'movie' ? 'movie' : 'tv';
    }

    /** @return array<int,int> */
    private function candidates(string $token, string $kind, int $wanted): array
    {
        $ids = [];
        for ($page = 1; $page <= 5 && count($ids) < $wanted + 12; $page++) {
            $data = $this->request($token, '/'.$this->endpoint($kind).'/popular', [
                'page' => $page, 'language' => 'en-US',
            ]);
            if (!is_array($data) || !is_array($data['results'] ?? null)) {
                break;
            }
            foreach ($data['results'] as $entry) {
                if (!is_array($entry) || ($entry['adult'] ?? false) === true) {
                    continue;
                }
                $id = (int) ($entry['id'] ?? 0);
                $date = (string) ($entry[$kind === 'movie' ? 'release_date' : 'first_air_date'] ?? '');
                if ($id > 0 && $this->isAired($date)) {
                    $ids[$id] = $id;
                }
            }
        }
        return array_values($ids);
    }

    private function request(string $token, string $path, array $query = []): ?array
    {
        try {
            // All paths are constructed from a fixed endpoint and positive integer IDs.
            $response = Http::withToken($token)->acceptJson()->timeout(15)
                ->get('https://api.themoviedb.org/3'.$path, $query);
            if (!$response->successful()) {
                $this->warn('TMDB_HTTP_ERROR='.$response->status());
                return null;
            }
            $data = $response->json();
            return is_array($data) ? $data : null;
        } catch (Throwable $exception) {
            $this->warn('TMDB_CONNECTION_ERROR='.class_basename($exception));
            return null;
        }
    }

    private function normalize(array $data, string $kind): ?array
    {
        $title = Str::limit(trim((string) ($data[$kind === 'movie' ? 'title' : 'name'] ?? '')), 255, '');
        $date = (string) ($data[$kind === 'movie' ? 'release_date' : 'first_air_date'] ?? '');
        if ($title === '' || !$this->isAired($date)) {
            return null;
        }
        $country = $kind === 'movie'
            ? ($data['production_countries'][0]['iso_3166_1'] ?? null)
            : ($data['origin_country'][0] ?? null);
        $country = is_string($country) && preg_match('/^[A-Z]{2}$/', $country) ? $country : null;
        $common = [
            'tmdb_id' => (int) $data['id'],
            'title' => $title,
            'overview' => Str::limit((string) ($data['overview'] ?? ''), 12000, ''),
            'country_code' => $country,
            'poster_path' => $this->artworkPath($data['poster_path'] ?? null),
            'backdrop_path' => $this->artworkPath($data['backdrop_path'] ?? null),
        ];
        return $kind === 'movie'
            ? array_merge($common, ['release_year' => (int) substr($date, 0, 4),
                'runtime_minutes' => $this->runtime($data['runtime'] ?? null)])
            : array_merge($common, ['first_air_date' => $date]);
    }

    private function isAired(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        try {
            return Carbon::createFromFormat('!Y-m-d', $date)->format('Y-m-d') === $date
                && $date <= now()->toDateString();
        } catch (Throwable) {
            return false;
        }
    }

    private function runtime(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 && (int) $value <= 1440 ? (int) $value : null;
    }

    private function artworkPath(mixed $path): ?string
    {
        return is_string($path) && preg_match('#\A/[A-Za-z0-9_/-]+\.(?:jpe?g|png|webp)\z#iD', $path)
            ? $path : null;
    }
}
