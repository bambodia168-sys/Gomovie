<?php
/**
 * Read-only, aggregated historical rights reconciliation for ISOLATED QA only.
 * Prints counts, never account information, document names or license references.
 * Requires an authorized local clone with independent DB credentials.
 *
 * php support/gomovie-rights-reconciliation-readonly.php
 */
declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$base = realpath(dirname(__DIR__)) ?: '';
$environment = (string) config('app.env');
if (!preg_match('/(?:qa|staging|acceptance)/i', $base)
    || !in_array($environment, ['testing','staging','local','qa'], true)) {
    fwrite(STDERR, "BLOCKED: authorized isolated QA checkout and non-production APP_ENV required.\n");
    exit(2);
}
foreach (['media_assets','media_ingests','content_rights','rights_evidence',
    'media_publication_reviews'] as $table) {
    if (!Schema::hasTable($table)) {
        fwrite(STDERR, "BLOCKED: required QA schema missing: ".$table."\n");
        exit(3);
    }
}
$report = [
    'mode'=>'READ_ONLY_ISOLATED_QA',
    'environment'=>$environment,
    'media_ingests'=>DB::table('media_ingests')->count(),
    'ready_media_assets'=>DB::table('media_assets')->where('status','ready')->count(),
    'legacy_ready_assets_without_review'=>DB::table('media_assets')
        ->where('status','ready')->whereNull('media_publication_review_id')->count(),
    'ready_assets_without_publisher'=>DB::table('media_assets')
        ->where('status','ready')->whereNull('published_by')->count(),
    'active_owner_declarations'=>DB::table('content_rights')
        ->where('status','active')
        ->whereRaw('LOWER(rights_reference) LIKE ?', ['owner-declared-%'])->count(),
    'active_rights_missing_approved_evidence'=>DB::table('content_rights as rights')
        ->where('rights.status','active')
        ->whereNotExists(fn ($query) => $query->selectRaw('1')
            ->from('rights_evidence as evidence')
            ->whereColumn('evidence.licensable_type','rights.licensable_type')
            ->whereColumn('evidence.licensable_id','rights.licensable_id')
            ->whereColumn('evidence.rights_reference','rights.rights_reference')
            ->where('evidence.status','approved')
            ->where('evidence.starts_at','<=',now())
            ->where('evidence.ends_at','>',now()))
        ->count(),
    'expired_approved_evidence'=>DB::table('rights_evidence')
        ->where('status','approved')->where('ends_at','<=',now())->count(),
    'revoked_evidence'=>DB::table('rights_evidence')
        ->where('status','revoked')->count(),
    'stale_publication_reviews'=>DB::table('media_publication_reviews')
        ->where('status','revoked')->count(),
    'database_modified'=>false,
    'media_modified'=>false,
];
echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
