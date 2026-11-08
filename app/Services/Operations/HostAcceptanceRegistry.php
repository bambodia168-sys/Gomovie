<?php

namespace App\Services\Operations;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Operator evidence tracker, NOT a substitute for actual aaPanel/cPanel tests.
 * Requires a second, verified super-admin review; it never toggles features.
 */
final class HostAcceptanceRegistry
{
    public const TARGETS = ['aapanel'=>'aaPanel', 'cpanel'=>'cPanel'];

    public const CONTROLS = [
        'clean_install'=>'Clean independent installation',
        'docroot_tls'=>'Public document root and HTTPS/TLS',
        'schema_upgrade'=>'Database migration and upgrade',
        'offsite_restore'=>'Encrypted off-site backup and isolated restore',
        'rollback'=>'Rollback and failure recovery rehearsal',
        'queue_supervision'=>'Application and private-media worker supervision',
        'smtp'=>'External SMTP delivery and account recovery',
        'private_storage'=>'Private object storage, edge origin and access denial',
        'media_rights'=>'Malware evidence, media rights and publication QA',
        'device_acceptance'=>'Physical mobile, tablet, desktop and accessibility',
        'legal_localization'=>'Legal, licensing and native-language review',
        'payments'=>'Payment onboarding, reconciliation and refund compliance',
        'monitoring'=>'Logging, alerting, resource and incident acceptance',
    ];

    public function report(): array
    {
        $rows = DB::table('host_acceptance_evidence')
            ->get(['target','control','status','evidence_reference','reviewed_at'])
            ->keyBy(fn ($row) => $row->target.':'.$row->control);
        $targets = [];
        foreach (self::TARGETS as $key => $label) {
            $checks = [];
            $accepted = 0;
            foreach (self::CONTROLS as $control => $name) {
                $record = $rows->get($key.':'.$control);
                $status = $record?->status ?? 'missing';
                if ($status === 'accepted') $accepted++;
                $checks[] = [
                    'id'=>$control, 'name'=>$name, 'status'=>$status,
                    'evidence_reference'=>$record?->evidence_reference,
                ];
            }
            $targets[$key] = [
                'label'=>$label,'accepted'=>$accepted,
                'total'=>count(self::CONTROLS),'checks'=>$checks,
            ];
        }
        return [
            'targets'=>$targets,
            'manual_evidence_fully_accepted'=>collect($targets)->every(
                fn ($target) => $target['accepted'] === $target['total']),
            'production_release_accepted'=>false,
        ];
    }

    public function submit(string $target, string $control, User $actor, string $evidence): void
    {
        $this->validateTarget($target, $control);
        $this->requireAdministrator($actor);
        abort_unless(strlen($evidence) >= 8 && strlen($evidence) <= 200
            && preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9:._\/-]+\z/D', $evidence),
            422, 'Use a non-secret external evidence or ticket reference.');

        DB::transaction(function () use ($target, $control, $actor, $evidence): void {
            $existing = DB::table('host_acceptance_evidence')
                ->where('target',$target)->where('control',$control)->lockForUpdate()->first();
            abort_if($existing && $existing->status !== 'rejected', 409,
                'Existing evidence requires a separate reviewer decision.');
            $values = [
                'status'=>'submitted', 'evidence_reference'=>$evidence,
                'submitted_by'=>$actor->id,'reviewed_by'=>null,'reviewed_at'=>null,'updated_at'=>now(),
            ];
            if ($existing) {
                DB::table('host_acceptance_evidence')->where('id',$existing->id)->update($values);
            } else {
                DB::table('host_acceptance_evidence')->insert($values + [
                    'target'=>$target,'control'=>$control,'created_at'=>now(),
                ]);
            }
            $this->audit($actor,'acceptance.evidence_submitted',$target,$control);
        });
    }

    public function decide(string $target, string $control, User $reviewer, string $decision): void
    {
        $this->validateTarget($target, $control);
        abort_unless(in_array($decision, ['accept','reject'], true),422);
        abort_unless($reviewer->role === 'super_admin'
            && $reviewer->is_active && $reviewer->hasVerifiedEmail(),403);

        DB::transaction(function () use ($target, $control, $reviewer, $decision): void {
            $record = DB::table('host_acceptance_evidence')
                ->where('target',$target)->where('control',$control)->lockForUpdate()->first();
            abort_unless($record && $record->status === 'submitted',409,
                'No pending independent host acceptance evidence.');
            abort_if((int) $record->submitted_by === (int) $reviewer->id,403,
                'Evidence must be reviewed by a different administrator.');
            DB::table('host_acceptance_evidence')->where('id',$record->id)
                ->where('status','submitted')->update([
                    'status'=>$decision === 'accept' ? 'accepted' : 'rejected',
                    'reviewed_by'=>$reviewer->id,'reviewed_at'=>now(),'updated_at'=>now(),
                ]);
            $this->audit($reviewer,'acceptance.evidence_'.$decision.'ed',$target,$control);
        });
    }

    private function validateTarget(string $target, string $control): void
    {
        abort_unless(array_key_exists($target,self::TARGETS)
            && array_key_exists($control,self::CONTROLS),422,
            'Unknown host acceptance target or control.');
    }

    private function requireAdministrator(User $actor): void
    {
        abort_unless($actor->is_active && $actor->hasVerifiedEmail()
            && in_array($actor->role, ['admin','super_admin'], true),403);
    }

    private function audit(User $actor, string $action, string $target, string $control): void
    {
        DB::table('admin_audit_events')->insert([
            'actor_id'=>$actor->id, 'action'=>$action,
            'resource_type'=>'host_acceptance', 'resource_id'=>null,
            'details'=>json_encode(['target'=>$target,'control'=>$control], JSON_THROW_ON_ERROR),
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }
}
