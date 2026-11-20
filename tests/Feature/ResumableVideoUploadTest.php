<?php

namespace Tests\Feature;

use App\Jobs\AutoPublishUploadedVideo;
use App\Models\Episode;
use App\Models\Season;
use App\Models\Series;
use App\Models\User;
use App\Services\Media\ResumableVideoUpload;
use App\Support\VideoUploadLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class ResumableVideoUploadTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/upload-studio/transfers';

    private function administrator(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true, 'email_verified_at' => now()]);
    }

    private function begin(string $workflow, int $size): array
    {
        return $this->postJson(self::URL, [
            'id' => Str::uuid()->toString(),
            'workflow' => $workflow,
            'filename' => 'Resumable example.mp4',
            'size_bytes' => $size,
        ])->assertOk()->assertJsonPath('received_bytes', 0)->json();
    }

    private function chunk(string $id, int $offset, string $bytes): TestResponse
    {
        return $this->call('PUT', self::URL.'/'.$id.'?offset='.$offset, [], [], [], [
            'CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json',
        ], $bytes);
    }

    private function videoBytes(int $size): string
    {
        $mp4Signature = hex2bin('000000186674797069736f6d0000020069736f6d6d703431');

        return str_pad($mp4Signature, $size, "\0");
    }

    public function test_normal_video_resumes_from_confirmed_bytes_and_finalizes_only_once(): void
    {
        Storage::fake('local');
        Queue::fake();
        config()->set([
            'gomovie.automatic_publication_enabled' => true,
            'gomovie.public_hls_enabled' => true,
            'gomovie.transcoding_enabled' => true,
            'gomovie.r2_enabled' => false,
        ]);
        $this->actingAs($this->administrator());
        $bytes = $this->videoBytes(ResumableVideoUpload::CHUNK_BYTES + 80);
        $transfer = $this->begin('quick', strlen($bytes));
        $offset = $transfer['chunk_bytes'];
        $first = substr($bytes, 0, $offset);
        $id = $transfer['id'];
        $fields = ['transfer_id' => $id, 'rights_confirmed' => true];

        $this->postJson('/upload-studio/quick', $fields)->assertConflict();
        $this->chunk($id, 0, $first)->assertOk()->assertJsonPath('received_bytes', $offset);
        $this->chunk($id, 0, $first)->assertConflict();
        $this->getJson(self::URL.'/'.$id)->assertOk()
            ->assertJsonPath('received_bytes', $offset)->assertJsonPath('chunk_hashes.0', hash('sha256', $first));
        // Simulate a request dying after writing a partial tail, before the database receipt.
        Storage::disk('local')->append('upload-transfers/'.$id.'.part', 'unacknowledged bytes');
        for ($position = $offset; $position < strlen($bytes); $position += $offset) {
            $chunk = substr($bytes, $position, $offset);
            $this->chunk($id, $position, $chunk)->assertOk()
                ->assertJsonPath('received_bytes', $position + strlen($chunk));
        }
        $this->postJson('/upload-studio/quick', ['transfer_id' => $id])->assertJsonValidationErrors('rights_confirmed');
        $result = $this->postJson('/upload-studio/quick', $fields)
            ->assertCreated()->assertJsonPath('access', 'free');
        $this->postJson('/upload-studio/quick', $fields)->assertCreated()
            ->assertJsonPath('upload_id', $result->json('upload_id'))
            ->assertJsonPath('watch_url', $result->json('watch_url'));

        $this->assertDatabaseCount('movies', 1);
        $this->assertDatabaseCount('media_ingests', 1);
        $this->assertDatabaseCount('content_rights', 1);
        $this->assertDatabaseHas('movies', ['title' => 'Resumable example', 'status' => 'published']);
        $ingest = DB::table('media_ingests')->sole();
        self::assertSame(hash('sha256', $bytes), $ingest->sha256);
        Storage::disk('local')->assertExists($ingest->object_key);
        Storage::disk('local')->assertMissing('upload-transfers/'.$id.'.part');
        $this->deleteJson(self::URL.'/'.$id)->assertConflict();
        Queue::assertPushed(AutoPublishUploadedVideo::class, 1);

        $this->travel(25)->hours();
        $this->artisan('gomovie:prune-video-transfers')->assertSuccessful();
        Storage::disk('local')->assertExists($ingest->object_key);
        $this->getJson(self::URL.'/'.$id)->assertNotFound();
    }

    public function test_catalog_transfer_keeps_the_episode_and_requires_valid_final_metadata(): void
    {
        Storage::fake('local');
        config()->set('gomovie.automatic_publication_enabled', false);
        $this->actingAs($this->administrator());
        $series = Series::create(['title' => 'Transfer Show', 'slug' => 'transfer-show']);
        $season = Season::create(['series_id' => $series->id, 'season_number' => 1, 'title' => 'Season 1']);
        $episode = Episode::create(['season_id' => $season->id, 'episode_number' => 1, 'title' => 'First episode']);
        $bytes = $this->videoBytes(1024);
        $id = $this->begin('catalog', strlen($bytes))['id'];
        $this->chunk($id, 0, $bytes)->assertOk();
        $this->postJson('/upload-studio/quick', ['transfer_id' => $id, 'rights_confirmed' => true])->assertNotFound();
        $this->postJson('/upload-studio', ['transfer_id' => $id, 'type' => 'episode'])->assertJsonValidationErrors('title_id');

        $fields = ['transfer_id' => $id, 'type' => 'episode', 'title_id' => $episode->id];
        $this->postJson('/upload-studio', [...$fields, 'video' => UploadedFile::fake()->create('separate.mp4')])
            ->assertJsonValidationErrors('video');
        $result = $this->postJson('/upload-studio', $fields)->assertCreated();
        $this->postJson('/upload-studio', [...$fields, 'title_id' => $episode->id + 1])->assertCreated()
            ->assertJsonPath('upload_id', $result->json('upload_id'))->assertJsonPath('title_id', $episode->id);
        $this->assertDatabaseHas('media_ingests', ['ingestable_type' => 'episode', 'ingestable_id' => $episode->id]);
        $this->assertDatabaseCount('media_ingests', 1);
        $this->assertDatabaseCount('movies', 0);
    }

    public function test_transfers_are_private_size_bounded_and_removable(): void
    {
        Storage::fake('local');
        $owner = $this->administrator();
        $input = [
            'id' => Str::uuid()->toString(), 'workflow' => 'quick', 'filename' => 'owned.mp4', 'size_bytes' => 20,
        ];
        $this->postJson(self::URL, $input)->assertUnauthorized();
        $this->actingAs($owner)->postJson(self::URL, [...$input, 'size_bytes' => VideoUploadLimit::bytes() + 1])
            ->assertJsonValidationErrors('size_bytes');
        $this->postJson(self::URL, [...$input, 'filename' => 'folder\\owned.mp4'])->assertJsonValidationErrors('filename');
        $id = $this->postJson(self::URL, $input)->assertOk()->json('id');
        $this->postJson(self::URL, $input)->assertOk()->assertJsonPath('id', $id);
        $this->actingAs($this->administrator())->postJson(self::URL, $input)->assertNotFound();
        $this->getJson(self::URL.'/'.$id)->assertNotFound();
        $this->chunk($id, 0, str_repeat('a', 20))->assertNotFound();
        $this->deleteJson(self::URL.'/'.$id)->assertNotFound();

        $this->actingAs($owner);
        $this->chunk($id, 0, 'short')->assertUnprocessable();
        $this->chunk($id, 0, str_repeat('a', 21))->assertUnprocessable();
        $this->getJson(self::URL.'/'.$id)->assertJsonPath('received_bytes', 0);
        $this->chunk($id, 0, str_repeat('a', 20))->assertOk();
        $this->postJson('/upload-studio/quick', ['transfer_id' => $id, 'rights_confirmed' => true])
            ->assertJsonValidationErrors('video');
        $this->deleteJson(self::URL.'/'.$id)->assertNoContent();
        $this->getJson(self::URL.'/'.$id)->assertNotFound();
        Storage::disk('local')->assertMissing('upload-transfers/'.$id.'.part');

        $expired = $this->begin('quick', 20)['id'];
        $this->chunk($expired, 0, str_repeat('a', 20))->assertOk();
        $this->travel(25)->hours();
        $this->chunk($expired, 0, str_repeat('a', 20))->assertNotFound();
        $this->artisan('gomovie:prune-video-transfers')->assertSuccessful();
        Storage::disk('local')->assertMissing('upload-transfers/'.$expired.'.part');
        $this->assertDatabaseCount('video_upload_transfers', 0);
        $this->assertDatabaseCount('media_ingests', 0);
    }
}
