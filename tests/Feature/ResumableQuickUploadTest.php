<?php

namespace Tests\Feature;

use App\Jobs\AutoPublishUploadedVideo;
use App\Models\User;
use App\Services\Media\ResumableUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ResumableQuickUploadTest extends TestCase
{
    use RefreshDatabase;

    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) @unlink($file);
        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role'=>'admin','is_active'=>true,'email_verified_at'=>now()])->save();
        return $user->fresh();
    }

    private function openPrivateSession(User $user, int $size): array
    {
        $response = $this->actingAs($user)->postJson('/upload-studio/resumable', [
            'title'=>'A licensed original private QA video',
            'description'=>'Synthetic bytes for isolated review only',
            'filename'=>'synthetic-original.mp4',
            'access'=>'private',
            'rights_confirmed'=>true,
            'size_bytes'=>$size,
        ]);
        $response->assertCreated()->assertJsonPath('received_bytes', 0)
            ->assertJsonPath('status','uploading')
            ->assertJsonPath('chunk_size',ResumableUploadService::CHUNK_SIZE);
        return $response->json();
    }

    private function videoBytes(): string
    {
        // Magic bytes identify MP4 via finfo; synthetic fixture contains no media content.
        $head = hex2bin('000000186674797069736f6d0000020069736f6d69736f32');
        return $head.str_repeat("\0", ResumableUploadService::CHUNK_SIZE + 31 - strlen($head));
    }

    private function part(string $bytes): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'gomovie-chunk-');
        file_put_contents($path, $bytes);
        $this->temporaryFiles[] = $path;
        return new UploadedFile($path, 'piece.bin', 'application/octet-stream', null, true);
    }

    private function send(string $id, int $offset, string $bytes): \Illuminate\Testing\TestResponse
    {
        return $this->post('/upload-studio/resumable/'.$id.'/chunks', [
            'offset'=>$offset, 'sha256'=>hash('sha256',$bytes),
            'chunk'=>$this->part($bytes),
        ],['Accept'=>'application/json']);
    }

    public function test_resumable_routes_are_disabled_by_default_and_forbidden_for_viewers(): void
    {
        config()->set('gomovie.resumable_uploads_enabled', false);
        $admin=$this->admin();
        $this->actingAs($admin)->postJson('/upload-studio/resumable', [
            'title'=>'QA', 'filename'=>'one.mp4','size_bytes'=>1,'access'=>'private',
            'rights_confirmed'=>true,
        ])->assertNotFound();
        config()->set('gomovie.resumable_uploads_enabled', true);
        $viewer=User::factory()->create();
        $this->actingAs($viewer)->postJson('/upload-studio/resumable', [])->assertForbidden();
        $this->assertDatabaseCount('resumable_upload_sessions',0);
        $this->assertDatabaseCount('media_ingests',0);
    }

    public function test_private_only_metadata_validation_and_owner_scoped_status(): void
    {
        Storage::fake('local');
        config()->set('gomovie.resumable_uploads_enabled', true);
        $owner=$this->admin();
        $this->actingAs($owner)->postJson('/upload-studio/resumable',[
            'title'=>'Fake public access', 'filename'=>'one.mp4',
            'size_bytes'=>512, 'access'=>'free', 'rights_confirmed'=>true,
        ])->assertUnprocessable();
        $session=$this->openPrivateSession($owner, 100);
        $id=$session['session_id'];
        $this->getJson('/upload-studio/resumable/'.$id)
            ->assertOk()->assertJsonPath('received_bytes',0)->assertJsonPath('upload_id',null);
        $other=$this->admin();
        $this->actingAs($other)->getJson('/upload-studio/resumable/'.$id)->assertNotFound();
        $this->post('/upload-studio/resumable/'.$id.'/chunks', [
            'offset'=>0,'sha256'=>hash('sha256','abc'),'chunk'=>$this->part('abc'),
        ],['Accept'=>'application/json'])->assertNotFound();
        $this->assertDatabaseCount('content_rights',0);
        $this->assertDatabaseCount('media_assets',0);
    }

    public function test_verified_chunks_are_ordered_idempotent_and_never_playable(): void
    {
        Storage::fake('local');
        config()->set('gomovie.resumable_uploads_enabled', true);
        $owner=$this->admin();
        $bytes=$this->videoBytes();
        $session=$this->openPrivateSession($owner, strlen($bytes));
        $id=$session['session_id'];
        $first=substr($bytes,0,ResumableUploadService::CHUNK_SIZE);
        $last=substr($bytes,ResumableUploadService::CHUNK_SIZE);
        $this->send($id,ResumableUploadService::CHUNK_SIZE,$last)->assertStatus(409);
        $this->send($id,0,$first)->assertOk()
            ->assertJsonPath('received_bytes',ResumableUploadService::CHUNK_SIZE);
        $this->send($id,0,$first)->assertOk()
            ->assertJsonPath('received_bytes',ResumableUploadService::CHUNK_SIZE);
        $this->send($id,ResumableUploadService::CHUNK_SIZE,$last)->assertOk()
            ->assertJsonPath('received_bytes',strlen($bytes));
        $this->assertDatabaseCount('resumable_upload_chunks',2);
        $this->assertDatabaseCount('movies',0);
        $this->assertDatabaseCount('media_ingests',0);
        $this->assertDatabaseCount('media_assets',0);
        Storage::disk('local')->assertExists('resumable/'.$id.'/chunks/0.part');
        $this->getJson('/upload-studio/resumable/'.$id)
            ->assertOk()->assertJsonPath('status','uploading');
    }

    public function test_full_private_completion_uses_existing_ingest_and_never_republishes(): void
    {
        Storage::fake('local');
        Queue::fake();
        config()->set('gomovie.resumable_uploads_enabled', true);
        config()->set('gomovie.r2_enabled', false);
        config()->set('gomovie.automatic_publication_enabled', false);
        config()->set('gomovie.public_hls_enabled', false);
        config()->set('gomovie.transcoding_enabled', false);
        $owner=$this->admin();
        $bytes=$this->videoBytes();
        $session=$this->openPrivateSession($owner,strlen($bytes));
        $id=$session['session_id'];
        $this->send($id,0,substr($bytes,0,ResumableUploadService::CHUNK_SIZE))->assertOk();
        $this->send($id,ResumableUploadService::CHUNK_SIZE,
            substr($bytes,ResumableUploadService::CHUNK_SIZE))->assertOk();

        $response=$this->postJson('/upload-studio/resumable/'.$id.'/complete',[]);
        $response->assertCreated()->assertJsonPath('status','completed');
        $ingestId=(int) $response->json('upload_id');
        self::assertGreaterThan(0,$ingestId);
        $ingest=DB::table('media_ingests')->where('id',$ingestId)->sole();
        self::assertSame($id,$ingest->resumable_upload_session_id);
        self::assertSame('quarantined',$ingest->status);
        self::assertSame('pending',$ingest->security_scan_status);
        self::assertSame('pending',$ingest->rights_review_status);
        self::assertNull($ingest->approved_by);
        self::assertNull($ingest->approved_at);
        self::assertSame('local',$ingest->disk);
        self::assertSame(hash('sha256',$bytes),$ingest->sha256);
        Storage::disk('local')->assertExists($ingest->object_key);
        $this->assertDatabaseCount('movies',1);
        $this->assertDatabaseCount('resumable_upload_sessions',1);
        $this->assertDatabaseCount('content_rights',0);
        $this->assertDatabaseCount('media_assets',0);
        Queue::assertNotPushed(AutoPublishUploadedVideo::class);
        $this->assertDatabaseHas('movies',['id'=>$response->json('movie_id'),'status'=>'draft']);
        // No duplicate title or ingest can be created by replaying completion.
        $this->postJson('/upload-studio/resumable/'.$id.'/complete',[])
            ->assertOk()->assertJsonPath('upload_id',$ingestId);
        $this->assertDatabaseCount('movies',1);
        $this->assertDatabaseCount('media_ingests',1);
    }

    public function test_bad_digest_conflicting_retry_and_changed_permissions_fail_closed(): void
    {
        Storage::fake('local');
        config()->set('gomovie.resumable_uploads_enabled', true);
        $owner = $this->admin();
        $session = $this->openPrivateSession($owner, 3);
        $id = $session['session_id'];

        $this->post('/upload-studio/resumable/'.$id.'/chunks', [
            'offset' => 0,
            'sha256' => str_repeat('0', 64),
            'chunk' => $this->part('abc'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->getJson('/upload-studio/resumable/'.$id)
            ->assertJsonPath('received_bytes', 0);
        $this->send($id, 0, 'abc')->assertOk();
        $this->post('/upload-studio/resumable/'.$id.'/chunks', [
            'offset' => 0,
            'sha256' => hash('sha256', 'abd'),
            'chunk' => $this->part('abd'),
        ], ['Accept' => 'application/json'])->assertStatus(409);
        $this->assertDatabaseCount('resumable_upload_chunks', 1);
        $this->assertDatabaseCount('media_ingests', 0);

        $owner->forceFill(['role' => 'viewer'])->save();
        $this->actingAs($owner->fresh())
            ->postJson('/upload-studio/resumable/'.$id.'/complete', [])
            ->assertForbidden();
        $this->deleteJson('/upload-studio/resumable/'.$id)->assertForbidden();
    }

    public function test_per_owner_session_quota_applies_to_uploading_and_stuck_sessions(): void
    {
        Storage::fake('local');
        config()->set('gomovie.resumable_uploads_enabled', true);
        $owner = $this->admin();
        $one = $this->openPrivateSession($owner, 1);
        $two = $this->openPrivateSession($owner, 1);
        $three = $this->openPrivateSession($owner, 1);
        DB::table('resumable_upload_sessions')
            ->where('id', $three['session_id'])->update(['status' => 'finalizing']);
        $this->postJson('/upload-studio/resumable', [
            'title' => 'Fourth private original',
            'filename' => 'fourth.mp4', 'access' => 'private',
            'rights_confirmed' => true, 'size_bytes' => 1,
        ])->assertStatus(429);
        $this->deleteJson('/upload-studio/resumable/'.$one['session_id'])->assertOk();
        $fourth = $this->openPrivateSession($owner, 1);
        self::assertNotSame($two['session_id'], $fourth['session_id']);
        $this->assertDatabaseCount('media_ingests', 0);
    }

    public function test_cancel_discards_private_chunks_but_never_deletes_completed_media(): void
    {
        Storage::fake('local');
        config()->set('gomovie.resumable_uploads_enabled', true);
        $owner=$this->admin();
        $session=$this->openPrivateSession($owner,3);
        $id=$session['session_id'];
        $this->send($id,0,'abc')->assertOk();
        $this->deleteJson('/upload-studio/resumable/'.$id)->assertOk()
            ->assertJsonPath('status','cancelled');
        Storage::disk('local')->assertMissing('resumable/'.$id.'/chunks/0.part');
        $this->send($id,0,'abc')->assertStatus(409);
        $this->assertDatabaseCount('media_ingests',0);
    }
}
