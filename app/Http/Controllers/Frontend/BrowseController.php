<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\Series;
use App\Models\UserProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BrowseController extends Controller
{
    public function home(): View
    {
        $movies = $this->visible(Movie::query())->latest('published_at')->latest('id')->limit(24)->get();
        $series = $this->visible(Series::query())->latest('published_at')->latest('id')->limit(24)->get();

        $catalog = $movies->map(fn (Movie $item): array => ['kind'=>'movie','item'=>$item])
            ->concat($series->map(fn (Series $item): array => ['kind'=>'series','item'=>$item]))
            ->sortByDesc(fn (array $entry): int => $entry['item']->published_at?->timestamp
                ?? $entry['item']->created_at?->timestamp ?? 0)->values();

        $heroTitles = $catalog->take(5)->values();
        $topPicks = $catalog->take(8)->values();
        $recentlyAdded = $catalog->sortByDesc(fn (array $entry): int =>
            $entry['item']->created_at?->timestamp ?? 0)->take(18)->values();
        $newReleases = $catalog->take(12)->values();

        // Never fabricate trending rankings. Show this row only when playback
        // history contains real aggregate engagement for visible titles.
        $scores = DB::table('watch_history')
            ->where('last_watched_at','>=',now()->subDays(30))
            ->whereIn('watchable_type',['movie','series'])
            ->select('watchable_type','watchable_id')
            ->selectRaw('COUNT(*) AS views')
            ->groupBy('watchable_type','watchable_id')
            ->orderByDesc('views')->limit(40)->get();
        $rank = [];
        foreach ($scores as $score) {
            $rank[$score->watchable_type.':'.$score->watchable_id] = (int) $score->views;
        }
        $trending = $catalog->filter(fn (array $entry): bool =>
            isset($rank[$entry['kind'].':'.$entry['item']->id]))
            ->sortByDesc(fn (array $entry): int =>
                $rank[$entry['kind'].':'.$entry['item']->id])->take(10)->values();

        return view('frontend.home', compact('heroTitles','topPicks','newReleases','trending','recentlyAdded'));
    }

    public function index(Request $request): View
    {
        $data = $request->validate([
            'q'=>['nullable','string','max:100'],
            'type'=>['nullable','in:all,movie,series'],
            'access'=>['nullable','in:all,free,premium'],
        ]);
        $type = $data['type'] ?? 'all';
        $access = $data['access'] ?? 'all';
        $q = trim($data['q'] ?? '');

        $movies = $type !== 'series'
            ? $this->visible(Movie::query())
                ->when($q !== '', fn ($query) => $query->where('title','like','%'.$q.'%'))
                ->when($access !== 'all', fn ($query) => $query->where('is_premium',$access === 'premium'))
                ->latest('published_at')->paginate(18)->withQueryString()
            : collect();
        $series = $type !== 'movie'
            ? $this->visible(Series::query())
                ->when($q !== '', fn ($query) => $query->where('title','like','%'.$q.'%'))
                ->when($access !== 'all', fn ($query) => $query->where('is_premium',$access === 'premium'))
                ->latest('published_at')->limit(24)->get()
            : collect();

        return view('frontend.browse', compact('movies','series','type','access','q'));
    }

    public function show(Request $request, string $type, string $slug): View
    {
        abort_unless(in_array($type,['movie','series'],true),404);
        $params = $request->validate([
            'view'=>['nullable','in:widescreen,portrait'],
            'episode'=>['nullable','integer','min:1'],
        ]);
        $model = $type === 'movie' ? Movie::query() : Series::query();
        $title = $this->visible($model)->where('slug',$slug)->firstOrFail();
        $displayMode = $params['view'] ?? 'widescreen';

        $seasons = collect();
        $selectedEpisode = null;
        if ($type === 'series') {
            $seasons = $title->seasons()->orderBy('season_number')
                ->with(['episodes'=>fn ($query) => $query->where('status','published')
                    ->where(fn ($visible) => $visible->whereNull('published_at')
                        ->orWhere('published_at','<=',now()))->orderBy('episode_number')])
                ->get();
            $allEpisodes = $seasons->flatMap(fn ($season) => $season->episodes);
            $selectedEpisode = isset($params['episode'])
                ? $allEpisodes->firstWhere('id',(int) $params['episode'])
                : $allEpisodes->first();
            if (isset($params['episode'])) {
                abort_unless($selectedEpisode,404);
            }
        } elseif (isset($params['episode'])) {
            abort(404);
        }

        $viewerAuthorized = auth()->check()
            && auth()->user()->is_active && auth()->user()->hasVerifiedEmail();
        $profileId = $viewerAuthorized ? UserProfile::query()->where('user_id',auth()->id())
            ->where('is_kids',false)->value('id') : null;
        $playerEnabled = config('gomovie.edge_enabled') === true && (bool) $profileId;
        $isWatchlisted = $profileId ? DB::table('watchlists')
            ->where('user_profile_id',$profileId)->where('watchable_type',$type)
            ->where('watchable_id',$title->id)->exists() : false;

        // Published, scheduled-visible content only. No draft disclosure.
        $related = $this->visible($type === 'movie' ? Movie::query() : Series::query())
            ->whereKeyNot($title->id)->latest('published_at')->limit(6)->get()
            ->map(fn ($item): array => ['kind'=>$type,'item'=>$item]);
        if ($related->count() < 6) {
            $otherType = $type === 'movie' ? 'series' : 'movie';
            $other = $this->visible($otherType === 'movie' ? Movie::query() : Series::query())
                ->latest('published_at')->limit(6-$related->count())->get()
                ->map(fn ($item): array => ['kind'=>$otherType,'item'=>$item]);
            $related = $related->concat($other)->values();
        }

        return view('frontend.detail', compact(
            'title','type','displayMode','seasons','selectedEpisode','related',
            'playerEnabled','profileId','isWatchlisted','viewerAuthorized'
        ));
    }

    private function visible(Builder $query): Builder
    {
        return $query->where('status','published')->where(function (Builder $query): void {
            $query->whereNull('published_at')->orWhere('published_at','<=',now());
        });
    }
}
