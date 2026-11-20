<?php

namespace App\Services\Rights;

use Illuminate\Database\Query\Builder;
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

        return $this->activeQuery()->where('licensable_type', $type)
            ->where('licensable_id', $id)->exists();
    }

    public function activeQuery(bool $documented = false): Builder
    {
        return DB::table('content_rights')->where('status', 'active')->whereNull('territories')
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->when($documented, fn ($query) => $query->whereNotNull('rights_reference')->where('rights_reference', '!=', ''));
    }

    public function requireActive(string $type, int $id): void
    {
        abort_unless($this->allows($type, $id), 403, 'Streaming distribution rights are not active.');
    }
}
