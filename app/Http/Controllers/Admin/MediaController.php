<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Series;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function index(): View
    {
        return view('admin.media', [
            'movies' => Movie::orderBy('title')->limit(500)->get(['id','title']),
            'episodes' => Episode::orderByDesc('id')->limit(500)->get(['id','title']),
            'assets' => DB::table('media_ingests')
                ->join('users','users.id','=','media_ingests.uploaded_by')
                ->leftJoin('media_publication_reviews as reviews','reviews.media_ingest_id','=','media_ingests.id')
                ->leftJoin('media_publication_batches as batches','batches.media_ingest_id','=','media_ingests.id')
                ->select('media_ingests.id','media_ingests.original_name','media_ingests.mime',
                    'media_ingests.size_bytes','media_ingests.status','media_ingests.created_at',
                    'users.name as uploader','reviews.status as review_status','batches.status as staging_status')
                ->orderByDesc('media_ingests.id')->paginate(20),
        ]);
    }

    public function artwork(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required',Rule::in(['movie','series'])],
            'title_id' => ['required','integer','min:1'],
            'slot' => ['required',Rule::in(['poster','backdrop'])],
            'image' => ['required','file','mimes:jpg,jpeg,png,webp','max:8192'],
        ]);
        $model = $data['type']==='movie' ? Movie::class : Series::class;
        $title = $model::findOrFail($data['title_id']);
        $field = $data['slot'].'_path';
        $path = $data['image']->store('artwork/'.$data['type'], 'public');
        abort_unless($path,503,'Artwork storage failed.');

        try {
            DB::transaction(function () use ($title,$field,$path,$request): void {
                $title->update([$field => $path]);
                DB::table('admin_audit_events')->insert([
                    'actor_id'=>$request->user()->id,'action'=>'media.artwork_uploaded',
                    'resource_type'=>$title instanceof Movie ? 'movie' : 'series',
                    'resource_id'=>$title->id,'details'=>json_encode(['slot'=>$field,'file'=>$path]),
                    'ip_address'=>$request->ip(),'created_at'=>now(),'updated_at'=>now(),
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }

        return back()->with('status','Artwork uploaded. Media usage rights remain the operator’s responsibility.');
    }

    public function ingest(Request $request): RedirectResponse
    {
        abort_unless(in_array($request->user()->role,['admin','super_admin'],true),403);
        $data = $request->validate([
            'type' => ['required',Rule::in(['movie','episode'])],
            'title_id' => ['required','integer','min:1'],
            'video' => ['required','file','mimes:mp4,mov,mkv,webm','max:131072'],
        ]);
        $model = $data['type']==='movie' ? Movie::class : Episode::class;
        $model::findOrFail($data['title_id']);

        $file = $data['video'];
        $hash = hash_file('sha256',$file->getRealPath());
        $path = $file->storeAs('ingests/'.now()->format('Y/m'),(string) Str::uuid().'.'.$file->extension(),'local');
        abort_unless($path,503,'Private upload failed.');
        try {
            DB::transaction(function () use ($request,$data,$path,$file,$hash): void {
                $id = DB::table('media_ingests')->insertGetId([
                    'ingestable_type'=>$data['type'],'ingestable_id'=>$data['title_id'],
                    'uploaded_by'=>$request->user()->id,'disk'=>'local','object_key'=>$path,
                    'original_name'=>Str::limit($file->getClientOriginalName(),255,''),
                    'mime'=>$file->getMimeType() ?: 'application/octet-stream',
                    'size_bytes'=>$file->getSize(),'sha256'=>$hash,
                    'status'=>'quarantined','created_at'=>now(),'updated_at'=>now(),
                ]);
                DB::table('admin_audit_events')->insert([
                    'actor_id'=>$request->user()->id,'action'=>'media.private_ingest_staged',
                    'resource_type'=>'media_ingest','resource_id'=>$id,
                    'details'=>json_encode(['size_bytes'=>$file->getSize()]),
                    'ip_address'=>$request->ip(),'created_at'=>now(),'updated_at'=>now(),
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return back()->with('status','Private video uploaded into quarantine. No transcoding or playback is enabled.');
    }
}
