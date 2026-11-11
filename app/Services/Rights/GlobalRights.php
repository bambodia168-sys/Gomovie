<?php

namespace App\Services\Rights;

use Illuminate\Support\Facades\DB;

/**
 * Only explicit, active, GLOBAL distribution rights are supported.
 * Never infer permission from metadata, regional headers or published status.
 */
final class GlobalRights
{
    public function allows(string $type, int $id): bool
    {
        if (!in_array($type, ['movie', 'episode'], true) || $id < 1) {
            return false;
        }

        return DB::table('content_rights')
            ->where('licensable_type', $type)
            ->where('licensable_id', $id)
            ->where('status', 'active')
            // Self-attestation is never independently verified distribution rights.
            // This also neutralizes legacy OWNER-DECLARED active records.
            ->whereNotNull('rights_reference')
            ->where('rights_reference', 'not like', 'OWNER-DECLARED-%')
            ->whereNull('territories')
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->exists();
    }

    public function requireActive(string $type, int $id): void
    {
        abort_unless($this->allows($type, $id), 403, 'Streaming distribution rights are not active.');
    }
}
