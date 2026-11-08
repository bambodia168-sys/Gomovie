<?php

namespace Tests\Feature;

use App\Console\Commands\DispatchQueueProbe;
use App\Jobs\ProcessQueueProbe;
use App\Jobs\TranscodeApprovedIngest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MediaQueueIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_jobs_use_isolated_connection_with_safe_retry_window(): void
    {
        $job = new TranscodeApprovedIngest(42);
        self::assertSame('database_media', $job->connection);
        self::assertSame('gomovie-media', $job->queue);
        self::assertSame(1, $job->tries);
        self::assertGreaterThan(
            $job->timeout,
            config('queue.connections.'.$job->connection.'.retry_after')
        );

        self::assertSame(7800,config('queue.connections.database_media.retry_after'));
        self::assertSame(7800,config('queue.connections.redis_media.retry_after'));
        self::assertSame('redis',config('queue.connections.redis_media.driver'));
    }

    public function test_media_queue_probe_uses_media_connection_not_short_queue(): void
    {
        config()->set('queue.default','database');
        config()->set('gomovie.media_queue_connection','database_media');
        Queue::fake();

        self::assertSame(0,Artisan::call('gomovie:queue-probe',['queue'=>'gomovie-media']));
        Queue::assertPushed(ProcessQueueProbe::class,fn(ProcessQueueProbe $job)=>
            $job->connection === 'database_media' && $job->queue === 'gomovie-media');
        $this->assertDatabaseCount('queue_probe_runs',1);
    }

    public function test_media_queue_probe_rejects_shared_short_retry_connection(): void
    {
        config()->set('queue.default','database');
        config()->set('gomovie.media_queue_connection','database');
        Queue::fake();

        self::assertSame(1,Artisan::call('gomovie:queue-probe',['queue'=>'gomovie-media']));
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('queue_probe_runs',0);
    }

    public function test_isolated_media_worker_never_runs_when_feature_is_disabled(): void
    {
        config()->set('gomovie.transcoding_enabled',false);
        $job=new TranscodeApprovedIngest(9999);
        try {
            $job->handle(app(\App\Services\Media\TranscodingService::class));
            self::fail('Disabled transcoding must fail closed.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            self::assertSame(503,$exception->getStatusCode());
        }
    }
}
