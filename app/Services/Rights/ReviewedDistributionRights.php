<?php

namespace App\Services\Rights;

use Illuminate\Support\Facades\DB;

/**
 * Current human-reviewed, scanner-checked evidence is necessary for PUBLIC HLS.
 * An opaque reference or preexisting content_rights row alone is insufficient.
 */
final class ReviewedDistributionRights
{
    public function allows(string $type, int $id, string $reference): bool
    {
        if (!in_array($type, ['movie', 'episode'], true) || $id < 1
            || $reference === ''
            || str_starts_with(strtoupper($reference), 'OWNER-DECLARED-')) return false;

        return DB::table('rights_evidence as evidence')
            ->join('users as reviewers', 'reviewers.id', '=', 'evidence.reviewed_by')
            ->where('evidence.licensable_type', $type)
            ->where('evidence.licensable_id', $id)
            ->where('evidence.rights_reference', $reference)
            ->where('evidence.status', 'approved')
            ->where('evidence.license_scope', 'global')
            ->whereNotNull('evidence.document_scanned_at')
            ->whereNotNull('evidence.review_reference')
            ->whereNotNull('evidence.reviewed_at')
            ->whereColumn('evidence.submitted_by', '!=', 'evidence.reviewed_by')
            ->where('reviewers.is_active', true)
            ->whereNotNull('reviewers.email_verified_at')
            ->whereIn('reviewers.role', ['admin', 'super_admin'])
            ->where('evidence.starts_at', '<=', now())
            ->where('evidence.ends_at', '>', now())
            ->whereExists(fn ($q) => $q->selectRaw('1')
                ->from('content_rights as granted')
                ->whereColumn('granted.licensable_type', 'evidence.licensable_type')
                ->whereColumn('granted.licensable_id', 'evidence.licensable_id')
                ->whereColumn('granted.rights_reference', 'evidence.rights_reference')
                ->where('granted.status', 'active')
                ->whereNull('granted.territories')
                ->where(fn ($q) => $q->whereNull('granted.starts_at')
                    ->orWhere('granted.starts_at', '<=', now()))
                ->where(fn ($q) => $q->whereNull('granted.ends_at')
                    ->orWhere('granted.ends_at', '>', now())))
            ->exists();
    }

    public function requireCurrent(string $type, int $id, string $reference): void
    {
        abort_unless($this->allows($type, $id, $reference), 403,
            'Current independently reviewed distribution evidence is required.');
    }
}
