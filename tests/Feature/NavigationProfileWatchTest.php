<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class NavigationProfileWatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_has_language_theme_and_sign_in_actions_without_staff_links(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('gm-profile-popover')
            ->assertSee('gm-locale-popover')
            ->assertSee('data-gm-theme-toggle')
            ->assertSee('Sign In')
            ->assertSee('Sign Up')
            ->assertDontSee('User Manager');
    }

    public function test_all_supported_languages_save_and_reject_unknown_codes(): void
    {
        $this->post('/language', ['locale' => 'km'])->assertRedirect();
        $this->assertSame('km', session('gomovie.locale'));
        $this->get('/')->assertOk()->assertSee('lang="km"', false)
            ->assertSee('ចូលគណនី')
            ->assertSee('🇰🇭');
        $this->post('/language', ['locale' => 'xx'])->assertSessionHasErrors('locale');
    }

    public function test_verified_staff_gets_staff_destination_and_viewer_gets_account(): void
    {
        $admin = User::factory()->create([
            'email' => 'staff@example.test',
            'password' => Hash::make('ExampleStrong123'),
        ]);
        $admin->forceFill(['role' => 'admin', 'is_active' => true, 'email_verified_at' => now()])->save();
        $this->post('/login', ['email' => $admin->email, 'password' => 'ExampleStrong123'])
            ->assertRedirect(route('admin.dashboard'));
        $this->post('/logout')->assertRedirect(route('home'));

        $viewer = User::factory()->create([
            'email' => 'viewer-route@example.test',
            'password' => Hash::make('ExampleStrong123'),
        ]);
        $this->post('/login', ['email' => $viewer->email, 'password' => 'ExampleStrong123'])
            ->assertRedirect(route('account.overview'));
        $this->get('/')->assertOk()
            ->assertSee('My List')
            ->assertDontSee('User Manager');
    }

    public function test_guest_watch_dialog_returns_to_same_movie_without_open_redirect(): void
    {
        Movie::create(['title' => 'Viewer Test Movie', 'slug' => 'viewer-test-movie', 'status' => 'published']);
        $url = '/watch/movie/viewer-test-movie';
        $this->get($url)->assertOk()
            ->assertSee('Sign In to Watch')
            ->assertSee('gm-watch-login-dialog')
            ->assertSee('name="return" value="'.$url.'"', false);
        $this->get('/hls/999/master.m3u8')->assertRedirect(route('login'));

        $viewer = User::factory()->create([
            'email' => 'return-viewer@example.test',
            'password' => Hash::make('ExampleStrong123'),
        ]);
        $this->post('/login', [
            'email' => $viewer->email, 'password' => 'ExampleStrong123',
            'return' => $url,
        ])->assertRedirect($url);
        $this->post('/logout');
        $this->post('/login', [
            'email' => $viewer->email, 'password' => 'ExampleStrong123',
            'return' => 'https://example.com/steal',
        ])->assertRedirect(route('account.overview'));
    }

    public function test_avatar_upload_is_private_and_used_in_account_header(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->actingAs($user)->post('/account/settings/avatar', [
            'avatar' => UploadedFile::fake()->image('profile.jpg', 140, 140),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $path = $user->fresh()->avatar_path;
        self::assertNotNull($path);
        Storage::disk('local')->assertExists($path);
        $this->get('/account/avatar')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get('/account')->assertOk()->assertSee('/account/avatar?v=', false);
        $this->post('/logout');
        $this->get('/account/avatar')->assertRedirect(route('login'));
    }
}
