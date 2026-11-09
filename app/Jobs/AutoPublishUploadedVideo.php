<?php

namespace App\Jobs;

use App\Services\Media\AutomaticPublicationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class AutoPublishUploadedVideo implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 7500;

    public function __construct(public readonly int $ingestId)
    {
        $this->onConnection((string) config('gomovie.media_queue_connection', 'database_media'));
        $this->onQueue('gomovie-media');
    }

    public function handle(AutomaticPublicationService $service): void
    {
        try {
            $service->process($this->ingestId);
        } catch (Throwable $error) {
            $service->failure($this->ingestId, $error);
            throw $error;
        }
    }
}
