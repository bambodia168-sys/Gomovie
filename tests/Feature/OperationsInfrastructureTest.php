<?php

namespace Tests\Feature;

use App\Jobs\ProcessQueueProbe;
use App\Models\User;
use App\Services\Operations\OperationalSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OperationsInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_is_read_only_without_production_claims(): void
    {
        config()->set('gomovie.queue_probes_enabled', false);
        $snapshot = app(OperationalSnapshot::class)->inspect();
        self::assertSame('healthy', $snapshot['checks']['database']);
        self::assertSame('healthy', $snapshot['checks']['schema']);
        self::assertFalse($snapshot['production_release_accepted']);
        self::assertFalse($snapshot['feature_flags']['billing_enabled']);
        self::assertArrayHasKey('queue_probes', $snapshot);
        self::assertArrayNotHasKey('secrets', $snapshot);
        self::assertSame(0, Artisan::call('gomovie:ops-check', ['--json'=>true]));
        self::assertStringContainsString('"database":"healthy"', Artisan::output());
        $this->assertDatabaseCount('queue_probe_runs', 0);
    }

    public function test_health_dashboard_is_limited_to_administrators(): void
    {
        $viewer = User::factory()->create();
        $this->actingAs($viewer)->get('/dashboard/operations')->assertForbidden();

        $editor = User::factory()->create();
        $editor->forceFill(['role'=>'editor'])->save();
        $this->actingAs($editor)->get('/dashboard/operations')->assertForbidden();

        $admin = User::factory()->create();
        $admin->forceFill(['role'=>'admin'])->save();
        $this->actingAs($admin)->get('/dashboard/operations')
            ->assertOk()->assertSee('Application health')->assertSee('Queue worker execution');
    }

    public function test_sync_driver_cannot_forge_worker_health(): void
    {
        config()->set('queue.default', 'sync');
        self::assertSame(1, Artisan::call('gomovie:queue-probe', ['queue'=>'default']));
        $this->assertDatabaseCount('queue_probe_runs', 0);
    }

    public function test_probe_only_completes_when_a_worker_executes_the_job(): void
    {
        config()->set('queue.default', 'database');
        Queue::fake();

        self::assertSame(0, Artisan::call('gomovie:queue-probe', ['queue'=>'gomovie-media']));
        $probe = DB::table('queue_probe_runs')->where('queue_name','gomovie-media')->first();
        self::assertNotNull($probe);
        self::assertNull($probe->processed_at);

        Queue::assertPushed(ProcessQueueProbe::class, function (ProcessQueueProbe $job) use ($probe) {
            self::assertSame($probe->id,$job->probeId);
            self::assertSame('gomovie-media',$job->queueName);
            $job->handle();
            return true;
        });
        self::assertNotNull(DB::table('queue_probe_runs')->where('id',$probe->id)->value('processed_at'));
        self::assertNotNull(app(OperationalSnapshot::class)->inspect()['queue_probes']['gomovie-media']);
    }

    public function test_pruning_removes_old_probes_only(): void
    {
        foreach ([45, 1] as $age) {
            $date = now()->subDays($age);
            DB::table('queue_probe_runs')->insert([
                'id'=>$age===45 ? '11111111-1111-4111-8111-111111111111' : '22222222-2222-4222-8222-222222222222',
                'queue_name'=>'default','dispatched_at'=>$date,
                'expires_at'=>$date->copy()->addMinutes(10),
                'created_at'=>$date,'updated_at'=>$date,
            ]);
        }
        self::assertSame(0, Artisan::call('gomovie:prune-queue-probes', ['--days'=>30]));
        $this->assertDatabaseCount('queue_probe_runs',1);
        self::assertSame(1,Artisan::call('gomovie:prune-queue-probes', ['--days'=>1]));
    }
}
