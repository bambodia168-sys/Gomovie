<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WorkspaceHeaderConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_studio_has_compact_shared_header_catalog_search_home_and_collapse_button(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'super_admin', 'is_active' => true])->save();

        $this->actingAs($admin)->get('/dashboard')->assertOk()
            ->assertSee('data-gm-workspace="studio"', false)
            ->assertSee('class="gm-studio-topbar gm-workspace-topbar"', false)
            ->assertSee('data-gm-sidebar-toggle', false)
            ->assertSee('aria-controls="gm-workspace-sidebar"', false)
            ->assertSee('id="gm-workspace-search"', false)
            ->assertSee('action="'.route('browse').'"', false)
            ->assertSee('aria-label="Home"', false)
            ->assertSee('gm-workspace-backdrop')
            ->assertSee('id="gomovie-workspace-sidebar-controller"', false)
            ->assertSee('gomovie.workspace.sidebar.collapsed')
            ->assertSee('gm-workspace-sidebar-nav>a.is-current .gm-workspace-link-label')
            ->assertSee('User Manager');
    }

    public function test_viewer_account_uses_identical_header_and_role_scoped_account_menu(): void
    {
        $viewer = User::factory()->create();
        $response = $this->actingAs($viewer)->get('/account');

        $response->assertOk()
            ->assertSee('data-gm-workspace="account"', false)
            ->assertSee('class="gm-studio-topbar gm-workspace-topbar"', false)
            ->assertSee('gm-workspace-header-search')
            ->assertSee('data-gm-sidebar-toggle', false)
            ->assertSee('data-gm-sidebar-backdrop', false)
            ->assertSee('gm-account-nav')
            ->assertSee('gm-workspace-link-label')
            ->assertSee('My List')
            ->assertSee('Watch history')
            ->assertDontSee('User Manager')
            ->assertDontSee('Player Settings');
    }

    public function test_editor_sidebar_excludes_admin_only_links(): void
    {
        $editor = User::factory()->create();
        $editor->forceFill(['role' => 'editor', 'is_active' => true])->save();

        $this->actingAs($editor)->get('/dashboard')->assertOk()
            ->assertSee('Media Library')
            ->assertSee('TV Series')
            ->assertDontSee('User Manager')
            ->assertDontSee('Player Settings');
    }

    public function test_public_frontend_retains_existing_navigation(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('gm-site-header')
            ->assertSee('gm-main-nav')
            ->assertDontSee('data-gm-workspace="studio"', false);
    }
}
