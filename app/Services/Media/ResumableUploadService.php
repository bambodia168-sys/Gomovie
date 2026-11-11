<?php

namespace App\Services\Media;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Private, sequential, SHA-256 verified chunk staging, never an approval gate.
 *
 * The only publication/ingest entry point remains PrivateVideoIntake, through
 * QuickUploadController after full assembly. No raw chunk has an HTTP read route.
 */
final class ResumableUploadService
{
    public const CHUNK_SIZE = 1048576;
    public const MAX_BYTES = 134217728;
    private const MAX_OPEN_SESSIONS = 3;

    public function start(User $user, array $data): object
    {
        abort_unless((int) $data['size_bytes'] >= 1
            && (int) $data['size_bytes'] <= self::MAX_BYTES, 422,
            'Video exceeds the current private-intake limit.');

        return DB::transaction(function () use ($user, $data): object {
            $active = DB::table('resumable_upload_sessions')
                ->where('uploaded_by', $user->id)->where('status', 'uploading')
                ->where('expires_at', '>', now())->lockForUpdate()->count();
            abort_if($active >= self::MAX_OPEN_SESSIONS, 429,
                'Finish or cancel an active upload before starting another.');
            $id = (string) Str::uuid();
            $now = now();
            DB::table('resumable_upload_sessions')->insert([
                'id' => $id,
                'uploaded_by' => $user->id,
                'title' => trim((string) $data['title']),
                'description' => trim((string) ($data['description'] ?? '')) ?: null,
                'original_name' => (string) $data['filename'],
                'total_bytes' => (int) $data['size_bytes'],
                'received_bytes' => 0,
                'chunk_size' => self::CHUNK_SIZE,
                'status' => 'uploading',
                'expires_at' => $now->copy()->addDay(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            return DB::table('resumable_upload_sessions')->where('id', $id)->first();
        });
    }

    public function status(User $user, string $id): object
    {
        $row = DB::table('resumable_upload_sessions')
            ->where('id', $id)->where('uploaded_by', $user->id)->first();
        abort_unless($row, 404);
        abort_if($row->expires_at <= now(), 410, 'Upload session expired.');
        return $row;
    }

    public function append(User $user, string $id, int $offset, UploadedFile $chunk, string $checksum): object
    {
        abort_unless(preg_match('/\A[a-f0-9]{64}\z/D', $checksum), 422);
        $path = $chunk->getRealPath();
        $size = $chunk->getSize();
        abort_unless($chunk->isValid() && is_string($path) && is_file($path)
            && is_int($size) && $size > 0 && $size <= self::CHUNK_SIZE
            && hash_equals($checksum, (string) hash_file('sha256', $path)),
            422, 'Invalid chunk or SHA-256 mismatch.');

        return DB::transaction(function () use ($user, $id, $offset, $path, $size, $checksum): object {
            $row = DB::table('resumable_upload_sessions')
                ->where('id', $id)->where('uploaded_by', $user->id)
                ->lockForUpdate()->first();
            abort_unless($row, 404);
            abort_if($row->expires_at <= now(), 410, 'Upload session expired.');
            abort_unless($row->status === 'uploading', 409, 'Upload is no longer writable.');

            $total = (int) $row->total_bytes;
            $current = (int) $row->received_bytes;
            $expected = min(self::CHUNK_SIZE, $total - $offset);
            abort_unless($offset >= 0 && $offset % self::CHUNK_SIZE === 0
                && $expected > 0 && $size === $expected, 422,
                'Chunk size, order, or offset is invalid.');

            if ($offset < $current) {
                $saved = DB::table('resumable_upload_chunks')
                    ->where('session_id', $id)->where('offset', $offset)->first();
                abort_unless($saved && (int) $saved->bytes === $size
                    && hash_equals((string) $saved->sha256, $checksum), 409,
                    'Chunk already exists with different data.');
                return $row; // Idempotent retransmission of the same verified chunk.
            }
            abort_unless($offset === $current, 409,
                'Offset differs from confirmed server progress. Refresh status and resume.');

            $key = $this->chunkKey($id, $offset);
            $disk = Storage::disk('local');
            $stream = fopen($path, 'rb');
            abort_unless($stream !== false, 422);
            try {
                abort_unless($disk->writeStream($key, $stream, ['visibility' => 'private']),
                    503, 'Could not store private chunk.');
            } finally {
                fclose($stream);
            }

            // Files can survive a rolled-back transaction; the next verified
            // attempt overwrites only the same deterministic private chunk key.
            abort_unless($disk->size($key) === $size
                && hash_equals($checksum, (string) hash_file('sha256', $disk->path($key))),
                503, 'Stored chunk integrity failed.');

            DB::table('resumable_upload_chunks')->insert([
                'session_id' => $id, 'offset' => $offset, 'bytes' => $size,
                'sha256' => $checksum, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('resumable_upload_sessions')->where('id', $id)->update([
                'received_bytes' => $offset + $size, 'updated_at' => now(),
            ]);
            return DB::table('resumable_upload_sessions')->where('id', $id)->first();
        });
    }

    /**
     * Claim completion before assembling to prevent concurrent commit requests.
     * An interrupted finalizing session remains fail-closed; a linked ingest
     * can be recovered on a later status call, never re-created blindly.
     */
    public function prepareComplete(User $user, string $id): array
    {
        $row = DB::transaction(function () use ($user, $id): object {
            $row = DB::table('resumable_upload_sessions')
                ->where('id', $id)->where('uploaded_by', $user->id)
                ->lockForUpdate()->first();
            abort_unless($row, 404);
            abort_if($row->expires_at <= now(), 410, 'Upload session expired.');
            if ($row->status === 'completed') return $row;
            if ($row->status === 'finalizing') {
                $this->repairCompleted($row);
                return DB::table('resumable_upload_sessions')->where('id', $id)->first();
            }
            abort_unless($row->status === 'uploading'
                && (int) $row->received_bytes === (int) $row->total_bytes,
                409, 'Upload is incomplete or not writable.');
            DB::table('resumable_upload_sessions')->where('id', $id)->update([
                'status' => 'finalizing', 'updated_at' => now(),
            ]);
            return $row;
        });
        if ($row->status === 'completed' || $row->status === 'finalizing') {
            abort_unless($row->status === 'completed', 409,
                'Finalization is in progress. Check status before retrying.');
            return ['existing' => true, 'session' => $row];
        }

        $disk = Storage::disk('local');
        $prefix = 'resumable/'.$id;
        $resultKey = $prefix.'/assembled.tmp';
        $disk->makeDirectory($prefix);
        $outputPath = $disk->path($resultKey);
        $output = fopen($outputPath, 'wb');
        abort_unless($output !== false, 503, 'Could not assemble private upload.');

        $digest = hash_init('sha256');
        $position = 0;
        $parts = DB::table('resumable_upload_chunks')->where('session_id', $id)
            ->orderBy('offset')->get();
        try {
            foreach ($parts as $part) {
                abort_unless((int) $part->offset === $position, 409, 'Chunk gap found.');
                $key = $this->chunkKey($id, $position);
                $sourcePath = $disk->path($key);
                abort_unless(is_file($sourcePath) && !is_link($sourcePath)
                    && filesize($sourcePath) === (int) $part->bytes
                    && hash_equals((string) $part->sha256,
                        (string) hash_file('sha256', $sourcePath)),
                    409, 'Private chunk integrity mismatch.');
                $input = fopen($sourcePath, 'rb');
                abort_unless($input !== false, 503);
                try {
                    while (!feof($input)) {
                        $data = fread($input, 65536);
                        abort_unless(is_string($data), 503);
                        if ($data === '') break;
                        hash_update($digest, $data);
                        abort_unless(fwrite($output, $data) === strlen($data), 503);
                    }
                } finally {
                    fclose($input);
                }
                $position += (int) $part->bytes;
            }
        } catch (Throwable $error) {
            fclose($output);
            // Block the session for operator reconciliation, not blind retry.
            DB::table('resumable_upload_sessions')->where('id', $id)
                ->where('status', 'finalizing')->update(['status' => 'blocked', 'updated_at' => now()]);
            throw $error;
        }
        fclose($output);
        abort_unless($position === (int) $row->total_bytes
            && filesize($outputPath) === $position, 409, 'Assembled size mismatch.');
        // Exact server-side digest is retained for audit and future review.
        $sha256 = hash_final($digest);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($outputPath);
        $ext = strtolower(pathinfo((string) $row->original_name, PATHINFO_EXTENSION));
        $valid = [
            'mp4' => 'video/mp4', 'mov' => 'video/quicktime',
            'mkv' => 'video/x-matroska', 'webm' => 'video/webm',
        ];
        abort_unless(isset($valid[$ext]) && $mime === $valid[$ext], 422,
            'Assembled video type does not match the original name.');

        DB::table('resumable_upload_sessions')->where('id', $id)->update([
            'updated_at' => now(),
        ]);
        return ['existing' => false, 'session' => $row,
            'source_path' => $outputPath, 'sha256' => $sha256];
    }

    public function markCompleted(User $user, string $id, int $movieId, int $ingestId): void
    {
        DB::transaction(function () use ($user, $id, $movieId, $ingestId): void {
            $row = DB::table('resumable_upload_sessions')
                ->where('id', $id)->where('uploaded_by', $user->id)
                ->lockForUpdate()->first();
            abort_unless($row && $row->status === 'finalizing', 409);
            $ingest = DB::table('media_ingests')
                ->where('id', $ingestId)->where('uploaded_by', $user->id)
                ->where('resumable_upload_session_id', $id)->first();
            abort_unless($ingest && $ingest->ingestable_type === 'movie'
                && (int) $ingest->ingestable_id === $movieId
                && $ingest->status === 'quarantined', 409);
            DB::table('resumable_upload_sessions')->where('id', $id)->update([
                'movie_id' => $movieId, 'media_ingest_id' => $ingestId,
                'status' => 'completed', 'updated_at' => now(),
            ]);
        });
        // Only delete temporary chunks; the resulting media_ingest is separate.
        Storage::disk('local')->deleteDirectory('resumable/'.$id);
    }

    public function cancel(User $user, string $id): void
    {
        DB::transaction(function () use ($user, $id): void {
            $row = DB::table('resumable_upload_sessions')
                ->where('id', $id)->where('uploaded_by', $user->id)
                ->lockForUpdate()->first();
            abort_unless($row, 404);
            abort_unless($row->status === 'uploading', 409,
                'This upload is being finalized or has already been saved.');
            DB::table('resumable_upload_sessions')->where('id', $id)->update([
                'status' => 'cancelled', 'updated_at' => now(),
            ]);
        });
        Storage::disk('local')->deleteDirectory('resumable/'.$id);
    }

    private function repairCompleted(object $row): void
    {
        $ingest = DB::table('media_ingests')
            ->where('resumable_upload_session_id', $row->id)
            ->where('uploaded_by', $row->uploaded_by)
            ->where('ingestable_type', 'movie')->first();
        if (!$ingest) return;
        DB::table('resumable_upload_sessions')->where('id', $row->id)
            ->where('status', 'finalizing')->update([
                'movie_id' => $ingest->ingestable_id,
                'media_ingest_id' => $ingest->id,
                'status' => 'completed', 'updated_at' => now(),
            ]);
    }

    private function chunkKey(string $id, int $offset): string
    {
        return 'resumable/'.$id.'/chunks/'.$offset.'.part';
    }
}
