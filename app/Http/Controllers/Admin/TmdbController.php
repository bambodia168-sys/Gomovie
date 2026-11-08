<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\Series;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TmdbController extends Controller
{
    private function metadata(string $type,int $tmdbId): array
    {
        $token = (string) config('gomovie.tmdb_token');
        abort_if(strlen($token)<20,503,'TMDB API is not configured.');
        $endpoint = $type==='movie' ? 'movie' : 'tv';
        $response = Http::withToken($token)->acceptJson()->timeout(12)
            ->get('https://api.themoviedb.org/3/'.$endpoint.'/'.$tmdbId,['language'=>'en-US']);
        abort_unless($response->successful(),502,'TMDB request failed.');

        $result = $response->json();
        abort_unless(is_array($result) && isset($result['id']),502,'Unexpected TMDB response.');

        $title = Str::limit((string) ($result['title'] ?? $result['name'] ?? ''),255,'');
        abort_if($title==='',502,'TMDB title is missing.');
        return [
            'tmdb_id'=>(int)$result['id'],
            'title'=>$title,
            'overview'=>Str::limit((string)($result['overview']??''),12000,''),
            'release_year'=>$type==='movie' ? (int)substr((string)($result['release_date']??''),0,4) : null,
            'first_air_date'=>$type==='series' ? ($result['first_air_date'] ?: null) : null,
            'runtime_minutes'=>$type==='movie' ? ($result['runtime'] ?: null) : null,
            'poster_path'=>isset($result['poster_path']) && preg_match('#^/[a-zA-Z0-9/_\.-]+$#',$result['poster_path']) ? $result['poster_path'] : null,
            'backdrop_path'=>isset($result['backdrop_path']) && preg_match('#^/[a-zA-Z0-9/_\.-]+$#',$result['backdrop_path']) ? $result['backdrop_path'] : null,
        ];
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'type'=>['required',Rule::in(['movie','series'])],
            'tmdb_id'=>['required','integer','min:1','max:2147483647'],
        ]);
        return response()->json(['data'=>$this->metadata($data['type'],(int)$data['tmdb_id'])])
            ->header('Cache-Control','private, no-store');
    }

    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type'=>['required',Rule::in(['movie','series'])],
            'tmdb_id'=>['required','integer','min:1','max:2147483647'],
        ]);
        $record = $this->metadata($data['type'],(int)$data['tmdb_id']);
        $model = $data['type']==='movie' ? Movie::class : Series::class;
        $exists = $model::query()->where('tmdb_id',$record['tmdb_id'])->exists();
        if ($exists) throw ValidationException::withMessages(['tmdb_id'=>'This TMDB title has already been imported.']);
        $slug = Str::slug($record['title']).'-'.$record['tmdb_id'];
        $title = DB::transaction(function () use ($model,$record,$slug,$data,$request) {
            $input = [
                'tmdb_id'=>$record['tmdb_id'],'title'=>$record['title'],
                'slug'=>$slug,'overview'=>$record['overview'],'poster_path'=>$record['poster_path'],
                'backdrop_path'=>$record['backdrop_path'],'is_premium'=>true,'status'=>'draft',
            ];
            if ($data['type']==='movie') {
                $input['release_year']=$record['release_year'] ?: null;
                $input['runtime_minutes']=$record['runtime_minutes'];
            } else {
                $input['first_air_date']=$record['first_air_date'];
            }
            $title = $model::query()->create($input);
            DB::table('admin_audit_events')->insert([
                'actor_id'=>$request->user()->id,'action'=>'tmdb.import_draft',
                'resource_type'=>$data['type'],'resource_id'=>$title->id,
                'details'=>json_encode(['tmdb_id'=>$record['tmdb_id']]),
                'ip_address'=>$request->ip(),'created_at'=>now(),'updated_at'=>now(),
            ]);
            return $title;
        });
        return redirect()->route('admin.catalog.edit',[$data['type']==='movie'?'movies':'series',$title->id])
            ->with('status','Metadata imported as draft. TMDB content never grants streaming rights.');
    }
}
