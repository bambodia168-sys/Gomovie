<?php

declare(strict_types=1);

/**
 * Minimal, bounded original-MP4 browser acceptance fixture.
 * Run only via CLI, as the www service account. Does not upload video.
 * The operator uploads the expected synthetic file through Upload Studio.
 * --revoke disables QA playback but preserves audit/history.
 */
use App\Services\Media\AutomaticPublicationService;
use Illuminate\Support\Facades\DB;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}
$mode=$argv[1]??'';
if (!in_array($mode,['--check','--setup','--status','--verify','--revoke'],true)) {
    fwrite(STDERR,"Usage: php browser-public-qa-fixture.php --check|--setup|--status|--verify|--revoke\n");
    exit(2);
}
require_once getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

const QA_SHA='c510082cf911cce99efafceb96466d87b6a750653fe11385372bb93a588e8cf5';
const QA_SLUG='gomovie-browser-qa-c510082cf911';
const QA_REF='GOMOVIE-ORIGINAL-FFMPEG-QA-c510082cf911cce9';
$fail=static function (string $reason): never {
    fwrite(STDERR,"BROWSER_QA_BLOCKED=".$reason."\n");
    exit(8);
};
$movie=DB::table('movies')->where('slug',QA_SLUG)->first();
$rights=$movie ? DB::table('content_rights')
    ->where('licensable_type','movie')->where('licensable_id',$movie->id)
    ->where('rights_reference',QA_REF)->first() : null;

if ($mode==='--check') {
    if (config('gomovie.automatic_publication_enabled')!==true
        || config('gomovie.public_hls_enabled')!==true
        || config('gomovie.transcoding_enabled')!==true) {
        $fail('production_activation_not_confirmed');
    }
    if ($movie || $rights) $fail('fixture_already_exists');
    echo "ORIGINAL_VIDEO_SHA256=".QA_SHA."\n";
    echo "BROWSER_QA_CATALOG_FIXTURE_PREFLIGHT=PASS\n";
    exit(0);
}

if ($mode==='--setup') {
    if ($movie) $fail('fixture_already_exists');
    if (config('gomovie.automatic_publication_enabled')!==true
        || config('gomovie.public_hls_enabled')!==true
        || config('gomovie.transcoding_enabled')!==true) $fail('activation_required');
    DB::transaction(static function (): void {
        $movieId=DB::table('movies')->insertGetId([
            'title'=>'Gomovie Original Playback Browser QA',
            'slug'=>QA_SLUG,
            'overview'=>'Original five-second FFmpeg video test pattern with generated sine tone. Acceptance test only; not third-party footage.',
            'runtime_minutes'=>1,'is_premium'=>false,'status'=>'draft','published_at'=>null,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('content_rights')->insert([
            'licensable_type'=>'movie','licensable_id'=>$movieId,
            'rights_reference'=>QA_REF,
            'territories'=>null, 'starts_at'=>now()->subMinute(),
            'ends_at'=>now()->addDay(), 'status'=>'active',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    });
    $movie=DB::table('movies')->where('slug',QA_SLUG)->first();
    echo "BROWSER_QA_MOVIE_ID=".$movie->id."\n";
    echo "BROWSER_QA_TITLE=Gomovie Original Playback Browser QA\n";
    echo "BROWSER_QA_VIDEO_SHA256=".QA_SHA."\n";
    echo "BROWSER_QA_RIGHTS=ONLY_FOR_GENERATED_ORIGINAL_TEST_PATTERN\n";
    echo "BROWSER_QA_RIGHTS_EXPIRY=24_HOURS\n";
    echo "BROWSER_QA_UPLOAD_STUDIO=https://gomovie.tonsamith.com/upload-studio\n";
    echo "BROWSER_QA_FIXTURE=CREATED\n";
    exit(0);
}

if (!$movie || !$rights) $fail('fixture_missing');
if ((bool)$movie->is_premium || $rights->territories!==null
    || $rights->licensable_type!=='movie'
    || (int)$rights->licensable_id!==(int)$movie->id) $fail('fixture_integrity_changed');

if ($mode==='--revoke') {
    DB::transaction(static function () use ($movie,$rights): void {
        DB::table('content_rights')->where('id',$rights->id)
            ->where('rights_reference',QA_REF)
            ->update(['status'=>'revoked','updated_at'=>now()]);
        DB::table('movies')->where('id',$movie->id)->where('slug',QA_SLUG)
            ->update(['status'=>'archived','published_at'=>null,'updated_at'=>now()]);
    });
    echo "BROWSER_QA_RIGHTS=REVOKED\n";
    echo "BROWSER_QA_TITLE=ARCHIVED\n";
    echo "EXISTING_UNRELATED_CATALOG=UNCHANGED\n";
    echo "BROWSER_QA_ACCESS=DISABLED\n";
    exit(0);
}

$ingests=DB::table('media_ingests')->where('ingestable_type','movie')
    ->where('ingestable_id',$movie->id)->orderByDesc('id')->get();
echo "BROWSER_QA_MOVIE_ID=".$movie->id."\n";
echo "BROWSER_QA_MOVIE_STATUS=".$movie->status."\n";
echo "BROWSER_QA_UPLOAD_COUNT=".$ingests->count()."\n";
if ($ingests->count()!==1) {
    if ($mode==='--verify') $fail('expected_exactly_one_browser_upload');
    exit(0);
}
$ingest=$ingests->first();
$matches=hash_equals(QA_SHA,(string)$ingest->sha256);
echo "BROWSER_QA_ORIGINAL_SHA256=".($matches?'VERIFIED':'MISMATCH')."\n";
echo "BROWSER_QA_INGEST_STATUS=".$ingest->status."\n";
echo "BROWSER_QA_SCAN_STATUS=".$ingest->security_scan_status."\n";
echo "BROWSER_QA_RIGHTS_STATUS=".$ingest->rights_review_status."\n";
if ($ingest->pipeline_error) echo "BROWSER_QA_PIPELINE_ERROR=".$ingest->pipeline_error."\n";
$asset=app(AutomaticPublicationService::class)->readyAsset('movie',(int)$movie->id);
if ($asset) {
    echo "BROWSER_QA_MEDIA_ASSET_ID=".$asset->id."\n";
    echo "BROWSER_QA_MANIFEST=https://gomovie.tonsamith.com/hls/".$asset->id."/master.m3u8\n";
    echo "BROWSER_QA_WATCH=https://gomovie.tonsamith.com/watch/movie/".QA_SLUG."\n";
}
if ($mode==='--verify') {
    if (!$matches || !$asset || $ingest->status!=='encoded'
        || $ingest->security_scan_status!=='clean'
        || $ingest->rights_review_status!=='verified') $fail('media_not_ready_or_wrong_file');
    $media=(int)DB::table('public_media_files')->where('media_asset_id',$asset->id)->count();
    if ($media<3) $fail('verified_public_hls_files_missing');
    echo "BROWSER_QA_REAL_UPLOAD_AND_PUBLIC_ASSET=PASS\n";
    echo "BROWSER_EXTERNAL_GUEST_PLAYBACK=REQUIRES_MANUAL_OR_HTTP_CHECK\n";
}
