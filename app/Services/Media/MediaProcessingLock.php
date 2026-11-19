<?php

namespace App\Services\Media;

use Closure;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class MediaProcessingLock
{
    /**
     * Media jobs share the private local volume. The OS releases the lock if
     * the worker is killed. Never unlink a lock file while a job might hold it.
     */
    public function run(int $ingestId, Closure $work): void
    {
        $disk = Storage::disk('local');
        if (!$disk->makeDirectory('.processing-locks')) {
            throw new RuntimeException('The media lock directory is unavailable.');
        }

        $lock = fopen($disk->path('.processing-locks/'.$ingestId.'.lock'), 'c');
        if ($lock === false) {
            throw new RuntimeException('The media lock is unavailable.');
        }

        try {
            if (flock($lock, LOCK_EX | LOCK_NB, $wouldBlock)) {
                $work();
            } elseif (!$wouldBlock) {
                throw new RuntimeException('Unable to lock the media ingest.');
            }
        } finally {
            fclose($lock);
        }
    }
}
