<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Movie;
use App\Services\Media\IntakeReviewService;
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

        return view('admin.upload-studio', [
            'movies' => Movie::query()->orderBy('title')->limit(2000)->get(['id', 'title']),
            'episodes' => Episode::query()->with('season.series')->orderByDesc('id')->limit(2000)->get(),
            'uploads' => DB::table('media_ingests')
                ->join('users', 'users.id', '=', 'media_ingests.uploaded_by')
                ->select('media_ingests.id', 'media_ingests.ingestable_type',
                    'media_ingests.original_name', 'media_ingests.status',
                    'media_ingests.cloud_status', 'media_ingests.size_bytes',
                    'media_ingests.created_at', 'users.name as uploader')
                ->orderByDesc('media_ingests.id')->paginate(15),
            'r2Configured' => config('gomovie.r2_enabled') === true
                && filled(config('filesystems.disks.r2.key'))
                && filled(config('filesystems.disks.r2.secret'))
                && filled(config('gomovie.r2_bucket'))
                && (bool) preg_match('#\Ahttps://[a-f0-9]{32}\.r2\.cloudflarestorage\.com/?\z#D',
                    (string) config('gomovie.r2_endpoint')),
            'scannerReady' => is_file((string) config('gomovie.malware_scanner_binary'))
                && is_executable((string) config('gomovie.malware_scanner_binary'))
                && basename((string) config('gomovie.malware_scanner_binary')) === 'clamscan',
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
                'message' => 'Upload complete. The video is private and awaiting review.',
                'upload_id' => $id,
                'review_url' => route('admin.upload-studio.review', $id),
            ], 201)->header('Cache-Control', 'private, no-store');
        }
        return redirect()->route('admin.upload-studio.index')
            ->with('status', 'Video uploaded privately. Review it when ready.');
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

        return redirect()->route('admin.upload-studio.review', $ingest)
            ->with('status', 'Upload approved for PRIVATE processing. It is not publicly playable.');
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

    private function requireAdmin(Request $request): void
    {
        abort_unless($request->user()
            && in_array($request->user()->role, ['admin', 'super_admin'], true), 403);
    }
}
