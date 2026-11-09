<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\TranscodeApprovedIngest;
use App\Models\Episode;
use App\Models\Movie;
use App\Services\Media\IntakeReviewService;
use App\Services\Media\PrivateVideoIntake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;
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

        return view('admin.upload-studio', [
            'movies' => Movie::query()->orderBy('title')->limit(2000)->get(['id', 'title']),
            'episodes' => Episode::query()->with('season.series')->orderByDesc('id')->limit(2000)->get(),
            'maxUploadBytes' => $max,
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
            ], 201)->header('Cache-Control', 'private, no-store');
        }
        return redirect()->route('upload-studio.index')
            ->with('status', 'Video uploaded successfully. Saved to your private Media Library.');
    }

    public function review(Request $request, int $ingest): View
    {
        $this->requireAdmin($request);
        $upload = DB::table('media_ingests')->where('id', $ingest)->first();
        abort_unless($upload, 404);

        return view('admin.upload-review', [
            'upload' => $upload,
            'scannerReady' => is_file((string) config('gomovie.malware_scanner_binary'))
                && is_executable((string) config('gomovie.malware_scanner_binary'))
                && basename((string) config('gomovie.malware_scanner_binary')) === 'clamscan',
        ]);
    }

    public function approve(Request $request, int $ingest, IntakeReviewService $review): RedirectResponse
    {
        $this->requireAdmin($request);
        $data = $request->validate([
            'rights_reference' => ['required', 'string', 'min:8', 'max:180',
                'regex:/\A[a-zA-Z0-9][a-zA-Z0-9:._\/-]+\z/D'],
            'rights_confirmed' => ['accepted'],
        ]);
        $review->approve($ingest, $request->user(), $data['rights_reference'], $request->ip());

        // The upload is deliberately simple. Once an administrator has
        // approved a clean, licensed ingest and the operator has enabled
        // the private encoding service, enqueue it automatically. Encoding
        // NEVER creates a public video or a ready playback asset.
        $message = 'Upload approved. Video remains private.';
        if (config('gomovie.transcoding_enabled') === true) {
            try {
                app(\App\Services\Media\TranscodeGate::class)->validate($ingest);
                TranscodeApprovedIngest::dispatch($ingest);
                $message = 'Upload approved. Private video processing has been queued.';
            } catch (Throwable $exception) {
                report($exception);
                $message = 'Upload approved, but processing could not be queued. Check System Health.';
            }
        } else {
            $message = 'Upload approved. Private processing will be available after setup.';
        }

        return redirect()->route('admin.upload-studio.review', $ingest)
            ->with('status', $message);
    }

    public function reject(Request $request, int $ingest, IntakeReviewService $review): RedirectResponse
    {
        $this->requireAdmin($request);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:8', 'max:1000'],
        ]);
        $review->reject($ingest, $request->user(), $data['reason'], $request->ip());

        return redirect()->route('admin.upload-studio.review', $ingest)
            ->with('status', 'Upload rejected and retained in private quarantine for operator action.');
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
