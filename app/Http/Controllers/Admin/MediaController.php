<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Series;
use App\Services\Media\PrivateVideoIntake;
use App\Services\Media\PublicationRecovery;
use App\Support\SiteSettings;
use App\Support\StudioNavigation;
use App\Support\VideoUploadLimit;
use App\Services\Media\AutomaticPublicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class MediaController extends Controller
{
    public function index(Request $request, PublicationRecovery $recovery): View
    {
        $data = $request->validate(['ingest' => ['nullable', 'integer', 'min:1']]);
        $ingestId = $data['ingest'] ?? null;

        $assets = DB::table('media_ingests')
            ->join('users', 'users.id', '=', 'media_ingests.uploaded_by')
            ->leftJoin('movies', function ($join): void {
                $join->on('movies.id', '=', 'media_ingests.ingestable_id')
                    ->where('media_ingests.ingestable_type', 'movie');
            })
            ->select('media_ingests.id', 'media_ingests.original_name', 'media_ingests.mime',
                'media_ingests.size_bytes', 'media_ingests.status', 'media_ingests.created_at',
                'media_ingests.uploaded_by', 'media_ingests.ingestable_type',
                'media_ingests.ingestable_id', 'media_ingests.encoded_manifest_key',
                'media_ingests.pipeline_error', 'media_ingests.updated_at', 'media_ingests.disk',
                'media_ingests.encoded_at', 'media_ingests.approved_at', 'media_ingests.processing_started_at',
                'media_ingests.auto_publication_requested', 'media_ingests.security_scan_status',
                'media_ingests.rights_review_status', 'media_ingests.publication_retry_token',
                'media_ingests.publication_retry_queued_at', 'users.name as uploader',
                'movies.is_standalone_video', 'movies.slug as movie_slug',
                'movies.status as movie_status', 'movies.published_at as movie_published_at')
            ->when($ingestId, fn ($query) => $query->where('media_ingests.id', $ingestId))
            ->orderByDesc('media_ingests.id')->paginate(10)->withQueryString();

        $auto = app(AutomaticPublicationService::class);
        $retriesEnabled = $auto->enabled() && SiteSettings::get('transcoding_enabled') === true;
        foreach ($assets as $asset) {
            $asset->public = false;
            $asset->retry_pending = $recovery->retryPending($asset);
            $asset->can_retry = $retriesEnabled && $recovery->canRetry($asset);
            $asset->watch_url = $asset->is_standalone_video && $asset->movie_status === 'published'
                && (!$asset->movie_published_at || $asset->movie_published_at <= now()->toDateTimeString())
                ? route('watch.show', ['type' => 'movie', 'slug' => $asset->movie_slug]) : null;
            if ($asset->status !== 'encoded' || !$asset->encoded_manifest_key) continue;
            $ready = $auto->readyAsset((string) $asset->ingestable_type, (int) $asset->ingestable_id);
            $asset->public = $ready && hash_equals(
                (string) $ready->manifest_key, (string) $asset->encoded_manifest_key);
            if ($asset->public && $asset->ingestable_type === 'movie' && $asset->movie_slug) {
                $asset->watch_url = route('watch.show', ['type' => 'movie', 'slug' => $asset->movie_slug]);
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
            'ingestId' => $ingestId,
        ]);
    }

    public function retry(Request $request, int $ingest, PublicationRecovery $recovery): RedirectResponse
    {
        abort_unless(StudioNavigation::administrator($request->user()), 403);
        $redirect = redirect()->route('admin.media.index', ['ingest' => $ingest]);
        try {
            $scheduled = $recovery->queue($ingest, (int) $request->user()->id);
        } catch (HttpExceptionInterface $error) {
            if ($request->expectsJson() || !in_array($error->getStatusCode(), [409, 503], true)) {
                throw $error;
            }

            return $redirect->withErrors(['retry' => $error->getMessage()]);
        }

        return $redirect->with('status', $scheduled ? 'Processing retry queued.' : 'A processing retry is already queued or running.');
    }

    public function artwork(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required',Rule::in(['movie','series'])],
            'upload_only' => ['sometimes', 'boolean'],
            'title_id' => [Rule::requiredIf(!$request->boolean('upload_only')), 'integer', 'min:1'],
            'slot' => ['required',Rule::in(['poster','backdrop'])],
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);
        $uploadOnly = (bool) ($data['upload_only'] ?? false);
        $model = $data['type']==='movie' ? Movie::class : Series::class;
        $title = $uploadOnly ? null : $model::findOrFail($data['title_id']);
        $field = $data['slot'].'_path';
        $path = $data['image']->store('artwork/'.$data['type'], 'public');
        abort_unless($path,503,'Artwork storage failed.');

        try {
            DB::transaction(function () use ($title,$data,$field,$path,$request): void {
                $title?->update([$field => $path]);
                DB::table('admin_audit_events')->insert([
                    'actor_id'=>$request->user()->id,
                    'action'=>$title ? 'media.artwork_uploaded' : 'media.artwork_stored',
                    'resource_type'=>$data['type'],
                    'resource_id'=>$title?->id,'details'=>json_encode(['slot'=>$field,'file'=>$path]),
                    'ip_address'=>$request->ip(),'created_at'=>now(),'updated_at'=>now(),
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }

        if ($uploadOnly) {
            return response()->json(['url' => Storage::disk('public')->url($path)], 201);
        }

        return back()->with('status','Artwork uploaded. Media usage rights remain the operator’s responsibility.');
    }

    public function ingest(Request $request, PrivateVideoIntake $intake): RedirectResponse
    {
        abort_unless(in_array($request->user()->role, ['admin', 'super_admin'], true), 403);
        $data = $request->validate([
            'type' => ['required', Rule::in(['movie', 'episode'])],
            'title_id' => ['required', 'integer', 'min:1'],
            'video' => VideoUploadLimit::fileRules(),
        ]);
        $model = $data['type'] === 'movie' ? Movie::class : Episode::class;
        $model::query()
            ->when($data['type'] === 'movie', fn ($query) => $query->where('is_standalone_video', false))
            ->findOrFail($data['title_id']);
        $intake->store($data['type'], (int) $data['title_id'],
            $data['video'], (int) $request->user()->id, $request->ip());

        return redirect()->route('upload-studio.index')
            ->with('status', 'Video uploaded successfully. Saved to your private Media Library.');
    }

}
