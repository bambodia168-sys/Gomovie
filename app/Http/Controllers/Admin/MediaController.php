<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Series;
use App\Services\Media\PrivateVideoIntake;
use App\Services\Media\AutomaticPublicationService;
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
        $assets = DB::table('media_ingests')
            ->join('users', 'users.id', '=', 'media_ingests.uploaded_by')
            ->select('media_ingests.id', 'media_ingests.original_name', 'media_ingests.mime',
                'media_ingests.size_bytes', 'media_ingests.status', 'media_ingests.created_at',
                'media_ingests.uploaded_by', 'media_ingests.ingestable_type',
                'media_ingests.ingestable_id', 'media_ingests.encoded_manifest_key',
                'users.name as uploader')
            ->orderByDesc('media_ingests.id')->paginate(10)->withQueryString();

        $auto = app(AutomaticPublicationService::class);
        foreach ($assets as $asset) {
            $asset->public = false;
            if ($asset->status !== 'encoded' || !$asset->encoded_manifest_key) continue;
            $ready = $auto->readyAsset((string) $asset->ingestable_type, (int) $asset->ingestable_id);
            $asset->public = $ready && hash_equals(
                (string) $ready->manifest_key, (string) $asset->encoded_manifest_key);
            $asset->watch_url = null;
            if ($asset->public && $asset->ingestable_type === 'movie') {
                $slug = Movie::query()->whereKey($asset->ingestable_id)->value('slug');
                if ($slug) $asset->watch_url = route('watch.show', ['type' => 'movie', 'slug' => $slug]);
            } elseif ($asset->public && $asset->ingestable_type === 'episode') {
                $episode = Episode::query()->with('season.series')->find($asset->ingestable_id);
                if ($episode?->season?->series?->slug) {
                    $asset->watch_url = route('watch.show', [
                        'type' => 'series', 'slug' => $episode->season->series->slug,
                        'episode' => $episode->id,
                    ]);
                }
            }
        }

        return view('admin.media', [
            'movies' => Movie::orderBy('title')->limit(500)->get(['id','title']),
            'episodes' => Episode::orderByDesc('id')->limit(500)->get(['id','title']),
            'assets' => $assets,
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

    public function ingest(Request $request, PrivateVideoIntake $intake): RedirectResponse
    {
        abort_unless(in_array($request->user()->role, ['admin', 'super_admin'], true), 403);
        $data = $request->validate([
            'type' => ['required', Rule::in(['movie', 'episode'])],
            'title_id' => ['required', 'integer', 'min:1'],
            'video' => ['required', 'file', 'mimes:mp4,mov,mkv,webm', 'max:131072'],
        ]);
        $model = $data['type'] === 'movie' ? Movie::class : Episode::class;
        $model::findOrFail($data['title_id']);
        $intake->store($data['type'], (int) $data['title_id'],
            $data['video'], (int) $request->user()->id, $request->ip());

        return redirect()->route('upload-studio.index')
            ->with('status', 'Video uploaded successfully. Saved to your private Media Library.');
    }

}
