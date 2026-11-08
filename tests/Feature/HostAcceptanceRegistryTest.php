<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Operations\HostAcceptanceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class HostAcceptanceRegistryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role'=>$role])->save();
        return $user->fresh();
    }

    public function test_host_evidence_requires_two_admins_and_never_certifies_production(): void
    {
        $registry = app(HostAcceptanceRegistry::class);
        $submitter = $this->admin('super_admin');
        $reviewer = $this->admin('super_admin');
        self::assertSame(0,$registry->report()['targets']['aapanel']['accepted']);
        $registry->submit('aapanel','offsite_restore',$submitter,'RECOVERY-DRILL-2026-11');
        self::assertSame('submitted', $registry->report()['targets']['aapanel']['checks'][3]['status']);
        try {
            $registry->decide('aapanel','offsite_restore',$submitter,'accept');
            self::fail('Self-review was allowed.');
        } catch (HttpException $e) {
            self::assertSame(403,$e->getStatusCode());
        }
        $registry->decide('aapanel','offsite_restore',$reviewer,'accept');
        $report = $registry->report();
        self::assertSame(1,$report['targets']['aapanel']['accepted']);
        self::assertSame(0,$report['targets']['cpanel']['accepted']);
        self::assertFalse($report['production_release_accepted']);
        self::assertFalse($report['manual_evidence_fully_accepted']);
        $this->assertDatabaseHas('admin_audit_events',[
            'actor_id'=>$reviewer->id,'action'=>'acceptance.evidence_accepted',
        ]);
        try {
            $registry->submit('aapanel','offsite_restore',$submitter,'REPLACEMENT-DRILL-ID');
            self::fail('Accepted evidence was overwritten.');
        } catch (HttpException $e) {
            self::assertSame(409,$e->getStatusCode());
        }
    }

    public function test_rejected_evidence_can_be_resubmitted(): void
    {
        $registry = app(HostAcceptanceRegistry::class);
        $submitter = $this->admin();
        $reviewer = $this->admin('super_admin');
        $registry->submit('cpanel','smtp',$submitter,'SMTP-FAILED-2026');
        $registry->decide('cpanel','smtp',$reviewer,'reject');
        $registry->submit('cpanel','smtp',$submitter,'SMTP-RETEST-2026');
        $this->assertDatabaseHas('host_acceptance_evidence',[
            'target'=>'cpanel','control'=>'smtp','status'=>'submitted',
            'evidence_reference'=>'SMTP-RETEST-2026',
        ]);
    }

    public function test_viewers_cannot_submit_or_access_system_health(): void
    {
        $viewer = User::factory()->create();
        try {
            app(HostAcceptanceRegistry::class)->submit('aapanel','clean_install',$viewer,'VALID-EVIDENCE-ID');
            self::fail('Viewer submitted operator evidence.');
        } catch (HttpException $e) {
            self::assertSame(403,$e->getStatusCode());
        }
        $this->actingAs($viewer)->get('/dashboard/operations')->assertForbidden();
        $admin = $this->admin();
        $this->actingAs($admin)->get('/dashboard/operations')
            ->assertOk()->assertSee('aaPanel')->assertSee('cPanel')
            ->assertSee('Production release accepted: No');
    }
}
