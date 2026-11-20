<?php

namespace App\Services\Media;

use App\Support\VideoUploadLimit;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use stdClass;

final class ResumableVideoUpload
{
    public const CHUNK_BYTES = 524288;

    public static function directLimit(): int
    {
        return min(self::CHUNK_BYTES, VideoUploadLimit::effectiveBytes());
    }

    public function begin(string $id, int $uploader, string $workflow, string $filename, int $size): array
    {
        return $this->locked($id, function () use ($id, $uploader, $workflow, $filename, $size): array {
            $now = now();
            DB::table('video_upload_transfers')->insertOrIgnore([
                'id' => $id,
                'source_id' => Str::uuid()->toString(),
                'uploader_id' => $uploader,
                'workflow' => $workflow,
                'filename' => $filename,
                'size_bytes' => $size,
                'chunk_bytes' => min(self::CHUNK_BYTES, VideoUploadLimit::effectiveBytes()),
                'chunk_hashes' => '[]',
                'expires_at' => $now->copy()->addDay(),
                'created_at' => $now,
            ]);
            $transfer = $this->owned($id, $uploader);
            abort_unless($transfer->workflow === $workflow && $transfer->filename === $filename
                && (int) $transfer->size_bytes === $size, 409, 'This transfer belongs to a different file.');

            return $this->snapshot($transfer);
        });
    }

    public function status(string $id, int $uploader): array
    {
        return $this->snapshot($this->owned($id, $uploader));
    }

    public function append(string $id, int $uploader, int $offset, $input): array
    {
        return $this->locked($id, function () use ($id, $uploader, $offset, $input): array {
            $transfer = $this->owned($id, $uploader);
            $received = (int) $transfer->received_bytes;
            $size = (int) $transfer->size_bytes;
            abort_unless($size <= VideoUploadLimit::bytes(), 422, 'The video exceeds the current upload limit.');
            abort_unless($transfer->ingest_id === null && $offset === $received && $received < $size, 409,
                'The upload offset changed. Resume the upload to continue.');
            $length = min((int) $transfer->chunk_bytes, $size - $received);
            $bytes = stream_get_contents($input, $length + 1);
            abort_unless(is_string($bytes) && strlen($bytes) === $length, 422, 'The upload chunk has an incorrect size.');

            $file = fopen($this->partPath($id), 'c+b');
            abort_unless(is_resource($file), 503, 'The upload could not be opened.');
            try {
                abort_unless(fstat($file)['size'] >= $received, 409, 'The saved portion is missing. Cancel and start again.');
                // A prior request can stop after writing bytes but before acknowledging them.
                if (!ftruncate($file, $received) || fseek($file, $received) !== 0
                    || fwrite($file, $bytes) !== $length || !fflush($file) || !fsync($file)) {
                    throw new RuntimeException('Could not save the upload chunk.');
                }
            } finally {
                fclose($file);
            }

            $hashes = json_decode($transfer->chunk_hashes, true, flags: JSON_THROW_ON_ERROR);
            $hashes[] = hash('sha256', $bytes);
            $received += $length;
            DB::table('video_upload_transfers')->where('id', $id)->update([
                'received_bytes' => $received,
                'chunk_hashes' => json_encode($hashes, JSON_THROW_ON_ERROR),
                'expires_at' => now()->addDay(),
            ]);

            return ['received_bytes' => $received];
        });
    }

    public function complete(string $id, int $uploader, string $workflow, Closure $register): int
    {
        return $this->locked($id, function () use ($id, $uploader, $workflow, $register): int {
            $transfer = $this->owned($id, $uploader);
            abort_unless($transfer->workflow === $workflow, 404);
            if ($transfer->ingest_id !== null) {
                return (int) $transfer->ingest_id;
            }

            $path = $this->partPath($id);
            clearstatcache(true, $path);
            abort_unless((int) $transfer->received_bytes === (int) $transfer->size_bytes
                && is_file($path) && filesize($path) === (int) $transfer->size_bytes, 409,
                'The upload is incomplete. Resume it before saving.');
            $video = new UploadedFile($path, $transfer->filename, null, UPLOAD_ERR_OK, true);
            Validator::make(['video' => $video], ['video' => VideoUploadLimit::fileRules()])->validate();

            $ingest = DB::transaction(function () use ($transfer, $register, $video): int {
                $ingest = $register($video, $this->sourcePrefix($transfer));
                DB::table('video_upload_transfers')->where('id', $transfer->id)->update([
                    'ingest_id' => $ingest,
                    'expires_at' => now()->addDay(),
                ]);

                return $ingest;
            });

            Storage::disk('local')->delete('upload-transfers/'.$id.'.part');

            return $ingest;
        });
    }

    public function cancel(string $id, int $uploader): void
    {
        $this->locked($id, function () use ($id, $uploader): void {
            $transfer = $this->owned($id, $uploader);
            abort_if($transfer->ingest_id !== null, 409, 'This upload is already saved.');
            $this->remove($transfer);
        });
    }

    public function prune(): void
    {
        DB::table('video_upload_transfers')->where('expires_at', '<=', now())
            ->select('id')->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $this->locked($row->id, function () use ($row): void {
                        $expired = DB::table('video_upload_transfers')->where('id', $row->id)
                            ->where('expires_at', '<=', now())->first();
                        if ($expired) {
                            $this->remove($expired);
                        }
                    });
                }
            });
    }

    private function owned(string $id, int $uploader): stdClass
    {
        $row = DB::table('video_upload_transfers')->where('id', $id)->where('uploader_id', $uploader)
            ->where('expires_at', '>', now())->first();
        abort_unless($row, 404, 'The saved upload is unavailable or has expired.');

        return $row;
    }

    private function snapshot(stdClass $transfer): array
    {
        return [
            'id' => $transfer->id,
            'filename' => $transfer->filename,
            'size_bytes' => (int) $transfer->size_bytes,
            'chunk_bytes' => (int) $transfer->chunk_bytes,
            'received_bytes' => (int) $transfer->received_bytes,
            'chunk_hashes' => json_decode($transfer->chunk_hashes, true, flags: JSON_THROW_ON_ERROR),
            'completed' => $transfer->ingest_id !== null,
        ];
    }

    private function locked(string $id, Closure $operation): mixed
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory('upload-transfers');
        // Reuse a bounded set of lock files. All requests for a transfer use
        // the same lock, including after its partial file has been removed.
        $slot = substr(hash('sha256', $id), 0, 3);
        $lock = fopen($disk->path('upload-transfers/'.$slot.'.lock'), 'c');
        abort_unless(is_resource($lock), 503, 'Upload storage is unavailable.');
        try {
            abort_unless(flock($lock, LOCK_EX), 503, 'The upload could not be locked.');

            return $operation();
        } finally {
            fclose($lock);
        }
    }

    private function partPath(string $id): string
    {
        return Storage::disk('local')->path('upload-transfers/'.$id.'.part');
    }

    private function sourcePrefix(stdClass $transfer): string
    {
        return 'ingests/'.Carbon::parse($transfer->created_at)->format('Y/m').'/'.$transfer->source_id;
    }

    private function remove(stdClass $transfer): void
    {
        $paths = ['upload-transfers/'.$transfer->id.'.part'];
        if ($transfer->ingest_id === null) {
            foreach (['mp4', 'mov', 'mkv', 'webm'] as $extension) {
                $paths[] = $this->sourcePrefix($transfer).'.'.$extension;
            }
        }
        abort_unless(Storage::disk('local')->delete($paths), 503, 'The partial upload could not be removed.');
        DB::table('video_upload_transfers')->where('id', $transfer->id)->delete();
    }
}
