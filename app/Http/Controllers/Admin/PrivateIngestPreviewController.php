<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Authenticated, private administrator preview of the original uploaded file.
 *
 * This is NOT distribution, publication, conversion, DRM or a grant for viewers.
 * Browsers use a byte-range request to seek inside a local MP4/WebM file.
 * Quarantined uploads remain quarantined; the preview does not mark anything
 * scanned, licensed, approved, encoded, or ready.
 */
final class PrivateIngestPreviewController extends Controller
{
    public function __invoke(Request $request, int $ingest): BinaryFileResponse
    {
        $user = $request->user();
        abort_unless($user && $user->is_active && $user->hasVerifiedEmail()
            && in_array($user->role, ['admin', 'super_admin'], true), 403);

        $record = DB::table('media_ingests')->where('id', $ingest)->first();
        abort_unless($record, 404);

        // Regular administrators see only their own uploads. The verified
        // super administrator may inspect privately uploaded files.
        abort_unless($user->role === 'super_admin'
            || (int) $record->uploaded_by === (int) $user->id, 404);

        abort_unless(in_array($record->ingestable_type, ['movie', 'episode'], true)
            && in_array($record->status, ['quarantined', 'approved', 'processing', 'encoded'], true)
            && $record->disk === 'local', 404);

        $extension = match ((string) $record->mime) {
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            default => null,
        };
        abort_unless($extension !== null, 415,
            'Private browser preview supports MP4 and WebM. Other formats require encoding.');

        // A predictable path is never accepted. Only the exact randomized
        // object key originally stored by PrivateVideoIntake is permitted.
        $key = (string) $record->object_key;
        abort_unless((bool) preg_match(
            '#\Aingests/[0-9]{4}/[0-9]{2}/[0-9a-f-]{36}\.'.preg_quote($extension, '#').'\z#iD',
            $key
        ), 404);

        $disk = Storage::disk('local');
        $root = realpath(storage_path('app/private'));
        $location = $disk->path($key);
        $real = realpath($location);
        abort_unless($root && $real && !is_link($location)
            && str_starts_with($real, $root.DIRECTORY_SEPARATOR)
            && is_file($real) && is_readable($real), 404);

        // Verify original-byte integrity before serving any range. No
        // unverified or modified source is delivered, even to the uploader.
        $size = filesize($real);
        $hash = hash_file('sha256', $real);
        abort_unless(is_int($size) && $size > 0
            && $size === (int) $record->size_bytes
            && is_string($hash) && is_string($record->sha256)
            && hash_equals($record->sha256, $hash), 409,
            'The private upload failed integrity verification.');

        $contentType = $extension === 'mp4' ? 'video/mp4' : 'video/webm';

        // Symfony BinaryFileResponse handles RFC byte-range and If-Range
        // requests, including 206 Partial Content, for HTML5 video seeking.
        $response = response()->file($real, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Referrer-Policy' => 'no-referrer',
            'X-Gomovie-Preview' => 'private-unpublished',
        ]);
        $response->setPrivate();

        return $response;
    }
}
