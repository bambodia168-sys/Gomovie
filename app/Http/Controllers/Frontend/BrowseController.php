<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\Series;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class BrowseController extends Controller
{
    public function home(): View
    {
        return view('frontend.home', [
            'featuredMovies' => $this->visible(Movie::query())->latest('published_at')->limit(12)->get(),
            'featuredSeries' => $this->visible(Series::query())->latest('published_at')->limit(12)->get(),
        ]);
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

        return view('frontend.detail', compact('title','type'));
    }

    private function visible($query)
    {
        return $query->where('status','published')->where(function ($query): void {
            $query->whereNull('published_at')->orWhere('published_at','<=',now());
        });
    }
}
