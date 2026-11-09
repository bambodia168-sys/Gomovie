<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\PlayerPreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PlayerSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->forceFill([
            'role' => $role, 'is_active' => true, 'email_verified_at' => now(),
        ])->save();
        return $user->fresh();
    }

    public function test_admin_edits_validated_player_appearance_without_changing_playback_rights(): void
    {
        $admin = $this->user();
        $this->actingAs($admin)->get('/dashboard/player-settings')->assertOk()
            ->assertSee('Player appearance')
            ->assertSee('Playback speed')
            ->assertSee('Save player settings');

        $this->patch('/dashboard/player-settings', [
            'accent' => 'cyan',
            'density' => 'compact',
            'seek_seconds' => 15,
            'auto_hide_seconds' => 5,
            'show_volume' => '1',
            'show_speed' => '0',
            'show_captions' => '1',
            'show_pip' => '0',
            'show_fullscreen' => '1',
        ])->assertRedirect(route('admin.player-settings.edit'));

        $this->assertDatabaseHas('player_preferences', [
            'id' => 1, 'accent' => 'cyan', 'density' => 'compact',
            'seek_seconds' => 15, 'auto_hide_seconds' => 5,
            'show_volume' => 1, 'show_speed' => 0,
            'show_captions' => 1, 'show_pip' => 0, 'show_fullscreen' => 1,
        ]);
        $this->assertDatabaseHas('admin_audit_events', [
            'actor_id' => $admin->id, 'action' => 'player.preferences.updated',
        ]);
        self::assertSame('#22d3ee', PlayerPreferences::accentColor(PlayerPreferences::current()));
        $this->assertDatabaseCount('content_rights', 0);
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertDatabaseCount('media_ingests', 0);
    }

    public function test_viewer_cannot_read_or_mutate_global_player_settings(): void
    {
        $viewer = $this->user('viewer');
        $this->actingAs($viewer)->get('/dashboard/player-settings')->assertForbidden();
        $this->actingAs($viewer)->patch('/dashboard/player-settings', [
            'accent' => 'gold',
        ])->assertForbidden();
        $this->assertDatabaseCount('player_preferences', 0);
        $this->assertDatabaseCount('admin_audit_events', 0);
    }

    public function test_player_settings_reject_unknown_css_and_invalid_options(): void
    {
        $this->actingAs($this->user());
        $this->patch('/dashboard/player-settings', [
            'accent' => 'url(javascript:alert(1))',
            'density' => 'wide',
            'seek_seconds' => 500,
            'auto_hide_seconds' => 99,
            'show_volume' => 'yes',
            'show_speed' => 1,
            'show_captions' => 1,
            'show_pip' => 1,
            'show_fullscreen' => 1,
        ])->assertSessionHasErrors([
            'accent', 'density', 'seek_seconds', 'auto_hide_seconds', 'show_volume',
        ]);
        $this->assertDatabaseCount('player_preferences', 0);
    }

    public function test_modern_controls_are_displayed_for_private_preview_without_public_video_access(): void
    {
        $admin = $this->user();
        $movie = Movie::create([
            'title' => 'Player QA Movie', 'slug' => 'player-qa-movie',
            'status' => 'published', 'is_premium' => false,
        ]);
        DB::table('media_ingests')->insert([
            'ingestable_type' => 'movie',
            'ingestable_id' => $movie->id,
            'uploaded_by' => $admin->id,
            'disk' => 'local',
            'object_key' => 'ingests/2026/11/sample.mp4',
            'mime' => 'video/mp4', 'original_name' => 'private.mp4',
            'size_bytes' => 100, 'sha256' => str_repeat('a', 64),
            'status' => 'quarantined', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($admin)->patch('/dashboard/player-settings', [
            'accent' => 'violet', 'density' => 'compact',
            'seek_seconds' => 30, 'auto_hide_seconds' => 0,
            'show_volume' => 0, 'show_speed' => 1,
            'show_captions' => 0, 'show_pip' => 0, 'show_fullscreen' => 1,
        ])->assertRedirect();

        $this->actingAs($admin)->get('/watch/movie/'.$movie->slug)->assertOk()
            ->assertSee('data-modern-player')
            ->assertSee('data-gomovie-private-preview')
            ->assertSee('data-seek-seconds="30"', false)
            ->assertSee('data-hide-seconds="0"', false)
            ->assertSee('PRIVATE ADMIN PREVIEW')
            ->assertSee('data-gm-timeline')
            ->assertSee('data-playback-speed')
            ->assertSee('data-fullscreen-player')
            ->assertDontSee('data-gm-volume')
            ->assertDontSee('data-captions-toggle')
            ->assertDontSee('data-picture-in-picture')
            ->assertDontSee('data-quality-container')
            ->assertSee('--gm-player-accent:#a78bfa');

        // Explicitly switch away from the administrator session.
        $this->actingAs($this->user('viewer'))
            ->get('/watch/movie/'.$movie->slug)->assertOk()
            ->assertDontSee('data-gomovie-private-preview')
            ->assertDontSee('data-modern-player');
        $this->assertDatabaseHas('media_ingests', [
            'ingestable_id' => $movie->id, 'status' => 'quarantined',
        ]);
        $this->assertDatabaseCount('content_rights', 0);
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_secure_player_uses_same_controls_but_keeps_existing_grant_system(): void
    {
        config()->set('gomovie.edge_enabled', true);
        $admin = $this->user();
        UserProfile::create([
            'user_id' => $admin->id, 'name' => 'Main',
            'slug' => 'main', 'is_kids' => false,
        ]);
        $movie = Movie::create([
            'title' => 'Secure Player UI', 'slug' => 'secure-player-ui',
            'status' => 'published', 'is_premium' => false,
        ]);
        $this->actingAs($admin)->get('/watch/movie/'.$movie->slug)->assertOk()
            ->assertSee('data-gomovie-player')
            ->assertSee('data-modern-player')
            ->assertSee('data-quality-container')
            ->assertSee('data-play-type="movie"', false)
            ->assertDontSee('data-gomovie-private-preview');

        $js = file_get_contents(resource_path('js/player.js'));
        self::assertIsString($js);
        self::assertStringContainsString("'/api/v1/stream/' + type + '/' + titleId + '/token'", $js);
        self::assertStringContainsString("sessionStorage.setItem('gomovie:autoplay-next'", $js);
        self::assertStringContainsString("document.querySelectorAll('[data-modern-player]')", $js);
    }
}
