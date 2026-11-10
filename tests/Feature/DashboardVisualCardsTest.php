<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\Series;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DashboardVisualCardsTest extends TestCase
{
    use RefreshDatabase;

    private function studioAdmin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'super_admin', 'is_active' => true])->save();

        return $user;
    }

    public function test_dashboard_displays_real_visual_metrics_and_publication_progress(): void
    {
        Movie::create([
            'title' => 'Published Visual Test', 'slug' => 'published-visual-test',
            'status' => 'published', 'is_premium' => false,
        ]);
        Movie::create([
            'title' => 'Draft Visual Test', 'slug' => 'draft-visual-test',
            'status' => 'draft', 'is_premium' => true,
        ]);
        Series::create([
            'title' => 'Published Series Test', 'slug' => 'published-series-visual-test',
            'status' => 'published',
        ]);
        Series::create([
            'title' => 'Draft Series Test', 'slug' => 'draft-series-visual-test',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->studioAdmin())->get('/dashboard');
        $response->assertOk()
            ->assertSee('gm-dashboard-overview')
            ->assertSee('gm-dashboard-metric-grid--four')
            ->assertSee('gm-dashboard-metric-grid--three')
            ->assertSee('gm-visual-metric-art')
            ->assertSee('gm-metric-movies', false)
            ->assertSee('gm-metric-series', false)
            ->assertSee('gm-metric-members', false)
            ->assertSee('gm-metric-published-movies', false)
            ->assertSee('gm-metric-published-series', false)
            ->assertSee('gm-metric-ingests-pending', false)
            ->assertSee('50% of all movies published')
            ->assertSee('50% of all series published')
            ->assertSee('aria-valuenow="50"', false)
            ->assertSee('Published Visual Test')
            ->assertSee('Draft Visual Test')
            ->assertSee('data-studio-menu="recent-', false)
            ->assertSee('gm-dashboard-table')
            ->assertSee('name="page"', false)
            ->assertSee('Manage all');
    }

    public function test_metric_percentages_are_safe_when_library_is_empty(): void
    {
        $this->actingAs($this->studioAdmin())->get('/dashboard')->assertOk()
            ->assertSee('0% of all movies published')
            ->assertSee('0% of all series published')
            ->assertSee('No movies have been created.');
    }

    public function test_recent_movies_table_remains_ten_items_per_page_with_pagination(): void
    {
        for ($n = 1; $n <= 12; $n++) {
            Movie::create([
                'title' => 'Dashboard Table Film '.sprintf('%02d', $n),
                'slug' => 'dashboard-table-film-'.sprintf('%02d', $n),
                'status' => 'published',
            ]);
        }
        $this->actingAs($this->studioAdmin());
        $this->get('/dashboard')->assertOk()
            ->assertSee('10 items per page')
            ->assertSee('12 records')
            ->assertSee('Dashboard Table Film 12')
            ->assertDontSee('Dashboard Table Film 01')
            ->assertSee('Go to');
        $this->get('/dashboard?page=2')->assertOk()
            ->assertSee('Dashboard Table Film 01')
            ->assertSee('Dashboard Table Film 02')
            ->assertDontSee('Dashboard Table Film 12');
    }

    public function test_shared_header_tools_are_transparent_with_avatar_unchanged(): void
    {
        $response = $this->actingAs($this->studioAdmin())->get('/dashboard');
        $response->assertOk()
            ->assertSee('gm-workspace-home')
            ->assertSee('gm-header-language')
            ->assertSee('gm-theme-tool')
            ->assertSee('gm-profile-tool')
            ->assertSee('background:transparent!important', false)
            ->assertSee('gm-visual-metric--rose')
            ->assertSee('gm-dashboard-table-panel');

        $this->get('/account')->assertOk()
            ->assertSee('gm-workspace-home')
            ->assertSee('gm-header-language')
            ->assertSee('background:transparent!important', false)
            ->assertDontSee('gm-dashboard-metric-grid--four');
    }
}
