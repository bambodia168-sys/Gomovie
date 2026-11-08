<?php

namespace Tests\Feature;

use App\Models\Episode;
use App\Models\MediaAsset;
use App\Models\Movie;
use App\Models\Season;
use App\Models\Series;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class WatchPageContinuationTest extends TestCase
{
    use RefreshDatabase;

    private function seriesWithEpisodes(): array
    {
        $series = Series::create([
            'title' => 'Gomovie Episode Showcase',
            'slug' => 'gomovie-episode-showcase',
            'status' => 'published',
            'is_premium' => false,
        ]);
        $season = Season::create([
            'series_id' => $series->id,
            'season_number' => 1,
            'title' => 'First Season',
        ]);
        $first = Episode::create([
            'season_id' => $season->id,
            'episode_number' => 1,
            'title' => 'Pilot Story',
            'status' => 'published',
            'still_path' => '/real-still.jpg',
        ]);
        $second = Episode::create([
            'season_id' => $season->id,
            'episode_number' => 2,
            'title' => 'The Next Chapter',
            'status' => 'published',
        ]);
        $draft = Episode::create([
            'season_id' => $season->id,
            'episode_number' => 3,
            'title' => 'Private Draft Episode',
            'status' => 'draft',
        ]);

        return [$series, $first, $second, $draft];
    }

    private function verifiedViewer(): User
    {
        $viewer = User::factory()->create();
        $viewer->forceFill(['is_active' => true, 'email_verified_at' => now()])->save();
        UserProfile::create([
            'user_id' => $viewer->id, 'name' => 'Main', 'slug' => 'main',
            'is_kids' => false,
        ]);
        return $viewer;
    }

    private function hintFor(Episode|Movie $item): void
    {
        $type = $item instanceof Episode ? 'episode' : 'movie';
        DB::table('content_rights')->insert([
            'licensable_type' => $type, 'licensable_id' => $item->id,
            'status' => 'active', 'territories' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        MediaAsset::create([
            'streamable_type' => $type, 'streamable_id' => $item->id,
            'storage_disk' => 'local',
            'manifest_key' => $type.'/'.$item->id.'/manifest.m3u8',
            'format' => 'hls', 'status' => 'ready',
        ]);
    }

    public function test_watch_page_removes_top_breadcrumb_and_heading_and_keeps_player_and_aspect_modes(): void
    {
        [$series, $first, $second] = $this->seriesWithEpisodes();

        $this->get('/watch/series/'.$series->slug)
            ->assertOk()
            ->assertDontSee('gm-watch-breadcrumb')
            ->assertDontSee('Now exploring')
            ->assertSee('gm-watch-toolbar-compact')
            ->assertSee('Widescreen · 16:9')
            ->assertSee('All Episodes')
            ->assertSee('Episode 1')
            ->assertSee('Episode 2')
            ->assertSee('Pilot Story')
            ->assertSee('The Next Chapter')
            ->assertSee('https://image.tmdb.org/t/p/w342/real-still.jpg')
            ->assertDontSee('Private Draft Episode')
            ->assertSee('Next episode →');

        $this->get('/watch/series/'.$series->slug.'?view=portrait&episode='.$second->id)
            ->assertOk()
            ->assertSee('Portrait · 9:16')
            ->assertSee('Previous episode')
            ->assertSee('episode='.$first->id)
            ->assertDontSee('Private Draft Episode');

        $this->get('/watch/series/'.$series->slug.'?episode=999999')->assertNotFound();
    }

    public function test_autoplay_hint_never_appears_without_active_rights_and_ready_media(): void
    {
        [$series, $first, $second] = $this->seriesWithEpisodes();
        config()->set('gomovie.edge_enabled', true);
        $this->actingAs($this->verifiedViewer())
            ->get('/watch/series/'.$series->slug)
            ->assertOk()
            ->assertSee('data-gomovie-player')
            ->assertSee('data-next-url=""', false)
            ->assertDontSee('data-gomovie-next-prompt')
            ->assertDontSee('Private Draft Episode');
    }

    public function test_licensed_published_next_episode_is_first_autoplay_choice(): void
    {
        [$series, $first, $second] = $this->seriesWithEpisodes();
        $this->hintFor($second);
        $fallback = Movie::create([
            'title' => 'Related Licensed Movie',
            'slug' => 'related-licensed-movie',
            'status' => 'published',
        ]);
        $this->hintFor($fallback);
        config()->set('gomovie.edge_enabled', true);

        $this->actingAs($this->verifiedViewer())
            ->get('/watch/series/'.$series->slug.'?episode='.$first->id.'&view=portrait')
            ->assertOk()
            ->assertSee('data-next-title="Episode 2: The Next Chapter"', false)
            ->assertSee('data-auto-next-toggle')
            ->assertSee('data-gomovie-next-prompt')
            ->assertSee('episode='.$second->id)
            ->assertSee('autoplay=1')
            ->assertDontSee('data-next-title="Related Licensed Movie"', false)
            ->assertDontSee('Private Draft Episode');
    }

    public function test_related_title_is_fallback_after_last_published_episode(): void
    {
        [$series, $first, $second] = $this->seriesWithEpisodes();
        $movie = Movie::create([
            'title' => 'Next Related Movie',
            'slug' => 'next-related-movie',
            'status' => 'published',
        ]);
        $this->hintFor($movie);
        config()->set('gomovie.edge_enabled', true);

        $this->actingAs($this->verifiedViewer())
            ->get('/watch/series/'.$series->slug.'?episode='.$second->id)
            ->assertOk()
            ->assertSee('data-next-title="Next Related Movie"', false)
            ->assertSee('/watch/movie/next-related-movie')
            ->assertSee('autoplay=1');
    }

    public function test_draft_and_scheduled_episodes_are_not_exposed_or_auto_selected(): void
    {
        [$series, $first, $second, $draft] = $this->seriesWithEpisodes();
        $second->forceFill(['published_at' => now()->addDays(2)])->save();
        $this->hintFor($draft);
        $this->hintFor($second);
        config()->set('gomovie.edge_enabled', true);

        $this->actingAs($this->verifiedViewer())
            ->get('/watch/series/'.$series->slug)
            ->assertOk()
            ->assertSee('Episode 1')
            ->assertDontSee('Episode 2')
            ->assertDontSee('Private Draft Episode')
            ->assertSee('data-next-url=""', false);
        $this->get('/watch/series/'.$series->slug.'?episode='.$second->id)->assertNotFound();
    }

    public function test_watch_client_keeps_all_playback_actions_behind_protected_api(): void
    {
        $player = file_get_contents(base_path('resources/js/player.js'));
        self::assertIsString($player);
        self::assertStringContainsString("video.addEventListener('ended', prepareNext)", $player);
        self::assertStringContainsString("parsed.origin === window.location.origin", $player);
        self::assertStringContainsString("'/api/v1/stream/' + type + '/' + titleId + '/token'", $player);
        self::assertStringContainsString("'X-CSRF-TOKEN': csrf", $player);
        self::assertStringContainsString("await stopCurrent();", $player);
        self::assertStringContainsString("sessionStorage.setItem('gomovie:autoplay-next'", $player);
    }
}
