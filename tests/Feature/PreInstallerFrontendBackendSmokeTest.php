<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\Season;
use App\Models\Series;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Renders real Laravel HTTP responses in isolated CI databases. This is NOT
 * JavaScript E2E, real-device, SMTP, TLS, aaPanel/cPanel or installer acceptance.
 */
final class PreInstallerFrontendBackendSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_authentication_and_read_only_api_routes_render(): void
    {
        $movie = Movie::query()->create([
            'title'=>'Smoke Test Movie', 'slug'=>'smoke-test-movie', 'status'=>'published',
            'is_premium'=>false,
        ]);
        $series = Series::query()->create([
            'title'=>'Smoke Test Series', 'slug'=>'smoke-test-series', 'status'=>'published',
            'is_premium'=>false,
        ]);
        Season::query()->create(['series_id'=>$series->id, 'season_number'=>1, 'title'=>'First Season']);

        foreach ([
            '/' => 'Smoke Test Movie',
            '/browse' => 'Smoke Test Movie',
            '/browse?type=movie&q=Smoke' => 'Smoke Test Movie',
            '/browse?type=series&q=Smoke' => 'Smoke Test Series',
            '/watch/movie/smoke-test-movie' => 'Smoke Test Movie',
            '/watch/series/smoke-test-series' => 'First Season',
            '/login' => 'Sign in',
            '/register' => 'Create your account',
            '/forgot-password' => 'Forgot password',
            '/reset-password/nonproduction-token?email=viewer%40example.test' => 'Choose a new password',
        ] as $path => $expected) {
            $this->get($path)->assertOk()->assertSee($expected);
        }

        foreach ([
            '/api/v1/movies', '/api/v1/movies/smoke-test-movie',
            '/api/v1/series', '/api/v1/series/smoke-test-series',
            '/api/v1/series/smoke-test-series/seasons/1',
        ] as $path) {
            $this->getJson($path)->assertOk()->assertHeader('Content-Type', 'application/json');
        }

        $this->get('/watch/movie/nonexistent')->assertNotFound();
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/account')->assertRedirect('/login');
    }

    public function test_verified_viewer_can_render_all_account_pages_but_not_studio(): void
    {
        $viewer = User::factory()->create();
        UserProfile::query()->create(['user_id'=>$viewer->id,'name'=>'Main','slug'=>'main']);
        $this->actingAs($viewer);
        foreach ([
            '/account' => 'Welcome',
            '/account/settings' => 'Account',
            '/account/library/watchlist' => 'My List',
            '/account/library/history' => 'Watch history',
        ] as $url => $expected) {
            $this->get($url)->assertOk()->assertSee($expected);
        }
        $this->getJson('/api/v1/user/profiles')->assertOk();
        $this->getJson('/api/v1/user/subscription')->assertOk();
        $this->postJson('/api/v1/user/subscription/subscribe')->assertStatus(503);
        $this->get('/dashboard')->assertForbidden();
        $this->get('/dashboard/operations')->assertForbidden();
    }

    public function test_admin_studio_pages_render_and_do_not_expose_installer_as_if_it_exists(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role'=>'admin'])->save();
        $movie = Movie::query()->create([
            'title'=>'Management Film', 'slug'=>'management-film', 'status'=>'draft',
        ]);
        $series = Series::query()->create([
            'title'=>'Management Show', 'slug'=>'management-show', 'status'=>'draft',
        ]);
        $this->actingAs($admin);
        foreach ([
            '/dashboard' => 'Content overview',
            '/dashboard/catalog/movies' => 'Management Film',
            '/dashboard/catalog/series' => 'Management Show',
            '/dashboard/catalog/movies/create' => 'Movie',
            '/dashboard/catalog/series/create' => 'Series',
            '/dashboard/catalog/movies/'.$movie->id.'/edit' => 'Management Film',
            '/dashboard/catalog/series/'.$series->id.'/edit' => 'Management Show',
            '/dashboard/series/'.$series->id.'/seasons' => 'Management Show',
            '/dashboard/media' => 'Media Library',
            '/dashboard/operations' => 'Application health',
            '/dashboard/users' => 'User',
        ] as $url => $expected) {
            $this->get($url)->assertOk()->assertSee($expected);
        }
        // Source audit for the missing web installer remains an explicit
        // release blocker, not a fabricated passing installation.
        self::assertFalse(Route::has('installer.index'));
    }

    public function test_media_and_billing_remain_disabled_after_catalog_smoke(): void
    {
        self::assertFalse(config('gomovie.edge_enabled'));
        self::assertFalse(config('gomovie.billing_enabled'));
        self::assertFalse(config('gomovie.transcoding_enabled'));
        self::assertFalse(config('gomovie.media_staging_enabled'));
    }
}
