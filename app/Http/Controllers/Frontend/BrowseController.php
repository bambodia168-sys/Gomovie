<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\UserProfile;
use App\Models\Series;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class BrowseController extends Controller
{
    public function home(): View
    {
        $featuredMovies = $this->visible(Movie::query())->latest('published_at')->limit(12)->get();
        $featuredSeries = $this->visible(Series::query())->latest('published_at')->limit(12)->get();
        $heroTitles = $featuredMovies->map(fn (Movie $item) => ['kind'=>'movie','item'=>$item])
            ->concat($featuredSeries->map(fn (Series $item) => ['kind'=>'series','item'=>$item]))
            ->sortByDesc(fn (array $entry) => $entry['item']->published_at?->timestamp
                ?? $entry['item']->created_at?->timestamp ?? 0)
            ->take(5)->values();

        return view('frontend.home', compact('featuredMovies','featuredSeries','heroTitles'));
    }

    public function index(Request $request): View
    {
        $data = $request->validate(['q' => ['nullable','string','max:100'], 'type'=>['nullable','in:all,movie,series']]);
        $type = $data['type'] ?? 'all';
        $q = trim($data['q'] ?? '');

        $movies = $type !== 'series'
            ? $this->visible(Movie::query())->when($q, fn ($query) => $query->where('title','like','%'.$q.'%'))->latest('published_at')->paginate(18)->withQueryString()
            : collect();
        $series = $type !== 'movie'
            ? $this->visible(Series::query())->when($q, fn ($query) => $query->where('title','like','%'.$q.'%'))->latest('published_at')->limit(18)->get()
            : collect();

        return view('frontend.browse', compact('movies','series','type','q'));
    }

    public function show(string $type, string $slug): View
    {
        abort_unless(in_array($type,['movie','series'],true),404);
        $model = $type === 'movie' ? Movie::query() : Series::query();
        $title = $this->visible($model)->where('slug',$slug)->firstOrFail();

        $viewerAuthorized = auth()->check() && auth()->user()->is_active && auth()->user()->hasVerifiedEmail();
        $profileId = $viewerAuthorized
            ? UserProfile::query()->where('user_id',auth()->id())->where('is_kids',false)->value('id')
            : null;
        $playerEnabled = config('gomovie.edge_enabled') === true && (bool) $profileId;
        $isWatchlisted = $profileId ? DB::table('watchlists')
            ->where('user_profile_id',$profileId)->where('watchable_type',$type)
            ->where('watchable_id',$title->id)->exists() : false;
        return view('frontend.detail', compact('title','type','playerEnabled','profileId','isWatchlisted'));
    }

    private function visible($query)
    {
        return $query->where('status','published')->where(function ($query): void {
            $query->whereNull('published_at')->orWhere('published_at','<=',now());
        });
    }
}
