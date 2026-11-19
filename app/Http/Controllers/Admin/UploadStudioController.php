<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Movie;
use App\Services\Media\PrivateVideoIntake;
use App\Services\Media\AutomaticPublicationService;
use App\Support\VideoUploadLimit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class UploadStudioController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $this->requireAdmin($request);

        $selection = $request->validate([
            'type' => ['required_with:title_id', 'nullable', Rule::in(['movie', 'episode'])],
            'title_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $resume = null;
        if ($request->query('upload') !== null) {
            abort_unless(ctype_digit((string) $request->query('upload')), 404);
            $resume = DB::table('media_ingests')
                ->where('id', (int) $request->query('upload'))
                ->where('uploaded_by', (int) $request->user()->id)->first();
            abort_unless($resume, 404);
            abort_unless(in_array($resume->ingestable_type, ['movie', 'episode'], true), 404);
            if ($resume->ingestable_type === 'movie' && Movie::query()->whereKey($resume->ingestable_id)
                ->where('is_standalone_video', true)->exists()) {
                return redirect()->route('upload-studio.index', ['ingest' => $resume->id]);
            }
        }

        $movies = Movie::query()->where('is_standalone_video', false)->orderBy('title')->limit(2000)->get([
            'id', 'title', 'overview', 'release_year', 'runtime_minutes', 'is_premium', 'status',
            'poster_path', 'backdrop_path',
        ]);
        $episodes = Episode::query()->with('season.series')
            ->orderByDesc('id')->limit(2000)->get();

        $selectedType = $resume->ingestable_type ?? $selection['type'] ?? 'movie';
        $selectedId = (int) ($resume->ingestable_id ?? $selection['title_id'] ?? 0);
        if ($selectedId > 0 && $selectedType === 'movie' && !$movies->contains('id', $selectedId)) {
            $movies->push(Movie::query()->where('is_standalone_video', false)->findOrFail($selectedId));
        }
        if ($selectedId > 0 && $selectedType === 'episode' && !$episodes->contains('id', $selectedId)) {
            $episodes->push(Episode::query()->with('season.series')->findOrFail($selectedId));
        }

        return view('admin.upload-studio', [
            'movies' => $movies,
            'episodes' => $episodes,
            'maxUploadBytes' => VideoUploadLimit::effectiveBytes(),
            'selectedType' => $selectedType,
            'selectedId' => $selectedId,
            'resume' => $resume,
            'automaticPublicationEnabled' => app(AutomaticPublicationService::class)->enabled(),
        ]);
    }

    public function upload(Request $request, PrivateVideoIntake $intake): JsonResponse|RedirectResponse
    {
        $this->requireAdmin($request);
        $data = $request->validate([
            'type' => ['required', Rule::in(['movie', 'episode'])],
            'title_id' => ['required', 'integer', 'min:1'],
            'video' => VideoUploadLimit::fileRules(),
        ]);

        $model = $data['type'] === 'movie' ? Movie::class : Episode::class;
        $model::query()
            ->when($data['type'] === 'movie', fn ($query) => $query->where('is_standalone_video', false))
            ->findOrFail($data['title_id']);

        $id = $intake->store($data['type'], (int) $data['title_id'],
            $data['video'], (int) $request->user()->id, $request->ip());

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Video uploaded successfully. Saved to your private Media Library.',
                'automatic_publication_enabled' => app(AutomaticPublicationService::class)->enabled(),
                'upload_id' => $id,
                'library_url' => route('admin.media.index'),
                'editor_url' => route('upload-studio.index', ['upload' => $id]),
            ], 201)->header('Cache-Control', 'private, no-store');
        }
        return redirect()->route('upload-studio.advanced', ['upload' => $id])
            ->with('status', 'Video uploaded successfully. Saved to your private Media Library.');
    }

    private function requireAdmin(Request $request): void
    {
        abort_unless($request->user()
            && in_array($request->user()->role, ['admin', 'super_admin'], true), 403);
    }
}
