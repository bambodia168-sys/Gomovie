<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Private WebVTT captions belonging to the exact uploader and media ingest.
 * No public URL, entitlement, rights approval or cloud exposure is created.
 */
final class UploadCaptionController extends Controller
{
    private const LANGUAGES = [
        'en' => 'English', 'km' => 'Khmer', 'zh' => 'Chinese',
        'fr' => 'French', 'hi' => 'Hindi', 'id' => 'Indonesian',
        'ja' => 'Japanese', 'ko' => 'Korean', 'ms' => 'Malay',
        'th' => 'Thai', 'vi' => 'Vietnamese',
    ];

    private function ingest(Request $request, int $ingest, bool $write = false): object
    {
        $user = $request->user();
        abort_unless($user && $user->is_active && $user->hasVerifiedEmail()
            && in_array($user->role, ['admin', 'super_admin'], true), 403);
        // Even super administrators use the uploaded owner's own management UI.
        // Private preview authorization is deliberately separate.
        $row = DB::table('media_ingests')->where('id', $ingest)
            ->where('uploaded_by', (int) $user->id)->first();
        abort_unless($row && in_array($row->ingestable_type, ['movie', 'episode'], true), 404);
        abort_unless(in_array($row->status, ['quarantined','approved','processing','encoded'], true), 404);
        if ($write) {
            abort_unless($row->status === 'quarantined', 409,
                'Captions can only be edited while this upload is private and unprocessed.');
        }
        return $row;
    }

    private function caption(int $ingest, int $caption): object
    {
        return DB::table('media_ingest_captions')->where('media_ingest_id', $ingest)
            ->where('id', $caption)->firstOrFail();
    }

    private function audit(Request $request, int $ingest, string $action, array $details): void
    {
        DB::table('admin_audit_events')->insert([
            'actor_id' => $request->user()->id,
            'action' => $action,
            'resource_type' => 'media_ingest',
            'resource_id' => $ingest,
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function index(Request $request, int $ingest): JsonResponse
    {
        $row = $this->ingest($request, $ingest);
        $captions = DB::table('media_ingest_captions')
            ->where('media_ingest_id', $ingest)
            ->orderByDesc('is_default')->orderBy('language')
            ->get(['id','language','label','is_default','size_bytes','created_at'])
            ->map(fn ($item) => [
                'id' => (int) $item->id, 'language' => $item->language,
                'label' => $item->label, 'is_default' => (bool) $item->is_default,
                'size_bytes' => (int) $item->size_bytes,
                'file_url' => route('upload-studio.captions.file',
                    ['ingest' => $ingest, 'caption' => $item->id]),
            ]);
        return response()->json([
            'ok' => true, 'captions' => $captions,
            'can_edit' => $row->status === 'quarantined',
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, int $ingest): JsonResponse
    {
        $this->ingest($request, $ingest, true);
        $data = $request->validate([
            'language' => ['required', Rule::in(array_keys(self::LANGUAGES))],
            'label' => ['nullable', 'string', 'max:80'],
            'caption' => ['required', 'file', 'max:256'],
            'is_default' => ['nullable','boolean'],
        ]);
        $file = $request->file('caption');
        abort_unless($file && strtolower($file->getClientOriginalExtension()) === 'vtt', 422,
            'Select a .vtt subtitle file.');
        $bytes = $file->get();
        abort_unless(is_string($bytes) && strlen($bytes) > 10 && strlen($bytes) <= 262144
            && preg_match('/\A(?:\xEF\xBB\xBF)?WEBVTT(?:[ \t][^\r\n]*)?\r?\n/', $bytes)
            && preg_match('/\d{2}:\d{2}(?::\d{2})?\.\d{3}[ \t]+-->[ \t]+\d{2}:\d{2}(?::\d{2})?\.\d{3}/', $bytes)
            && !str_contains($bytes, "\0") && !str_contains($bytes, '<')
            && !str_contains($bytes, '>') && preg_match('//u', $bytes), 422,
            'The caption must contain valid UTF-8 WEBVTT cues without HTML markup.');

        $language = $data['language'];
        $label = trim((string) ($data['label'] ?? '')) ?: self::LANGUAGES[$language];
        $key = 'captions/ingests/'.$ingest.'/'.Str::uuid().'.vtt';
        $disk = Storage::disk('local');
        abort_unless($disk->put($key, $bytes), 503, 'Private caption storage unavailable.');
        $hash = hash('sha256', $bytes);
        $oldKey = null;
        $created = false;
        try {
            DB::transaction(function () use ($request, $ingest, $language, $label, $key, $hash, $bytes,
                $data, &$oldKey, &$created): void {
                // Serialize with processing, including a status recheck.
                $record = DB::table('media_ingests')->where('id', $ingest)->lockForUpdate()->first();
                abort_unless($record && $record->status === 'quarantined', 409,
                    'Upload processing has already started.');
                $previous = DB::table('media_ingest_captions')
                    ->where('media_ingest_id',$ingest)->where('language',$language)
                    ->lockForUpdate()->first();
                $created = !$previous;
                $oldKey = $previous?->object_key;
                $defaultExists = DB::table('media_ingest_captions')
                    ->where('media_ingest_id', $ingest)->where('is_default', true)->exists();
                $isDefault = $request->boolean('is_default') || (!$defaultExists && !$previous);
                if ($isDefault) {
                    DB::table('media_ingest_captions')->where('media_ingest_id', $ingest)
                        ->update(['is_default' => false]);
                }
                $values = [
                    'label' => $label, 'disk' => 'local', 'object_key' => $key,
                    'sha256' => $hash, 'size_bytes' => strlen($bytes),
                    'is_default' => $isDefault || (bool) ($previous?->is_default && !$request->has('is_default')),
                    'updated_at' => now(),
                ];
                if ($previous) {
                    DB::table('media_ingest_captions')->where('id', $previous->id)->update($values);
                } else {
                    DB::table('media_ingest_captions')->insert([
                        'media_ingest_id' => $ingest, 'language' => $language,
                        ...$values, 'created_at' => now(),
                    ]);
                }
                $this->audit($request, $ingest,
                    $previous ? 'media.ingest_caption_replaced' : 'media.ingest_caption_created',
                    ['language'=>$language]);
            });
        } catch (Throwable $e) {
            $disk->delete($key);
            throw $e;
        }
        if ($oldKey) $disk->delete($oldKey);
        return response()->json([
            'ok' => true,
            'message' => $created ? 'Caption uploaded privately.' : 'Caption replaced privately.',
        ], $created ? 201 : 200)->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, int $ingest, int $caption): JsonResponse
    {
        $this->ingest($request, $ingest, true);
        $data = $request->validate([
            'label' => ['required','string','max:80'],
            'is_default' => ['required','boolean'],
        ]);
        DB::transaction(function () use ($request, $ingest, $caption, $data): void {
            $record = DB::table('media_ingests')->where('id',$ingest)->lockForUpdate()->first();
            abort_unless($record && $record->status === 'quarantined', 409);
            $this->caption($ingest, $caption);
            if ($data['is_default']) {
                DB::table('media_ingest_captions')->where('media_ingest_id',$ingest)
                    ->update(['is_default'=>false]);
            }
            DB::table('media_ingest_captions')->where('id',$caption)->update([
                'label'=>trim($data['label']), 'is_default'=>(bool) $data['is_default'],
                'updated_at'=>now(),
            ]);
            $this->audit($request,$ingest,'media.ingest_caption_updated',
                ['caption_id'=>$caption,'default'=>(bool) $data['is_default']]);
        });
        return response()->json(['ok'=>true,'message'=>'Caption updated.'])
            ->header('Cache-Control','private, no-store');
    }

    public function destroy(Request $request, int $ingest, int $caption): JsonResponse
    {
        $this->ingest($request, $ingest, true);
        $key = DB::transaction(function () use ($request,$ingest,$caption): string {
            $record = DB::table('media_ingests')->where('id',$ingest)->lockForUpdate()->first();
            abort_unless($record && $record->status === 'quarantined', 409);
            $item = $this->caption($ingest,$caption);
            DB::table('media_ingest_captions')->where('id',$caption)->delete();
            $this->audit($request,$ingest,'media.ingest_caption_deleted',['language'=>$item->language]);
            return $item->object_key;
        });
        Storage::disk('local')->delete($key);
        return response()->json(['ok'=>true,'message'=>'Caption deleted.'])
            ->header('Cache-Control','private, no-store');
    }

    public function file(Request $request, int $ingest, int $caption): BinaryFileResponse
    {
        $this->ingest($request, $ingest);
        $row = $this->caption($ingest,$caption);
        abort_unless($row->disk === 'local' && preg_match(
            '#\Acaptions/ingests/'.$ingest.'/[0-9a-f-]{36}\.vtt\z#iD', $row->object_key), 404);
        $disk = Storage::disk('local');
        $root = realpath($disk->path(''));
        $path = $disk->path($row->object_key);
        $real = realpath($path);
        abort_unless($root && $real && !is_link($path)
            && str_starts_with($real, $root.DIRECTORY_SEPARATOR) && is_file($real)
            && filesize($real) === (int) $row->size_bytes
            && hash_equals((string) $row->sha256, (string) hash_file('sha256',$real)), 404);

        $response = response()->file($real, [
            'Content-Type' => 'text/vtt; charset=utf-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Referrer-Policy' => 'no-referrer',
        ]);
        $response->setPrivate();
        return $response;
    }
}
