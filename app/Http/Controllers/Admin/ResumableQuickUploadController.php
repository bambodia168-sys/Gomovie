<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Media\PrivateVideoIntake;
use App\Services\Media\ResumableUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Authenticated private-only resumable upload extension of QuickUpload.
 * It does not scan, approve, license, publish or expose any video.
 */
final class ResumableQuickUploadController extends Controller
{
    private function gate(Request $request): void
    {
        abort_unless(config('gomovie.resumable_uploads_enabled') === true, 404);
        abort_unless($request->user()
            && in_array($request->user()->role, ['admin','super_admin'], true), 403);
    }

    public function start(Request $request, ResumableUploadService $uploads): JsonResponse
    {
        $this->gate($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string', 'max:12000'],
            'access' => ['required', Rule::in(['private'])],
            'rights_confirmed' => ['accepted'],
            'filename' => ['required', 'string', 'max:255'],
            'size_bytes' => ['required', 'integer', 'min:1', 'max:'.ResumableUploadService::MAX_BYTES],
        ]);
        abort_unless(preg_match('/\.(mp4|mov|mkv|webm)\z/iD', $data['filename']), 422,
            'Choose an MP4, MOV, MKV or WebM file.');
        $session = $uploads->start($request->user(), $data);
        return $this->result($session, 201);
    }

    public function status(Request $request, string $session, ResumableUploadService $uploads): JsonResponse
    {
        $this->gate($request);
        return $this->result($uploads->status($request->user(), $session), 200);
    }

    public function append(Request $request, string $session, ResumableUploadService $uploads): JsonResponse
    {
        $this->gate($request);
        $data = $request->validate([
            'offset' => ['required', 'integer', 'min:0'],
            'sha256' => ['required', 'regex:/\A[a-f0-9]{64}\z/D'],
            'chunk' => ['required', 'file', 'max:1024'],
        ]);
        return $this->result($uploads->append($request->user(), $session,
            (int) $data['offset'], $data['chunk'], (string) $data['sha256']), 200);
    }

    public function complete(
        Request $request, string $session, ResumableUploadService $uploads,
        QuickUploadController $quick, PrivateVideoIntake $intake
    ): JsonResponse {
        $this->gate($request);
        $result = $uploads->prepareComplete($request->user(), $session);
        if ($result['existing']) return $this->result($result['session'], 200);

        $source = $result['source_path'];
        $record = $result['session'];
        // Only an assembled, server-verified PRIVATE file enters the existing
        // QuickUpload controller and its normal validation/rights/service path.
        $request->request->replace([
            'title' => $record->title,
            'description' => $record->description ?? '',
            'access' => 'private',
            'rights_confirmed' => '1',
        ]);
        $request->files->set('video',
            new UploadedFile($source, $record->original_name, null, null, true));
        $request->headers->set('Accept', 'application/json');
        $request->attributes->set('gomovie.resumable_session_id', $session);

        $response = $quick->store($request, $intake);
        abort_unless($response instanceof JsonResponse && $response->getStatusCode() === 201, 503);
        $body = $response->getData(true);
        abort_unless(($body['ok'] ?? false) === true
            && (int) ($body['upload_id'] ?? 0) > 0
            && (int) ($body['movie_id'] ?? 0) > 0
            && ($body['access'] ?? null) === 'private'
            && ($body['will_publish_automatically'] ?? true) === false, 503,
            'Private intake confirmation is unavailable.');
        $uploads->markCompleted($request->user(), $session,
            (int) $body['movie_id'], (int) $body['upload_id']);

        return $this->result($uploads->status($request->user(), $session), 201);
    }

    public function cancel(Request $request, string $session, ResumableUploadService $uploads): JsonResponse
    {
        $this->gate($request);
        $uploads->cancel($request->user(), $session);
        return response()->json(['ok' => true, 'status' => 'cancelled'])
            ->header('Cache-Control','private, no-store');
    }

    private function result(object $session, int $code): JsonResponse
    {
        $value = [
            'ok' => true,
            'session_id' => $session->id,
            'status' => $session->status,
            'chunk_size' => (int) $session->chunk_size,
            'size_bytes' => (int) $session->total_bytes,
            'received_bytes' => (int) $session->received_bytes,
            'expires_at' => $session->expires_at,
            'upload_id' => $session->media_ingest_id ? (int) $session->media_ingest_id : null,
            'movie_id' => $session->movie_id ? (int) $session->movie_id : null,
        ];
        if ($value['upload_id']) {
            $value['status_url'] = route('upload-studio.status', ['ingest' => $value['upload_id']]);
            $value['media_library_url'] = route('admin.media.index');
        }
        return response()->json($value, $code)
            ->header('Cache-Control','private, no-store');
    }
}
