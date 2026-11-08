<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class TranscodeGate
{
    /**
     * No route/API can approve or run this pipeline. Security scan and licensing
     * approvals must be provided by independently verified operator workflows.
     */
    public function validate(int $ingestId): object
    {
        abort_unless(config('gomovie.transcoding_enabled') === true, 503,
            'Private transcoding is disabled until operational acceptance.');

        $ingest = DB::table('media_ingests')->where('id', $ingestId)->first();
        abort_unless($ingest, 404);
        abort_unless(
            $ingest->status === 'approved'
                && $ingest->security_scan_status === 'clean'
                && $ingest->rights_review_status === 'verified'
                && $ingest->approved_at !== null
                && $ingest->approved_by !== null,
            403,
            'Security scanning, rights review, and operator approval are required.'
        );
        abort_unless(in_array($ingest->ingestable_type, ['movie', 'episode'], true), 403);

        app(\App\Services\Rights\GlobalRights::class)
            ->requireActive($ingest->ingestable_type, (int) $ingest->ingestable_id);

        return $ingest;
    }
}
