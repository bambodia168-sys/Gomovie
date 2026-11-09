<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Series;
use App\Models\UserProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LibraryController extends Controller
{
    public function index(Request $request, string $section): View
    {
        abort_unless(in_array($section, ['watchlist','history'], true), 404);
        $data = $request->validate(['profile_id'=>['sometimes','integer','min:1']]);
        $profiles = UserProfile::query()->where('user_id',$request->user()->id)
            ->orderBy('id')->get(['id','name','slug','is_kids']);
        $profile = isset($data['profile_id'])
            ? $profiles->firstWhere('id',(int) $data['profile_id'])
            : $profiles->first();
        abort_unless($profile,404);

        $table = $section === 'watchlist' ? 'watchlists' : 'watch_history';
        $query = DB::table($table)->where('user_profile_id',$profile->id);
        $records = $query->orderByDesc($section === 'watchlist' ? 'created_at' : 'last_watched_at')
            ->paginate(10)->withQueryString();

        $rows = $records->getCollection();
        $movieIds = $rows->where('watchable_type','movie')->pluck('watchable_id')->all();
        $seriesIds = $rows->where('watchable_type','series')->pluck('watchable_id')->all();
        $episodeIds = $rows->where('watchable_type','episode')->pluck('watchable_id')->all();
        $movies = Movie::query()->whereIn('id',$movieIds)->where('status','published')->get()->keyBy('id');
        $series = Series::query()->whereIn('id',$seriesIds)->where('status','published')->get()->keyBy('id');
        $episodes = Episode::query()->whereIn('id',$episodeIds)->where('status','published')
            ->with('season.series')->get()->keyBy('id');

        $entries = $rows->map(function ($row) use ($movies,$series,$episodes): ?array {
            $kind = $row->watchable_type;
            $item = match ($kind) {
                'movie' => $movies->get($row->watchable_id),
                'series' => $series->get($row->watchable_id),
                'episode' => $episodes->get($row->watchable_id),
                default => null,
            };
            if ($item instanceof Episode && $item->season?->series?->status !== 'published') {
                return null;
            }
            return $item ? ['row'=>$row,'kind'=>$kind,'item'=>$item] : null;
        })->filter()->values();

        return view('account.library', compact('profiles','profile','section','entries','records'));
    }

    public function toggle(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'profile_id'=>['required','integer','min:1'],
            'kind'=>['required',Rule::in(['movie','series'])],
            'title_id'=>['required','integer','min:1'],
            'action'=>['required',Rule::in(['add','remove'])],
        ]);
        $profile = UserProfile::query()->where('user_id',$request->user()->id)
            ->findOrFail($data['profile_id']);
        $model = $data['kind'] === 'movie' ? Movie::class : Series::class;
        $model::query()->where('status','published')->findOrFail($data['title_id']);
        $where = [
            'user_profile_id'=>$profile->id,
            'watchable_type'=>$data['kind'],
            'watchable_id'=>$data['title_id'],
        ];

        if ($data['action']==='add') {
            DB::table('watchlists')->insertOrIgnore($where + [
                'created_at'=>now(), 'updated_at'=>now(),
            ]);
        } else {
            DB::table('watchlists')->where($where)->delete();
        }
        return back()->with('status', $data['action']==='add'
            ? 'Saved to My List.' : 'Removed from My List.');
    }
    public function removeHistory(Request $request, int $record): RedirectResponse
    {
        $profileId=$request->validate([
            'profile_id'=>['required','integer','min:1'],
        ])['profile_id'];
        $profile=UserProfile::query()->where('user_id',$request->user()->id)
            ->findOrFail((int)$profileId);
        $row=DB::table('watch_history')->where('id',$record)
            ->where('user_id',$request->user()->id)
            ->where('user_profile_id',$profile->id)->firstOrFail();
        DB::table('watch_history')->where('id',$row->id)
            ->where('user_id',$request->user()->id)
            ->where('user_profile_id',$profile->id)->delete();
        return back()->with('status','Watch-history entry removed.');
    }

}
