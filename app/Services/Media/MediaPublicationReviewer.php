<?php

namespace App\Services\Media;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Server-only administrative record of reviewed external evidence.
 * References are operator attestations, NOT independently verified scans.
 */
final class MediaPublicationReviewer
{
    public function review(
        int $ingestId, User $reviewer, string $rightsReference,
        string $scanReference, string $qualityReference, string $expectedManifestSha
    ): int {
        abort_unless($reviewer->is_active && $reviewer->hasVerifiedEmail()
            && in_array($reviewer->role, ['admin','super_admin'], true), 403);
        $package = app(MediaPackageInspector::class)->inspect($ingestId);
        $ingest = $package['ingest'];
        abort_if((int) $reviewer->id === (int) $ingest->uploaded_by
            || (int) $reviewer->id === (int) $ingest->approved_by, 403,
            'The independent reviewer must differ from the uploader and ingest approver.');
        abort_unless(preg_match('/\A[a-f0-9]{64}\z/D', $expectedManifestSha)
            && hash_equals($package['manifest_sha256'], $expectedManifestSha),
            422, 'Expected media checksum differs from the reviewed package.');

        foreach ([$rightsReference, $scanReference, $qualityReference] as $reference) {
            abort_unless(strlen($reference) >= 8 && strlen($reference) <= 180
                && preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9:._\/-]+\z/D', $reference),
                422, 'External evidence reference is missing or invalid.');
        }
        $hasMatchingRights = DB::table('content_rights')
            ->where('licensable_type', $ingest->ingestable_type)
            ->where('licensable_id', $ingest->ingestable_id)
            ->where('status', 'active')->whereNull('territories')
            ->where('rights_reference', $rightsReference)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->exists();
        abort_unless($hasMatchingRights, 403, 'No matching active global distribution-rights record.');

        return DB::transaction(function () use (
            $ingestId, $reviewer, $rightsReference, $scanReference, $qualityReference, $package
        ): int {
            abort_if(DB::table('media_publication_reviews')->where('media_ingest_id', $ingestId)
                ->lockForUpdate()->exists(), 409, 'Existing immutable media review must not be overwritten.');
            $id = DB::table('media_publication_reviews')->insertGetId([
                'media_ingest_id'=>$ingestId, 'reviewed_by'=>$reviewer->id,
                'rights_reference'=>$rightsReference,
                'scan_report_reference'=>$scanReference,
                'quality_report_reference'=>$qualityReference,
                'manifest_sha256'=>$package['manifest_sha256'],
                'package_sha256'=>$package['package_sha256'],
                'status'=>'reviewed', 'reviewed_at'=>now(),
                'created_at'=>now(), 'updated_at'=>now(),
            ]);
            DB::table('admin_audit_events')->insert([
                'actor_id'=>$reviewer->id,'action'=>'media.package_reviewed',
                'resource_type'=>'media_ingest','resource_id'=>$ingestId,
                'details'=>json_encode(['review_id'=>$id,'manifest_sha256'=>$package['manifest_sha256'],
                    'file_count'=>$package['file_count'],
                    'package_sha256'=>$package['package_sha256']], JSON_THROW_ON_ERROR),
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            return $id;
        });
    }
}
