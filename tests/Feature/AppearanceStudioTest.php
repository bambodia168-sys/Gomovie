<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AppearanceStudioTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user=User::factory()->create();
        $user->forceFill(['role'=>$role])->save();
        return $user;
    }

    public function test_frontend_layout_and_original_copy_are_unchanged_until_owner_customizes(): void
    {
        $this->get('/about')->assertOk()->assertSee('Stories worth discovering.')
            ->assertSee('gm-site-header')->assertSee('gm-site-footer');
        $this->get('/browse')->assertOk()->assertSee('Movies &amp; Series', false)
            ->assertSee('gm-browse-intro');
        $this->get('/login')->assertOk()->assertSee('Welcome back')
            ->assertSee('gm-auth-columns');
        $this->get('/pages/private-preview')->assertNotFound();
        $this->assertDatabaseCount('studio_pages',0);
        $this->assertDatabaseCount('studio_typography',0);
    }

    public function test_roles_guard_appearance_writes_and_built_in_page_updates_are_live(): void
    {
        $viewer=$this->user('viewer');
        $editor=$this->user('editor');
        $admin=$this->user('admin');
        $super=$this->user('super_admin');
        $this->actingAs($viewer)->get('/dashboard/appearance')->assertForbidden();
        $this->actingAs($editor)->get('/dashboard/appearance')->assertForbidden();
        $this->actingAs($admin)->get('/dashboard/appearance')
            ->assertOk()->assertSee('Frontend page templates');
        $this->put('/dashboard/appearance/pages/about',['heading'=>'Not allowed'])
            ->assertForbidden();
        $this->actingAs($super)->get('/dashboard/appearance')->assertOk()
            ->assertSee('Create page')->assertSee('Typography');
        $this->put('/dashboard/appearance/pages/about',[
            'eyebrow'=>'Our story','heading'=>'An original cinema story',
            'description'=>'Our independent licensed catalog.',
            'seo_title'=>'About our catalog',
            'seo_description'=>'Verified rights and safe viewing.',
        ])->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('studio_pages',['slug'=>'about','is_custom'=>false]);
        $this->get('/about')->assertOk()
            ->assertSee('An original cinema story')
            ->assertSee('Our independent licensed catalog.')
            ->assertSee('About our catalog')
            ->assertSee('gm-site-header');
        $this->delete('/dashboard/appearance/pages/about')->assertRedirect();
        $this->assertDatabaseMissing('studio_pages',['slug'=>'about']);
        $this->get('/about')->assertOk()->assertSee('Stories worth discovering.');
    }

    public function test_custom_pages_can_be_created_read_updated_published_and_deleted(): void
    {
        $this->actingAs($this->user('super_admin'));
        $this->post('/dashboard/appearance/pages',[
            'slug'=>'our-story','heading'=>'Our cinema mission',
            'eyebrow'=>'Our identity','body'=>'A practical plain text page.',
            'description'=>'Made for viewers.','seo_title'=>'Our Story',
            'is_published'=>false,
        ])->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('studio_pages',['slug'=>'our-story','is_custom'=>true,'is_published'=>false]);
        $this->get('/pages/our-story')->assertNotFound();
        $this->put('/dashboard/appearance/pages/our-story',[
            'heading'=>'Updated cinema mission','body'=>'This page has real content.',
            'description'=>'Updated introduction.','is_published'=>true,
        ])->assertRedirect();
        $this->get('/pages/our-story')->assertOk()
            ->assertSee('Updated cinema mission')
            ->assertSee('This page has real content.')
            ->assertSee('gm-site-footer');
        $this->post('/dashboard/appearance/pages',[
            'slug'=>'about','heading'=>'Reserved page name',
        ])->assertSessionHasErrors('slug');
        $this->delete('/dashboard/appearance/pages/our-story')
            ->assertRedirect()->assertSessionHas('status');
        $this->get('/pages/our-story')->assertNotFound();
        $this->assertDatabaseHas('admin_audit_events',['action'=>'appearance.page.deleted']);
    }

    public function test_typography_has_real_validation_and_is_protected_from_untrusted_css(): void
    {
        $this->actingAs($this->user('super_admin'));
        $this->patch('/dashboard/appearance/typography',[
            'family'=>'https://evil.example/css','base_size'=>16,
            'heading_scale'=>100,'weight'=>'600',
        ])->assertSessionHasErrors('family');
        $this->patch('/dashboard/appearance/typography',[
            'family'=>'Custom','base_size'=>16,
            'heading_scale'=>100,'weight'=>'600',
        ])->assertSessionHasErrors('family');
        $this->patch('/dashboard/appearance/typography',[
            'family'=>'Georgia','base_size'=>18,
            'heading_scale'=>110,'weight'=>'700',
        ])->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('studio_typography',[
            'family'=>'Georgia','base_size'=>18,'heading_scale'=>110,'weight'=>700,
        ]);
        $this->get('/about')->assertOk()->assertSee('Georgia,serif')
            ->assertSee('gm-custom-heading-scale');
    }

    public function test_navigation_has_reusable_menus_pagination_and_centered_dialogs(): void
    {
        $this->actingAs($this->user('super_admin'));
        Movie::create(['title'=>'Dashboard Test Movie','slug'=>'dashboard-test-movie','status'=>'draft']);
        $this->get('/dashboard')->assertOk()->assertSee('gm-studio-sidebar')
            ->assertSee('gm-studio-pager')->assertSee('Appearance');
        $this->get('/dashboard/users')->assertOk()
            ->assertSee('data-studio-menu')->assertSee('gm-studio-modal');
        $this->get('/dashboard/appearance')->assertOk()
            ->assertSee('data-studio-open')->assertSee('appearance-new')
            ->assertSee('appearance-type');
    }
}
