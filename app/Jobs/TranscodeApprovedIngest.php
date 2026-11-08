<?php

namespace App\Jobs;

use App\Services\Media\TranscodingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TranscodeApprovedIngest implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 7500;

    public function __construct(public readonly int $ingestId)
    {
        $this->onConnection((string) config('gomovie.media_queue_connection', 'database_media'));
        $this->onQueue('gomovie-media');
    }

    public function handle(TranscodingService $service): void
    {
        $service->encode($this->ingestId);
    }
}
