<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Movie;
use App\Services\Media\AutomaticPublicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Throwable;

/**
 * Optional post-upload catalog editing for the uploader's own private ingest.
 * This controller cannot change media approval, encoding, rights, or playback.
 */
final class UploadStudioWorkflowController extends Controller
{
    private function ingest(Request $request, int $ingest): object
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'super_admin'], true), 403);

        $record = DB::table('media_ingests')->where('id', $ingest)
            ->where('uploaded_by', (int) $request->user()->id)->first();

        abort_unless($record, 404);
        abort_unless(in_array($record->ingestable_type, ['movie', 'episode'], true), 404);

        return $record;
    }

    private function target(object $record): Movie|Episode
    {
        return $record->ingestable_type === 'movie'
            ? Movie::query()->findOrFail((int) $record->ingestable_id)
            : Episode::query()->with('season.series')->findOrFail((int) $record->ingestable_id);
    }

    private function requireDraft(object $record, Movie|Episode $target): void
    {
        abort_unless($record->status === 'quarantined', 409,
            'This upload is no longer editable.');
        abort_unless($target->status === 'draft', 409,
            'This title is not a draft. Edit published catalog metadata in Catalog Manager.');
    }

    private function audit(Request $request, int $ingest, string $action, array $fields = []): void
    {
        DB::table('admin_audit_events')->insert([
            'actor_id' => (int) $request->user()->id,
            'action' => $action,
            'resource_type' => 'media_ingest',
            'resource_id' => $ingest,
            'details' => json_encode($fields, JSON_THROW_ON_ERROR),
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function state(Request $request, int $ingest): JsonResponse
    {
        $record = $this->ingest($request, $ingest);
        $target = $this->target($record);
        $auto = app(AutomaticPublicationService::class);
        $ready = $auto->readyAsset((string) $record->ingestable_type, (int) $record->ingestable_id);
        $watchUrl = null;
        if ($ready) {
            $watchUrl = $record->ingestable_type === 'movie'
                ? route('watch.show', ['type' => 'movie', 'slug' => $target->slug])
                : route('watch.show', ['type' => 'series',
                    'slug' => $target->season->series->slug, 'episode' => $target->id]);
        }
        return response()->json([
            'ok' => true,
            'public' => (bool) $ready,
            'watch_url' => $watchUrl,
            'publication_error' => $record->pipeline_error,
            'automatic_publication_enabled' => $auto->enabled(),
            'upload_id' => $record->id,
            'type' => $record->ingestable_type,
            'title_id' => $record->ingestable_id,
            'status' => $record->status,
            'can_edit' => $record->status === 'quarantined' && $target->status === 'draft',
            'catalog_status' => $target->status,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function details(Request $request, int $ingest): JsonResponse
    {
        $record = $this->ingest($request, $ingest);
        $target = $this->target($record);
        $this->requireDraft($record, $target);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'overview' => ['nullable', 'string', 'max:12000'],
            'runtime_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'release_year' => ['nullable', 'integer', 'between:1888,2100'],
        ]);
        if ($record->ingestable_type === 'episode') {
            unset($data['release_year']);
        }

        // Only update actual existing catalog fields. Preserve slug and status.
        DB::transaction(function () use ($record, $target, $data, $request): void {
            $locked = $target->newQuery()->lockForUpdate()->findOrFail($target->id);
            abort_unless($locked->status === 'draft', 409,
                'Catalog title changed. Refresh before editing.');
            $locked->fill($data);
            $locked->save();
            $this->audit($request, (int) $record->id, 'media.ingest_catalog_details_updated',
                ['fields' => array_keys($data)]);
        });

        return response()->json([
            'ok' => true,
            'message' => 'Title details saved.',
            'title' => $data['title'],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function artwork(Request $request, int $ingest): JsonResponse
    {
        $record = $this->ingest($request, $ingest);
        $target = $this->target($record);
        $this->requireDraft($record, $target);

        $data = $request->validate([
            'slot' => ['required', Rule::in(['poster', 'backdrop', 'still'])],
            'artwork' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);
        if ($record->ingestable_type === 'episode') {
            abort_unless($data['slot'] === 'still', 422,
                'Episodes support a still image only.');
        } else {
            abort_unless(in_array($data['slot'], ['poster', 'backdrop'], true), 422,
                'Movies support poster or backdrop images.');
        }

        $image = $data['artwork'];
        $path = $image->store('artwork/upload-studio/'.$record->ingestable_type, 'public');
        abort_unless(is_string($path) && $path !== '', 503, 'Artwork could not be saved.');

        $field = $data['slot'].'_path';
        try {
            DB::transaction(function () use ($target, $record, $field, $path, $request, $data): void {
                $locked = $target->newQuery()->lockForUpdate()->findOrFail($target->id);
                abort_unless($locked->status === 'draft', 409,
                    'Catalog title changed. Refresh before editing.');
                $locked->forceFill([$field => $path])->save();
                $this->audit($request, (int) $record->id, 'media.ingest_catalog_artwork_updated',
                    ['slot' => $data['slot'], 'path' => $path]);
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }

        return response()->json([
            'ok' => true,
            'message' => 'Artwork saved.',
            'slot' => $data['slot'],
            'artwork_url' => Storage::disk('public')->url($path),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function access(Request $request, int $ingest): JsonResponse
    {
        $record = $this->ingest($request, $ingest);
        $target = $this->target($record);
        $this->requireDraft($record, $target);

        $data = $request->validate([
            'is_premium' => ['required', 'boolean'],
        ]);

        // An episode inherits the premium catalog flag of its parent series.
        $catalog = $target instanceof Movie ? $target : $target->season?->series;
        abort_unless($catalog, 404);

        DB::transaction(function () use ($catalog, $record, $request, $data): void {
            $locked = $catalog->newQuery()->lockForUpdate()->findOrFail($catalog->id);
            abort_unless($locked->status === 'draft', 409,
                'Catalog access can only be edited for draft titles.');
            $locked->forceFill(['is_premium' => (bool) $data['is_premium']])->save();
            $this->audit($request, (int) $record->id, 'media.ingest_catalog_access_updated',
                ['is_premium' => (bool) $data['is_premium']]);
        });

        return response()->json([
            'ok' => true,
            'message' => 'Catalog access saved. This does not activate billing or playback.',
            'is_premium' => (bool) $data['is_premium'],
        ])->header('Cache-Control', 'private, no-store');
    }
}
