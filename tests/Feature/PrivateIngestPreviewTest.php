<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PrivateIngestPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => true, 'email_verified_at' => now()])->save();
        return $user->fresh();
    }

    private function storedUpload(User $user, string $status = 'quarantined'): array
    {
        $movie = Movie::create([
            'title' => 'Preview Private Upload', 'slug' => 'preview-private-upload',
            'status' => 'published', 'is_premium' => false,
        ]);
        $key = 'ingests/2026/11/'.Str::uuid().'.mp4';
        $bytes = str_repeat('gomovie private preview bytes ', 150);
        Storage::disk('local')->put($key, $bytes);

        $id = DB::table('media_ingests')->insertGetId([
            'ingestable_type' => 'movie', 'ingestable_id' => $movie->id,
            'uploaded_by' => $user->id, 'disk' => 'local',
            'object_key' => $key, 'mime' => 'video/mp4',
            'original_name' => 'uploaded-movie.mp4',
            'size_bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes),
            'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$movie, $key, (int) $id];
    }

    public function test_verified_admin_sees_private_original_in_existing_movie_player(): void
    {
        Storage::fake('local');
        config()->set('gomovie.edge_enabled', false);
        $admin = $this->admin();
        [$movie, $key, $id] = $this->storedUpload($admin);
        $preview = route('admin.media.ingest-preview', ['ingest' => $id]);

        $this->get('/watch/movie/'.$movie->slug)->assertOk()
            ->assertDontSee('PRIVATE ADMIN PREVIEW');

        $this->actingAs($admin)->get('/watch/movie/'.$movie->slug)->assertOk()
            ->assertSee('PRIVATE ADMIN PREVIEW')
            ->assertSee('data-gomovie-private-preview')
            ->assertSee($preview)
            ->assertDontSee('Secure playback is being prepared');

        $this->get($preview)->assertOk()
            ->assertHeader('Content-Type', 'video/mp4')
            ->assertHeader('X-Gomovie-Preview', 'private-unpublished');

        // Must not publish assets, mark rights verified, or expose uploaded
        // bytes in public disk, even after an administrator previews.
        $this->assertDatabaseHas('media_ingests', [
            'id' => $id, 'status' => 'quarantined', 'security_scan_status' => 'pending',
            'rights_review_status' => 'pending',
        ]);
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertDatabaseCount('content_rights', 0);
        Storage::disk('local')->assertExists($key);
    }

    public function test_private_stream_supports_browser_byte_ranges(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        [, , $id] = $this->storedUpload($admin);
        $url = route('admin.media.ingest-preview', ['ingest' => $id]);

        $this->actingAs($admin)->withHeaders(['Range' => 'bytes=0-15'])
            ->get($url)->assertStatus(206)->assertHeader('Content-Range',
                'bytes 0-15/'.strlen(str_repeat('gomovie private preview bytes ', 150)));
    }

    public function test_private_preview_is_never_available_to_guests_viewers_or_unrelated_admins(): void
    {
        Storage::fake('local');
        $uploader = $this->admin();
        [$movie, , $id] = $this->storedUpload($uploader);
        $url = route('admin.media.ingest-preview', ['ingest' => $id]);

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs($this->admin())->get($url)->assertNotFound();

        $other = User::factory()->create();
        $this->actingAs($other)->get('/watch/movie/'.$movie->slug)
            ->assertOk()->assertDontSee($url)->assertDontSee('PRIVATE ADMIN PREVIEW');
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_private_preview_fails_closed_for_rejected_or_tampered_sources(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        [$movie, $key, $id] = $this->storedUpload($admin);
        $url = route('admin.media.ingest-preview', ['ingest' => $id]);

        DB::table('media_ingests')->where('id', $id)->update(['status' => 'rejected']);
        $this->actingAs($admin)->get($url)->assertNotFound();
        $this->get('/watch/movie/'.$movie->slug)->assertOk()
            ->assertDontSee('PRIVATE ADMIN PREVIEW');

        DB::table('media_ingests')->where('id', $id)->update(['status' => 'quarantined']);
        Storage::disk('local')->put($key, 'tampered');
        $this->get($url)->assertStatus(409);
    }
}
