<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Services\Media\PrivateVideoIntake;
use App\Support\SiteSettings;
use App\Support\VideoUploadLimit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

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
            'maxUploadBytes' => VideoUploadLimit::effectiveBytes(),
            'autoReady' => $this->publicProcessingEnabled(),
            'ingestId' => $ingestId,
            'resumeAccess' => $resumeAccess,
        ]);
    }

    public function store(Request $request, PrivateVideoIntake $intake): JsonResponse|RedirectResponse
    {
        $this->requireAdmin($request);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:12000'],
            'access' => ['sometimes', 'required', Rule::in(['free'])],
            'rights_confirmed' => ['accepted'],
            'video' => VideoUploadLimit::fileRules(),
        ]);

        abort_unless($this->publicProcessingEnabled(), 409,
            'Enable FFmpeg transcoding, HLS playback, and automatic publication in Site Settings to upload a normal video.');

        $uploader = (int) $request->user()->id;
        $filename = pathinfo($data['video']->getClientOriginalName(), PATHINFO_FILENAME);
        $filename = preg_replace('/[\p{C}_-]+/u', ' ', $filename) ?? '';
        $title = Str::limit(trim($data['title'] ?? $filename) ?: 'Untitled video', 255, '');
        $slug = substr(Str::slug($title) ?: 'video', 0, 175).'-'.strtolower(Str::random(16));
        // A self-attestation is documented with the actual administrator
        // and a unique reference; it does not license any existing movie.
        $reference = 'OWNER-DECLARED-'.$uploader.'-'.strtoupper(Str::random(24));

        $movie = DB::transaction(function () use ($data, $title, $slug, $uploader, $request, $reference): Movie {
            $movie = Movie::create([
                'title' => $title,
                'slug' => $slug,
                'overview' => trim($data['description'] ?? '') ?: null,
                'status' => 'draft',
                'is_premium' => false,
                'is_standalone_video' => true,
            ]);

            DB::table('content_rights')->insert([
                'licensable_type' => 'movie',
                'licensable_id' => $movie->id,
                'rights_reference' => $reference,
                'status' => 'active',
                'territories' => null,
                'starts_at' => now(),
                'ends_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('admin_audit_events')->insert([
                'actor_id' => $uploader,
                'action' => 'media.quick_upload_attested',
                'resource_type' => 'movie',
                'resource_id' => $movie->id,
                'details' => json_encode([
                    'access' => 'free',
                    'rights_basis' => 'uploader_declaration_only',
                    'declaration' => 'I own or have worldwide online publishing permission for this video.',
                    'rights_reference' => $reference,
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
                $data['video'], $uploader, $request->ip(), publishWatchPage: true);
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

        $message = 'Your watch page is public. Playback starts after the video finishes processing.';

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'movie_id' => $movie->id,
                'upload_id' => $ingestId,
                'access' => 'free',
                'will_publish_automatically' => true,
                'status_url' => route('upload-studio.status', ['ingest' => $ingestId]),
                'watch_url' => route('watch.show', ['type' => 'movie', 'slug' => $slug]),
                'media_library_url' => route('admin.media.index'),
            ], 201)->header('Cache-Control', 'private, no-store');
        }

        return redirect()->route('upload-studio.index', ['ingest' => $ingestId])
            ->with('status', $message);
    }

    private function publicProcessingEnabled(): bool
    {
        return SiteSettings::get('automatic_publication_enabled') === true
            && SiteSettings::get('public_hls_enabled') === true
            && SiteSettings::get('transcoding_enabled') === true;
    }

    private function requireAdmin(Request $request): void
    {
        abort_unless($request->user()
            && in_array($request->user()->role, ['admin', 'super_admin'], true), 403);
    }
}
