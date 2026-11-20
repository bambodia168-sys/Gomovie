<?php

namespace App\Services\Streaming;

use App\Models\Episode;
use App\Models\Movie;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class WatchProgress
{
    public const MIN_SECONDS = 5;

    public function profiles(?User $viewer): Collection
    {
        if (!$viewer || !$viewer->is_active || !$viewer->hasVerifiedEmail()) return collect();

        return UserProfile::query()->where('user_id', $viewer->id)->where('is_kids', false)
            ->orderBy('id')->get(['id', 'user_id', 'name', 'is_kids']);
    }

    public function selectProfile(Request $request, Collection $profiles, ?int $requested = null): ?UserProfile
    {
        $session = $request->session();
        if ($requested !== null) {
            $profile = $profiles->firstWhere('id', $requested);
            abort_unless($profile, 404);
            $session->put('watch_profile_id', $profile->id);

            return $profile;
        }

        return $profiles->firstWhere('id', $session->get('watch_profile_id')) ?? $profiles->first();
    }

    public function resumeAt(UserProfile $profile, string $kind, int $id): int
    {
        return (int) $this->unfinished($profile)->where('watchable_type', $kind)
            ->where('watchable_id', $id)->value('progress_seconds');
    }

    public function continueWatching(?User $viewer, ?UserProfile $profile, PlaybackAvailability $availability): Collection
    {
        if (!$viewer || !$profile) return collect();

        $rows = $this->unfinished($profile)->orderByDesc('last_watched_at')->orderByDesc('id')->limit(30)->get();
        if ($rows->isEmpty()) return collect();

        $movies = $this->visible(Movie::query())->whereKey($rows->where('watchable_type', 'movie')->pluck('watchable_id')->all())
            ->get()->keyBy('id');
        $episodes = $this->visible(Episode::query())
            ->whereHas('season.series', fn ($query) => $this->visible($query))
            ->whereKey($rows->where('watchable_type', 'episode')->pluck('watchable_id')->all())
            ->with('season.series')->get()->keyBy('id');
        $states = $availability->forMedia(collect($movies->values()->all())->concat($episodes->values()), $viewer, $profile);

        return $rows->map(function ($row) use ($movies, $episodes, $states, $profile): ?array {
            if ($states->get($row->watchable_type.':'.$row->watchable_id) !== 'ready') return null;

            $episode = $row->watchable_type === 'episode' ? $episodes->get($row->watchable_id) : null;
            $item = $episode ? $episode->season->series : $movies->get($row->watchable_id);
            $kind = $episode ? 'series' : 'movie';
            return [
                'item' => $item,
                'kind' => $kind,
                'episode_label' => $episode ? 'S'.$episode->season->season_number.' E'.$episode->episode_number.' · '.$episode->title : null,
                'percent' => (int) round($row->progress_seconds * 100 / $row->duration_seconds),
                'url' => route('watch.show', array_filter([
                    'type' => $kind, 'slug' => $item->slug, 'episode' => $episode?->id, 'profile_id' => $profile->id,
                ])),
            ];
        })->filter()->take(8)->values();
    }

    private function unfinished(UserProfile $profile): \Illuminate\Database\Query\Builder
    {
        return DB::table('watch_history')->where('user_id', $profile->user_id)
            ->where('user_profile_id', $profile->id)
            ->where('completed', false)
            ->where('progress_seconds', '>=', self::MIN_SECONDS)
            ->whereColumn('progress_seconds', '<', 'duration_seconds');
    }

    private function visible(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }
}
