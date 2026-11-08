<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\User;
use App\Services\Media\MediaPackageInspector;
use App\Services\Media\MediaPublicationReviewer;
use App\Services\Media\PrivateMediaStagingPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class MediaPublicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private array $temporaryPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryPaths as $path) {
            Storage::disk('local')->deleteDirectory($path);
        }
        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role'=>'admin'])->save();
        return $user->fresh();
    }

    private function encodedPackage(): array
    {
        $uploader = $this->admin();
        $approver = $this->admin();
        $movie = Movie::create([
            'title'=>'Authorized Synthetic Test', 'slug'=>'authorized-synthetic-test-'.strtolower(Str::random(7)),
            'status'=>'published', 'is_premium'=>false,
        ]);
        DB::table('content_rights')->insert([
            'licensable_type'=>'movie', 'licensable_id'=>$movie->id,
            'rights_reference'=>'TEST-GLOBAL-LICENSE-1', 'territories'=>null,
            'status'=>'active', 'created_at'=>now(), 'updated_at'=>now(),
        ]);
        $disk = Storage::disk('local');
        $sourceKey = 'ingests/2026/11/'.Str::uuid().'.mp4';
        $sourceData = 'SYNTHETIC SOURCE, NOT PRODUCTION MEDIA';
        $disk->put($sourceKey, $sourceData);
        $this->temporaryPaths[] = dirname($sourceKey);

        $id = DB::table('media_ingests')->insertGetId([
            'ingestable_type'=>'movie', 'ingestable_id'=>$movie->id,
            'uploaded_by'=>$uploader->id, 'approved_by'=>$approver->id,
            'disk'=>'local','object_key'=>$sourceKey,'original_name'=>'test.mp4',
            'mime'=>'video/mp4', 'size_bytes'=>strlen($sourceData),
            'sha256'=>hash('sha256',$sourceData),
            'status'=>'encoded', 'security_scan_status'=>'clean',
            'rights_review_status'=>'verified', 'approved_at'=>now(),
            'encoded_at'=>now(), 'created_at'=>now(), 'updated_at'=>now(),
        ]);
        $prefix = 'transcodes/staging/'.$id.'/'.Str::uuid();
        $master = "#EXTM3U\n#EXT-X-VERSION:3\n#EXT-X-STREAM-INF:BANDWIDTH=1200000\n480p/index.m3u8\n";
        $variant = "#EXTM3U\n#EXT-X-VERSION:3\n#EXT-X-TARGETDURATION:6\n#EXT-X-MEDIA-SEQUENCE:0\n#EXT-X-PLAYLIST-TYPE:VOD\n#EXTINF:1.000,\nsegment_00000.ts\n#EXT-X-ENDLIST\n";
        $disk->put($prefix.'/master.m3u8', $master);
        $disk->put($prefix.'/480p/index.m3u8', $variant);
        $disk->put($prefix.'/480p/segment_00000.ts', 'SYNTHETIC TS DATA');
        $this->temporaryPaths[] = $prefix;
        DB::table('media_ingests')->where('id',$id)->update([
            'encoded_manifest_key'=>$prefix.'/master.m3u8',
        ]);
        return ['id'=>$id,'uploader'=>$uploader, 'approver'=>$approver,
            'movie'=>$movie, 'master_sha'=>hash('sha256',$master), 'prefix'=>$prefix];
    }

    public function test_hls_review_and_private_stage_require_independent_administrators(): void
    {
        Storage::fake('s3');
        $package = $this->encodedPackage();
        $id = $package['id'];
        $reviewer = $this->admin();
        $publisher = $this->admin();

        try {
            app(MediaPublicationReviewer::class)->review($id,$package['uploader'],
                'TEST-GLOBAL-LICENSE-1','SCAN-REPORT-1','QUALITY-REPORT-1',$package['master_sha']);
            self::fail('Uploader self-review should be rejected.');
        } catch (HttpException $e) {
            self::assertSame(403, $e->getStatusCode());
        }

        $reviewId = app(MediaPublicationReviewer::class)->review(
            $id,$reviewer,'TEST-GLOBAL-LICENSE-1','SCAN-REPORT-1',
            'QUALITY-REPORT-1',$package['master_sha']
        );
        $this->assertDatabaseHas('admin_audit_events',[
            'action'=>'media.package_reviewed','resource_id'=>$id,'actor_id'=>$reviewer->id,
        ]);
        $this->assertDatabaseHas('media_publication_reviews',[
            'id'=>$reviewId,'media_ingest_id'=>$id,'status'=>'reviewed',
        ]);

        config()->set('gomovie.media_staging_enabled', false);
        try {
            app(PrivateMediaStagingPublisher::class)->stage($id, $publisher);
            self::fail('Disabled staging must fail closed.');
        } catch (HttpException $e) {
            self::assertSame(503, $e->getStatusCode());
        }

        config()->set('gomovie.media_staging_enabled', true);
        // Master playlist is unchanged: staged segment tampering must still
        // invalidate the complete package fingerprint captured at review.
        $segmentKey = $package['prefix'].'/480p/segment_00000.ts';
        Storage::disk('local')->put($segmentKey, 'POST REVIEW TAMPERING');
        try {
            app(PrivateMediaStagingPublisher::class)->stage($id,$publisher);
            self::fail('Segment tampering should invalidate the reviewed package.');
        } catch (HttpException $e) {
            self::assertSame(403,$e->getStatusCode());
        }
        Storage::disk('local')->put($segmentKey, 'SYNTHETIC TS DATA');

        try {
            app(PrivateMediaStagingPublisher::class)->stage($id,$reviewer);
            self::fail('Reviewer should not stage their own reviewed package.');
        } catch (HttpException $e) {
            self::assertSame(403, $e->getStatusCode());
        }

        $batchId = app(PrivateMediaStagingPublisher::class)->stage($id, $publisher);
        $batch = DB::table('media_publication_batches')->where('id',$batchId)->first();
        self::assertSame('staged', $batch->status);
        self::assertSame(3, (int) $batch->file_count);
        self::assertSame('private', Storage::disk('s3')->visibility($batch->manifest_key));
        self::assertTrue(Storage::disk('s3')->exists($batch->manifest_key));
        $this->assertDatabaseHas('admin_audit_events',[
            'action'=>'media.private_package_staged','resource_id'=>$id,
        ]);
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertDatabaseHas('media_ingests',['id'=>$id,'status'=>'encoded']);

        try {
            app(PrivateMediaStagingPublisher::class)->stage($id,$publisher);
            self::fail('The same package must not be staged twice.');
        } catch (HttpException $e) {
            self::assertSame(409,$e->getStatusCode());
        }
    }

    public function test_review_rejects_tampering_external_hls_urls_and_rights_mismatch(): void
    {
        $package = $this->encodedPackage();
        $reviewer = $this->admin();
        try {
            app(MediaPublicationReviewer::class)->review($package['id'],$reviewer,
                'WRONG-LICENSE-REFERENCE','SCAN-REPORT-1','QUALITY-REPORT-1',$package['master_sha']);
            self::fail('An unrelated rights reference should be refused.');
        } catch (HttpException $e) {
            self::assertSame(403,$e->getStatusCode());
        }

        Storage::disk('local')->put($package['prefix'].'/480p/index.m3u8',
            "#EXTM3U\n#EXT-X-KEY:METHOD=AES-128,URI=\"https://attacker.example/key\"\n#EXTINF:6,\nsegment_00000.ts\n#EXT-X-ENDLIST\n");
        try {
            app(MediaPackageInspector::class)->inspect($package['id']);
            self::fail('External key URL should be rejected.');
        } catch (HttpException $e) {
            self::assertSame(422, $e->getStatusCode());
        }
        $this->assertDatabaseCount('media_publication_reviews',0);
    }

    public function test_viewer_cannot_stage_or_review(): void
    {
        $package = $this->encodedPackage();
        $viewer = User::factory()->create();
        try {
            app(MediaPublicationReviewer::class)->review($package['id'],$viewer,
                'TEST-GLOBAL-LICENSE-1','SCAN-REPORT-1','QUALITY-REPORT-1',$package['master_sha']);
            self::fail('Viewer cannot attest.');
        } catch (HttpException $e) {
            self::assertSame(403,$e->getStatusCode());
        }
        config()->set('gomovie.media_staging_enabled', true);
        try {
            app(PrivateMediaStagingPublisher::class)->stage($package['id'],$viewer);
            self::fail('Viewer cannot stage.');
        } catch (HttpException $e) {
            self::assertSame(403,$e->getStatusCode());
        }
        $this->assertDatabaseCount('media_assets',0);
    }
}
