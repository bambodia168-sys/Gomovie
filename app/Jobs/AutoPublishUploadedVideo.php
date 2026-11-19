<?php

namespace App\Jobs;

use App\Services\Media\AutomaticPublicationService;
use App\Services\Media\MediaProcessingLock;
use App\Services\Media\PublicationRecovery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class AutoPublishUploadedVideo implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 7500;

    public ?string $retryToken = null;

    public function __construct(public readonly int $ingestId, ?string $retryToken = null)
    {
        $this->retryToken = $retryToken;
        $this->onConnection((string) config('gomovie.media_queue_connection', 'database_media'));
        $this->onQueue('gomovie-media');
    }

    public function handle(AutomaticPublicationService $service): void
    {
        app(MediaProcessingLock::class)->run($this->ingestId, function () use ($service): void {
            try {
                if ($this->retryToken === null) {
                    $service->process($this->ingestId);
                } else {
                    app(PublicationRecovery::class)->resume($this->ingestId, $this->retryToken, $service);
                }
            } catch (Throwable $error) {
                $service->failure($this->ingestId, $error);
                throw $error;
            }
        });
    }
}
