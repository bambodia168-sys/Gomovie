<?php

namespace Tests\Feature;

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Season;
use App\Models\Series;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CinematicFrontendTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_uses_real_published_catalog_and_premium_sections(): void
    {
        Movie::create([
            'title'=>'Cinematic Premier','slug'=>'cinematic-premier','status'=>'published',
            'is_premium'=>false,'poster_path'=>'/safe/poster.jpg',
        ]);
        Movie::create(['title'=>'Never Published Draft','slug'=>'never-published-draft','status'=>'draft']);
        Series::create([
            'title'=>'Hidden Future','slug'=>'hidden-future','status'=>'published',
            'published_at'=>now()->addDays(4),
        ]);

        $this->get('/')->assertOk()
            ->assertSee('Top Picks for You')
            ->assertSee('New Releases')
            ->assertSee('Recently Added')
            ->assertSee('Cinematic Premier')
            ->assertSee('https://image.tmdb.org/t/p/w500/safe/poster.jpg')
            ->assertDontSee('Never Published Draft')
            ->assertDontSee('Hidden Future')
            ->assertDontSee('Trending Now');
    }

    public function test_featured_hero_background_tracks_slide_and_keeps_drafts_private(): void
    {
        Movie::create([
            'title' => 'Backdrop Film', 'slug' => 'backdrop-film', 'status' => 'published',
            'poster_path' => '/poster-film.jpg', 'backdrop_path' => '/landscape-film.jpg',
        ]);
        Series::create([
            'title' => 'Backdrop Series', 'slug' => 'backdrop-series', 'status' => 'published',
            'poster_path' => '/series-portrait.jpg',
        ]);
        Movie::create([
            'title' => 'Hidden Draft Backdrop', 'slug' => 'hidden-draft-backdrop',
            'status' => 'draft', 'backdrop_path' => '/private-draft.jpg',
        ]);

        $this->get('/')->assertOk()
            ->assertSee('gm-hero-scene')
            ->assertSee('width:100dvw')
            ->assertSee('filter:brightness(1.08) saturate(1.28) blur(1px)')
            ->assertDontSee('brightness(.68)')
            ->assertSee('gm-hero-scene-tint')
            ->assertSee('x-show="active === 0"', false)
            ->assertSee('x-show="active === 1"', false)
            ->assertSee('https://image.tmdb.org/t/p/w780/landscape-film.jpg')
            ->assertSee('https://image.tmdb.org/t/p/w780/series-portrait.jpg')
            ->assertDontSee('private-draft.jpg')
            ->assertSee('gm-hero-tile')
            ->assertSee('gm-hero .gm-hero-tile.is-far-left')
            ->assertSee('gm-hero .gm-hero-tile.is-far-right')
            ->assertSee('filter: brightness(.96) saturate(1.08)')
            ->assertSee('gm-feature-grid');
    }

    public function test_browse_filters_are_real_and_do_not_publish_drafts(): void
    {
        Movie::create(['title'=>'Free Feature','slug'=>'free-feature','status'=>'published','is_premium'=>false]);
        Movie::create(['title'=>'Paid Feature','slug'=>'paid-feature','status'=>'published','is_premium'=>true]);
        Movie::create(['title'=>'Private Draft','slug'=>'private-draft','status'=>'draft']);

        $this->get('/browse?type=movie&access=free')->assertOk()
            ->assertSee('Free Feature')->assertDontSee('Paid Feature')
            ->assertDontSee('Private Draft');
        $this->get('/browse?type=movie&access=premium')->assertOk()
            ->assertSee('Paid Feature')->assertDontSee('Free Feature');
        $this->get('/about')->assertOk()->assertSee('About the platform');
    }

    public function test_watch_modes_retain_selected_episode_and_keep_other_series_out(): void
    {
        $series = Series::create(['title'=>'Portrait Story','slug'=>'portrait-story','status'=>'published']);
        $season = Season::create(['series_id'=>$series->id,'season_number'=>1,'title'=>'Season One']);
        $first = Episode::create(['season_id'=>$season->id,'episode_number'=>1,'title'=>'A First Episode','status'=>'published']);
        $second = Episode::create(['season_id'=>$season->id,'episode_number'=>2,'title'=>'The Next Chapter','status'=>'published']);
        Episode::create(['season_id'=>$season->id,'episode_number'=>3,'title'=>'Private Episode','status'=>'draft']);
        $other = Series::create(['title'=>'Another Show','slug'=>'another-show','status'=>'published']);
        $otherSeason = Season::create(['series_id'=>$other->id,'season_number'=>1,'title'=>'S1']);
        $foreign = Episode::create(['season_id'=>$otherSeason->id,'episode_number'=>1,'title'=>'Foreign Episode','status'=>'published']);

        $this->get('/watch/series/portrait-story')->assertOk()
            ->assertSee('data-gm-view-preference="auto"', false)
            ->assertDontSee('gm-mode-switch')
            ->assertSee('A First Episode')
            ->assertSee('The Next Chapter')
            ->assertDontSee('Private Episode');

        $this->get('/watch/series/portrait-story?view=portrait&episode='.$second->id)
            ->assertOk()->assertSee('data-gm-view-preference="portrait"', false)
            ->assertSee('is-portrait')->assertSee('The Next Chapter')
            ->assertSee('Related Movies &amp; Series', false)
            ->assertSee('Another Show')
            ->assertDontSee('Private Episode')
            ->assertSee('episode='.$second->id);
        $this->get('/watch/series/portrait-story?episode='.$foreign->id)->assertNotFound();
        $this->get('/watch/movie/portrait-story')->assertNotFound();
        self::assertNotSame($first->id,$second->id);
    }

    public function test_related_titles_exclude_drafts_and_watchlist_form_keeps_existing_behavior(): void
    {
        $movie = Movie::create([
            'title'=>'Watch Page Feature','slug'=>'watch-page-feature','status'=>'published','is_premium'=>false,
        ]);
        Movie::create(['title'=>'Related Good Film','slug'=>'related-good-film','status'=>'published']);
        Movie::create(['title'=>'Related Private Draft','slug'=>'related-private-draft','status'=>'draft']);

        $this->get('/watch/movie/watch-page-feature')->assertOk()
            ->assertSee('Related Good Film')->assertDontSee('Related Private Draft')
            ->assertSee('Sign in')->assertDontSee('data-gomovie-player', false);

        $viewer = User::factory()->create();
        UserProfile::create(['user_id'=>$viewer->id,'name'=>'Main','slug'=>'main']);
        $this->actingAs($viewer)->get('/watch/movie/watch-page-feature')
            ->assertOk()->assertSee('Add to My List');
    }

    public function test_premium_authentication_styles_keep_real_native_form_actions(): void
    {
        $this->get('/login')->assertOk()->assertSee('Welcome back')
            ->assertSee('Forgot password?')->assertSee('name="email"', false)
            ->assertSee('action="'.route('login').'"', false)
            ->assertDontSee('Continue with Google');
        $this->get('/register')->assertOk()->assertSee('Create your account')
            ->assertSee('name="password_confirmation"', false)
            ->assertDontSee('Continue with Google');
        $this->get('/forgot-password')->assertOk()->assertSee('Forgot password');
        $this->get('/email/verify')->assertRedirect(route('login'));
    }
}
