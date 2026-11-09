<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Movie;
use App\Services\Media\PrivateVideoIntake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class UploadStudioController extends Controller
{
    public function index(Request $request): View
    {
        $this->requireAdmin($request);

        // Advertise the effective PHP request limit to the browser. Keep the
        // original private-intake ceiling at 128 MB until resumable uploads ship.
        $max = 128 * 1048576;
        $fileMax = $this->iniBytes(ini_get('upload_max_filesize'));
        $postMax = $this->iniBytes(ini_get('post_max_size'));
        if ($fileMax > 0) $max = min($max, $fileMax);
        if ($postMax > 0) $max = min($max, max(1, $postMax - 65536));

        $resume = null;
        if ($request->query('upload') !== null) {
            abort_unless(ctype_digit((string) $request->query('upload')), 404);
            $resume = DB::table('media_ingests')
                ->where('id', (int) $request->query('upload'))
                ->where('uploaded_by', (int) $request->user()->id)->first();
            abort_unless($resume, 404);
            abort_unless(in_array($resume->ingestable_type, ['movie', 'episode'], true), 404);
        }

        $movies = Movie::query()->orderBy('title')->limit(2000)->get([
            'id', 'title', 'overview', 'release_year', 'runtime_minutes', 'is_premium', 'status',
        ]);
        $episodes = Episode::query()->with('season.series')
            ->orderByDesc('id')->limit(2000)->get();

        // Always include the user's resumed upload, even in a large catalog.
        if ($resume && $resume->ingestable_type === 'movie'
            && !$movies->contains('id', (int) $resume->ingestable_id)) {
            $movies->push(Movie::query()->findOrFail((int) $resume->ingestable_id));
        }
        if ($resume && $resume->ingestable_type === 'episode'
            && !$episodes->contains('id', (int) $resume->ingestable_id)) {
            $episodes->push(Episode::query()->with('season.series')
                ->findOrFail((int) $resume->ingestable_id));
        }

        return view('admin.upload-studio', [
            'movies' => $movies,
            'episodes' => $episodes,
            'maxUploadBytes' => $max,
            'resume' => $resume,
        ]);
    }

    public function upload(Request $request, PrivateVideoIntake $intake): JsonResponse|RedirectResponse
    {
        $this->requireAdmin($request);
        $data = $request->validate([
            'type' => ['required', Rule::in(['movie', 'episode'])],
            'title_id' => ['required', 'integer', 'min:1'],
            'video' => ['required', 'file', 'mimes:mp4,mov,mkv,webm', 'max:131072'],
        ]);

        $model = $data['type'] === 'movie' ? Movie::class : Episode::class;
        $model::query()->findOrFail($data['title_id']);

        $id = $intake->store($data['type'], (int) $data['title_id'],
            $data['video'], (int) $request->user()->id, $request->ip());

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Video uploaded successfully. Saved to your private Media Library.',
                'upload_id' => $id,
                'library_url' => route('admin.media.index'),
                'editor_url' => route('upload-studio.index', ['upload' => $id]),
            ], 201)->header('Cache-Control', 'private, no-store');
        }
        return redirect()->route('upload-studio.index')
            ->with('status', 'Video uploaded successfully. Saved to your private Media Library.');
    }

    private function iniBytes(string|false $value): int
    {
        if (!is_string($value) || !preg_match('/\A(\d+)([KMG]?)\z/iD', trim($value), $parts)) {
            return 0;
        }
        $multiplier = match (strtoupper($parts[2])) {
            'K' => 1024,
            'M' => 1048576,
            'G' => 1073741824,
            default => 1,
        };
        return min(PHP_INT_MAX, (int) $parts[1] * $multiplier);
    }

    private function requireAdmin(Request $request): void
    {
        abort_unless($request->user()
            && in_array($request->user()->role, ['admin', 'super_admin'], true), 403);
    }
}
