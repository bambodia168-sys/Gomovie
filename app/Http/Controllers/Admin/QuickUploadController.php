<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Services\Media\PrivateVideoIntake;
use App\Services\Media\ResumableUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * One-screen upload for ORIGINAL videos. The legacy catalog editor still
 * handles existing movie/episode metadata; it is not a prerequisite here.
 *
 * "Free" records an uploader DECLARATION as pending review, never a
 * distribution license or authority to publish. Only independently reviewed
 * media may later enter the publication process.
 *
 * "Private" and "paid" stay private. Paid does not imply a working paywall.
 */
final class QuickUploadController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $this->requireAdmin($request);

        if ($request->filled('upload')) {
            return redirect()->route('upload-studio.advanced', [
                'upload' => $request->query('upload'),
            ]);
        }

        $max = 128 * 1048576;
        foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
            $bytes = $this->iniBytes(ini_get($setting));
            if ($bytes > 0) $max = min($max, $setting === 'post_max_size' ? max(1, $bytes - 65536) : $bytes);
        }

        $requested = $request->query('ingest');
        $ingestId = null;
        $resumeAccess = null;
        if ($requested !== null) {
            abort_unless(is_string($requested) && ctype_digit($requested), 404);
            $row = DB::table('media_ingests')->where('id', (int) $requested)
                ->where('uploaded_by', $request->user()->id)->first();
            abort_unless($row, 404);
            $ingestId = (int) $row->id;
            if ($row->ingestable_type === 'movie') {
                $record = DB::table('admin_audit_events')
                    ->where('actor_id', $request->user()->id)
                    ->where('resource_type', 'movie')
                    ->where('resource_id', $row->ingestable_id)
                    ->where('action', 'media.quick_upload_attested')->first();
                $details = $record ? json_decode((string) $record->details, true) : null;
                $access = is_array($details) ? ($details['access'] ?? null) : null;
                if (in_array($access, ['free', 'private', 'paid'], true)) $resumeAccess = $access;
            }
        }

        return view('admin.quick-upload', [
            'maxUploadBytes' => $max,
            'resumableEnabled' => config('gomovie.resumable_uploads_enabled') === true,
            'resumableMaxBytes' => ResumableUploadService::MAX_BYTES,
            'autoReady' => config('gomovie.automatic_publication_enabled') === true
                && config('gomovie.public_hls_enabled') === true
                && config('gomovie.transcoding_enabled') === true,
            'ingestId' => $ingestId,
            'resumeAccess' => $resumeAccess,
        ]);
    }

    public function store(Request $request, PrivateVideoIntake $intake): JsonResponse|RedirectResponse
    {
        $this->requireAdmin($request);

        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string', 'max:12000'],
            'access' => ['required', Rule::in(['free', 'private', 'paid'])],
            'rights_confirmed' => ['accepted'],
            'video' => ['required', 'file', 'mimes:mp4,mov,mkv,webm', 'max:131072'],
        ]);

        $isFree = $data['access'] === 'free';
        $autoReady = config('gomovie.automatic_publication_enabled') === true
            && config('gomovie.public_hls_enabled') === true
            && config('gomovie.transcoding_enabled') === true;
        if ($isFree) {
            abort_unless($autoReady, 409,
                'Free publication is not configured on this installation. Choose Private instead.');
        }

        $uploader = (int) $request->user()->id;
        // Only the server-side resumable completion controller may set this attribute.
        $resumableId = $request->attributes->get('gomovie.resumable_session_id');
        abort_unless($resumableId === null || (is_string($resumableId)
            && Str::isUuid($resumableId) && $data['access'] === 'private'), 403);
        $slug = Str::slug($data['title'], '-');
        $slug = substr($slug !== '' ? $slug : 'original-video', 0, 175)
            .'-'.($resumableId ? str_replace('-', '', $resumableId)
                : strtolower(Str::random(16)));
        // A self-attestation is documented with the actual administrator
        // and a unique reference; it does not license any existing movie.
        $reference = 'OWNER-DECLARED-'.$uploader.'-'.strtoupper(Str::random(24));

        $movie = DB::transaction(function () use ($data, $slug, $isFree, $uploader, $request, $reference): Movie {
            $movie = Movie::create([
                'title' => trim($data['title']),
                'slug' => $slug,
                'overview' => trim($data['description'] ?? '') ?: null,
                'status' => 'draft',
                'is_premium' => $data['access'] === 'paid',
            ]);

            if ($isFree) {
                DB::table('content_rights')->insert([
                    'licensable_type' => 'movie',
                    'licensable_id' => $movie->id,
                    'rights_reference' => $reference,
                    'status' => 'pending',
                    'territories' => null,
                    'starts_at' => now(),
                    'ends_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('admin_audit_events')->insert([
                'actor_id' => $uploader,
                'action' => 'media.quick_upload_attested',
                'resource_type' => 'movie',
                'resource_id' => $movie->id,
                'details' => json_encode([
                    'access' => $data['access'],
                    'rights_basis' => 'uploader_declaration_only',
                    'declaration' => $isFree
                        ? 'I own or have worldwide online publishing permission for this video.'
                        : 'I own or have permission to upload and store this video.',
                    'rights_reference' => $isFree ? $reference : null,
                    'sales_enabled' => false,
                ], JSON_THROW_ON_ERROR),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $movie;
        });

        try {
            $ingestId = $intake->store('movie', (int) $movie->id,
                $data['video'], $uploader, $request->ip(), false, $resumableId);
        } catch (Throwable $error) {
            // Do not delete a movie after a partially successful intake.
            // An existing ingest may already be queued or independently mirrored.
            DB::transaction(function () use ($movie, $slug, $reference): void {
                if (DB::table('media_ingests')->where('ingestable_type', 'movie')
                    ->where('ingestable_id', $movie->id)->exists()) return;

                DB::table('content_rights')->where('licensable_type', 'movie')
                    ->where('licensable_id', $movie->id)
                    ->where('rights_reference', $reference)->delete();
                DB::table('admin_audit_events')->where('resource_type', 'movie')
                    ->where('resource_id', $movie->id)
                    ->where('action', 'media.quick_upload_attested')->delete();
                Movie::whereKey($movie->id)->where('slug', $slug)
                    ->where('status', 'draft')->delete();
            });
            throw $error;
        }

        $message = match ($data['access']) {
            'free' => 'Upload received privately. Distribution rights and publication require independent administrator review.',
            'paid' => 'Paid video saved privately. Sales will remain disabled until checkout is configured.',
            default => 'Video saved privately. It will not appear on the public website.',
        };

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'movie_id' => $movie->id,
                'upload_id' => $ingestId,
                'access' => $data['access'],
                'will_publish_automatically' => false,
                'status_url' => route('upload-studio.status', ['ingest' => $ingestId]),
                'watch_url' => route('watch.show', ['type' => 'movie', 'slug' => $slug]),
                'media_library_url' => route('admin.media.index'),
            ], 201)->header('Cache-Control', 'private, no-store');
        }

        return redirect()->route('upload-studio.index', ['ingest' => $ingestId])
            ->with('status', $message);
    }

    private function iniBytes(string|false $value): int
    {
        if (!is_string($value) || !preg_match('/\A(\d+)([KMG]?)\z/iD', trim($value), $parts)) return 0;
        return min(PHP_INT_MAX, (int) $parts[1] * match (strtoupper($parts[2])) {
            'K' => 1024, 'M' => 1048576, 'G' => 1073741824, default => 1,
        });
    }

    private function requireAdmin(Request $request): void
    {
        abort_unless($request->user()
            && in_array($request->user()->role, ['admin', 'super_admin'], true), 403);
    }
}
