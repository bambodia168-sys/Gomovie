<?php

namespace App\Services\Streaming;

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Media\AutomaticPublicationService;
use App\Services\Media\PublicationRecovery;
use App\Services\Rights\GlobalRights;
use App\Support\SiteSettings;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PlaybackAvailability
{
    public const LABELS = [
        'ready' => 'Watch now',
        'processing' => 'Processing',
        'sign-in' => 'Sign in to watch',
        'membership' => 'Subscription required',
        'unavailable' => 'Currently unavailable',
    ];

    public function __construct(
        private readonly AutomaticPublicationService $automatic,
        private readonly GlobalRights $rights,
    ) {}

    /**
     * Resolve availability for visible catalog items in batches. A series badge
     * links to a playable episode, including when its first episode is unavailable.
     */
    public function catalog(Collection $movies, Collection $series, ?User $viewer, ?UserProfile $profile): Collection
    {
        $episodes = Episode::query()->join('seasons', 'episodes.season_id', '=', 'seasons.id')
            ->whereIn('seasons.series_id', $series->pluck('id'))
            ->where('episodes.status', 'published')
            ->where(fn ($q) => $q->whereNull('episodes.published_at')->orWhere('episodes.published_at', '<=', now()))
            ->orderBy('seasons.season_number')->orderBy('episodes.episode_number')->orderBy('episodes.id')
            ->get(['episodes.id', 'seasons.series_id']);
        $seriesById = $series->keyBy('id');
        $targets = collect($movies->all())->map(fn (Movie $movie) => $this->target($movie))
            ->concat($episodes->map(fn (Episode $episode) => $this->target($episode, $seriesById[$episode->series_id]->is_premium)));
        $states = $this->states($targets, $viewer, $profile);

        $catalog = collect($movies->all())->mapWithKeys(fn (Movie $movie) => [
            'movie:'.$movie->id => ['state' => $states['movie:'.$movie->id]],
        ]);
        $bySeries = $episodes->groupBy('series_id');
        foreach ($series as $show) {
            $ordered = $bySeries->get($show->id, collect());
            foreach (['ready', 'sign-in', 'membership', 'processing'] as $state) {
                $episode = $ordered->first(fn (Episode $episode) => $states['episode:'.$episode->id] === $state);
                if ($episode) {
                    $catalog['series:'.$show->id] = ['state' => $state, 'episode' => $episode->id];
                    break;
                }
            }
            $catalog['series:'.$show->id] ??= ['state' => 'unavailable'];
        }

        return $catalog;
    }

    public function forMedia(Collection $media, ?User $viewer, ?UserProfile $profile): Collection
    {
        return $this->states($media->map(fn (Movie|Episode $item) => $this->target($item,
            $item instanceof Episode ? (bool) $item->season->series->is_premium : null)), $viewer, $profile);
    }

    private function target(Movie|Episode $media, ?bool $premium = null): array
    {
        return [
            'type' => $media instanceof Movie ? 'movie' : 'episode',
            'id' => $media->id,
            'premium' => $premium ?? (bool) $media->is_premium,
            'guest' => $media instanceof Movie && $media->is_standalone_video,
        ];
    }

    private function states(Collection $targets, ?User $viewer, ?UserProfile $profile): Collection
    {
        if ($targets->isEmpty()) return collect();

        $keys = fn (Collection $rows, string $type, string $id): Collection => $rows
            ->mapWithKeys(fn ($row) => [$row->{$type}.':'.$row->{$id} => true]);
        $rights = $this->forTargets($this->rights->activeQuery(), $targets, 'licensable')
            ->get(['licensable_type', 'licensable_id', 'rights_reference']);
        $active = $keys($rights, 'licensable_type', 'licensable_id');
        $documented = $keys($rights->filter(fn ($row) => $row->rights_reference !== null && $row->rights_reference !== ''),
            'licensable_type', 'licensable_id');

        $public = $this->automatic->publicEnabled()
            ? $keys($this->forTargets($this->automatic->verifiedPublicAssets(), $targets, 'assets.streamable')->get(),
                'streamable_type', 'streamable_id')
            : collect();
        $edge = PlaybackGrantService::edgeConfigured()
            ? $keys($this->forTargets(DB::table('media_assets'), $targets, 'streamable')
                ->where('status', 'ready')->where('format', 'hls')->whereNull('drm_scheme')
                ->orderBy('id')->get(['streamable_type', 'streamable_id', 'manifest_key'])
                ->unique(fn ($row) => $row->streamable_type.':'.$row->streamable_id)
                ->filter(fn ($row) => PlaybackGrantService::usableManifestKey($row->manifest_key)),
                'streamable_type', 'streamable_id')
            : collect();
        $processing = $this->automatic->enabled() && SiteSettings::get('transcoding_enabled') === true
            ? $keys($this->forTargets(DB::table('media_ingests'), $targets, 'ingestable')
                ->where('auto_publication_requested', true)->whereNull('pipeline_error')
                ->whereIn('status', ['quarantined', 'approved', 'processing', 'encoded'])
                ->get()->reject(fn ($row) => app(PublicationRecovery::class)->stalled($row)),
                'ingestable_type', 'ingestable_id')
            : collect();

        $signedIn = $viewer && $viewer->is_active && $viewer->hasVerifiedEmail();
        $hasSubscription = $profile && $targets->contains('premium', true)
            && $viewer && Subscription::query()->where('user_id', $viewer->id)->currentlyActive()->exists();
        return $targets->mapWithKeys(function (array $item) use ($active, $documented, $public, $edge, $processing, $signedIn, $profile, $hasSubscription): array {
            $key = $item['type'].':'.$item['id'];
            $readyPublic = !$item['premium'] && $documented->has($key) && $public->has($key);
            $readyEdge = !$item['guest'] && $active->has($key) && $edge->has($key);
            $state = match (true) {
                $readyPublic && ($item['guest'] || $signedIn) => 'ready',
                ($readyPublic || $readyEdge) && !$signedIn => 'sign-in',
                $readyEdge && $profile && $item['premium'] && !$hasSubscription => 'membership',
                $readyEdge && $profile => 'ready',
                !$item['premium'] && $documented->has($key) && $processing->has($key) => 'processing',
                default => 'unavailable',
            };
            return [$key => $state];
        });
    }

    private function forTargets(Builder $query, Collection $targets, string $prefix): Builder
    {
        $ids = $targets->groupBy('type');
        return $query->where(fn ($q) => $q
            ->where(fn ($movies) => $movies->where($prefix.'_type', 'movie')
                ->whereIntegerInRaw($prefix.'_id', $ids->get('movie', collect())->pluck('id')))
            ->orWhere(fn ($episodes) => $episodes->where($prefix.'_type', 'episode')
                ->whereIntegerInRaw($prefix.'_id', $ids->get('episode', collect())->pluck('id'))));
    }
}
