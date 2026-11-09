<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Season;
use App\Models\Series;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EpisodeStudioController extends Controller
{
    public function index(Series $series): View
    {
        // Each season and its episodes have independent, bounded pagination.
        // Keeping per-season page names prevents one table from jumping the others.
        $seasons = $series->seasons()->withCount('episodes')
            ->orderBy('season_number')->orderBy('id')
            ->paginate(10, ['*'], 'seasons_page')->withQueryString();

        $episodePages = [];
        foreach ($seasons as $season) {
            $episodePages[$season->id] = $season->episodes()
                ->orderBy('episode_number')->orderBy('id')
                ->paginate(10, ['*'], 'episodes_'.$season->id)
                ->withQueryString();
        }

        return view('admin.series.episodes', compact('series', 'seasons', 'episodePages'));
    }

    public function storeSeason(Request $request,Series $series): RedirectResponse
    {
        $data=$request->validate([
            'season_number'=>['required','integer','min:0','max:9999',
                Rule::unique('seasons')->where('series_id',$series->id)],
            'title'=>['required','string','max:255'],
        ]);
        DB::transaction(function () use ($series,$request,$data): void {
            $season=$series->seasons()->create($data);
            $this->audit($request,'season.created',$season->id);
        });
        return back()->with('status','Season created.');
    }

    public function updateSeason(Request $request,Series $series,Season $season): RedirectResponse
    {
        $this->assertSeason($series,$season);
        $data=$request->validate([
            'season_number'=>['required','integer','min:0','max:9999',
                Rule::unique('seasons')->where('series_id',$series->id)->ignore($season->id)],
            'title'=>['required','string','max:255'],
        ]);
        DB::transaction(function () use ($season,$request,$data): void {
            $season->update($data);
            $this->audit($request,'season.updated',$season->id);
        });
        return back()->with('status','Season updated.');
    }

    public function destroySeason(Request $request,Series $series,Season $season): RedirectResponse
    {
        $this->assertSeason($series,$season);
        $this->requireAdministrator($request);
        abort_if($season->episodes()->exists(),422,'Delete or archive episodes first.');
        DB::transaction(function () use ($season,$request): void {
            $id=$season->id;
            $season->delete();
            $this->audit($request,'season.deleted',$id);
        });
        return back()->with('status','Empty season deleted.');
    }

    public function storeEpisode(Request $request,Series $series,Season $season): RedirectResponse
    {
        $this->assertSeason($series,$season);
        $data=$this->validateEpisode($request,$series,$season);
        DB::transaction(function () use ($season,$request,$data): void {
            $episode=$season->episodes()->create($data);
            $this->audit($request,'episode.created',$episode->id);
        });
        return back()->with('status','Episode created.');
    }

    public function updateEpisode(Request $request,Series $series,Season $season,Episode $episode): RedirectResponse
    {
        $this->assertEpisode($series,$season,$episode);
        $data=$this->validateEpisode($request,$series,$season,$episode->id);
        DB::transaction(function () use ($episode,$request,$data): void {
            $episode->update($data);
            $this->audit($request,'episode.updated',$episode->id);
        });
        return back()->with('status','Episode updated.');
    }

    public function destroyEpisode(Request $request,Series $series,Season $season,Episode $episode): RedirectResponse
    {
        $this->assertEpisode($series,$season,$episode);
        $this->requireAdministrator($request);
        abort_unless($episode->status==='draft',422,'Archive published episodes rather than delete them.');
        abort_if($episode->mediaAssets()->exists(),422,'Cannot delete episodes with video assets.');
        abort_if(DB::table('media_ingests')->where('ingestable_type','episode')->where('ingestable_id',$episode->id)->exists(),
            422,'Cannot delete episodes with staged ingests.');
        abort_if(DB::table('content_rights')->where('licensable_type','episode')->where('licensable_id',$episode->id)->exists(),
            422,'Cannot delete episodes with rights records.');
        DB::transaction(function () use ($request,$episode): void {
            $id=$episode->id;
            $episode->delete();
            $this->audit($request,'episode.deleted',$id);
        });
        return back()->with('status','Draft episode deleted.');
    }

    private function validateEpisode(Request $request,Series $series,Season $season,?int $id=null): array
    {
        $data=$request->validate([
            'episode_number'=>['required','integer','min:1','max:10000',
                Rule::unique('episodes')->where('season_id',$season->id)->ignore($id)],
            'title'=>['required','string','max:255'],
            'overview'=>['nullable','string','max:12000'],
            'runtime_minutes'=>['nullable','integer','min:1','max:1440'],
            'air_date'=>['nullable','date_format:Y-m-d'],
            'still_path'=>['nullable','string','max:1024'],
            'status'=>['required',Rule::in(['draft','published','archived'])],
        ]);
        if ($data['status']==='published') {
            abort_unless($series->status==='published',422,'Publish the series before individual episodes.');
        }
        $data['published_at']=$data['status']==='published' ? now() : null;
        return $data;
    }

    private function requireAdministrator(Request $request): void
    {
        abort_unless(in_array($request->user()->role,['admin','super_admin'],true),403);
    }

    private function assertSeason(Series $series,Season $season): void
    {
        abort_unless((int)$season->series_id===(int)$series->id,404);
    }

    private function assertEpisode(Series $series,Season $season,Episode $episode): void
    {
        $this->assertSeason($series,$season);
        abort_unless((int)$episode->season_id===(int)$season->id,404);
    }

    private function audit(Request $request,string $action,int $resourceId): void
    {
        DB::table('admin_audit_events')->insert([
            'actor_id'=>$request->user()->id,'action'=>$action,
            'resource_type'=>str_starts_with($action,'episode')?'episode':'season',
            'resource_id'=>$resourceId,'ip_address'=>$request->ip(),
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }
}
