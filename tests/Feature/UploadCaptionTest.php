<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class UploadCaptionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'admin'])->save();
        return $user->fresh();
    }

    private function video(): UploadedFile
    {
        $file = UploadedFile::fake()->create('licensed-video.mp4', 5, 'video/mp4');
        file_put_contents($file->getRealPath(), str_repeat('x', 5120));
        return $file;
    }

    private function vtt(string $body = "WEBVTT\n\n00:00:01.000 --> 00:00:03.000\nHello world\n"): UploadedFile
    {
        $file = UploadedFile::fake()->create('captions.vtt', 1, 'text/vtt');
        file_put_contents($file->getRealPath(), $body);
        return $file;
    }

    private function upload(User $admin, Movie $movie): int
    {
        $this->actingAs($admin)->post('/upload-studio', [
            'type' => 'movie', 'title_id' => $movie->id, 'video' => $this->video(),
        ], ['Accept'=>'application/json'])->assertCreated();
        return (int) DB::table('media_ingests')->latest('id')->value('id');
    }

    public function test_private_caption_create_read_replace_update_and_delete(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $movie = Movie::create([
            'title'=>'Caption Studio QA','slug'=>'caption-studio-qa','status'=>'draft','is_premium'=>false,
        ]);
        $id = $this->upload($admin, $movie);
        $prefix = '/upload-studio/'.$id.'/captions';
        $this->actingAs($admin)->getJson($prefix)->assertOk()->assertJsonCount(0,'captions');

        $result = $this->post($prefix, [
            'language'=>'en','label'=>'English (CC)','caption'=>$this->vtt(),'is_default'=>'1',
        ], ['Accept'=>'application/json']);
        self::assertSame(201, $result->getStatusCode(), $result->getContent());
        $result->assertJsonPath('ok',true);
        $row = DB::table('media_ingest_captions')->first();
        self::assertNotNull($row);
        self::assertSame('local',$row->disk);
        self::assertTrue((bool) $row->is_default);
        Storage::disk('local')->assertExists($row->object_key);
        $this->get($prefix.'/'.$row->id.'/file')->assertOk()
            ->assertHeader('Content-Type','text/vtt; charset=utf-8')
            ->assertSee('WEBVTT')->assertSee('Hello world');

        $this->patchJson($prefix.'/'.$row->id, [
            'label'=>'English subtitles','is_default'=>true,
        ])->assertOk()->assertJsonPath('ok',true);
        $this->getJson($prefix)->assertOk()->assertJsonPath('captions.0.label','English subtitles');

        $this->post($prefix, [
            'language'=>'en','label'=>'English replacement','caption'=>$this->vtt(
                "WEBVTT\n\n00:00:00.000 --> 00:00:01.000\nReplaced words\n"),
        ], ['Accept'=>'application/json'])->assertOk()->assertJsonPath('ok',true);
        $this->assertDatabaseCount('media_ingest_captions',1);
        $newRow = DB::table('media_ingest_captions')->first();
        Storage::disk('local')->assertMissing($row->object_key);
        Storage::disk('local')->assertExists($newRow->object_key);
        $this->get($prefix.'/'.$row->id.'/file')->assertOk()->assertSee('Replaced words');

        $this->deleteJson($prefix.'/'.$row->id)->assertOk()->assertJsonPath('ok',true);
        $this->assertDatabaseCount('media_ingest_captions',0);
        Storage::disk('local')->assertMissing($newRow->object_key);
        $this->assertDatabaseHas('media_ingests',['id'=>$id,'status'=>'quarantined']);
        $this->assertDatabaseCount('content_rights',0);
        $this->assertDatabaseCount('media_assets',0);
        $this->assertDatabaseHas('admin_audit_events',['action'=>'media.ingest_caption_deleted']);
    }

    public function test_invalid_vtt_and_unauthorized_access_fail_closed(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $other = $this->admin();
        $movie = Movie::create(['title'=>'Private Caption QA',
            'slug'=>'private-caption-qa','status'=>'draft','is_premium'=>false]);
        $id = $this->upload($admin, $movie);
        $url = '/upload-studio/'.$id.'/captions';

        $this->post($url, [
            'language'=>'en','caption'=>$this->vtt("1\n00:00:01,000 --> 00:00:02,000\nInvalid SRT\n"),
        ], ['Accept'=>'application/json'])->assertStatus(422);
        $this->post($url, [
            'language'=>'en','caption'=>$this->vtt("WEBVTT\n\n00:00:01.000 --> 00:00:02.000\n<script>bad</script>\n"),
        ], ['Accept'=>'application/json'])->assertStatus(422);
        $this->assertDatabaseCount('media_ingest_captions',0);

        $this->post($url, [
            'language'=>'km','caption'=>$this->vtt(),
        ], ['Accept'=>'application/json'])->assertCreated();
        $caption=(int) DB::table('media_ingest_captions')->value('id');
        $this->actingAs($other)->getJson($url)->assertNotFound();
        $this->get($url.'/'.$caption.'/file')->assertNotFound();
        $this->deleteJson($url.'/'.$caption)->assertNotFound();
        $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
        $this->assertDatabaseCount('media_ingest_captions',1);
    }

    public function test_real_caption_tracks_appear_only_in_authorized_private_preview(): void
    {
        Storage::fake('local');
        $admin=$this->admin();
        $movie = Movie::create([
            'title'=>'Subtitle Preview QA','slug'=>'subtitle-preview-qa',
            'status'=>'draft','is_premium'=>false,
        ]);
        $id=$this->upload($admin,$movie);
        $this->post('/upload-studio/'.$id.'/captions',[
            'language'=>'en','caption'=>$this->vtt(),
        ], ['Accept'=>'application/json'])->assertCreated();
        $movie->forceFill(['status'=>'published'])->save();
        $caption=(int) DB::table('media_ingest_captions')->value('id');

        $this->get('/watch/movie/'.$movie->slug)->assertOk()
            ->assertSee('kind="subtitles"',false)
            ->assertSee(route('upload-studio.captions.file', ['ingest'=>$id,'caption'=>$caption]))
            ->assertSee('data-captions-toggle');
        $this->get('/upload-studio?upload='.$id)->assertOk()
            ->assertSee('gm-caption-manager');

        $this->actingAs(User::factory()->create())->get('/watch/movie/'.$movie->slug)
            ->assertOk()->assertDontSee('kind="subtitles"', false);
        $this->assertDatabaseCount('content_rights',0);
        $this->assertDatabaseCount('media_assets',0);
    }
}
