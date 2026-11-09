<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AppearanceSlotsTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role): User
    {
        $user=User::factory()->create();
        $user->forceFill(['role'=>$role])->save();
        return $user;
    }

    public function test_default_content_and_frontend_layout_remain_unchanged(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Top Picks for You')
            ->assertSee('Recently Added')
            ->assertSee('gm-site-header')
            ->assertSee('gm-site-footer');
        $this->get('/about')->assertOk()
            ->assertSee('Personal library')
            ->assertSee('Responsible delivery')
            ->assertSee('gm-about-grid');
        $this->get('/browse')->assertOk()
            ->assertSee('Movies')
            ->assertSee('TV Series')
            ->assertSee('gm-filter-bar');
        $this->assertDatabaseCount('studio_content_slots',0);
    }

    public function test_editor_is_super_admin_only_with_safe_live_content_and_reset(): void
    {
        $url='/dashboard/appearance/sections/home-top-picks';
        $this->actingAs($this->staff('admin'))->put($url,['content'=>'Unauthorized'])
            ->assertForbidden();
        $this->actingAs($this->staff('super_admin'));
        $this->put($url,['content'=>'Curated cinema'])
            ->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('studio_content_slots',['key'=>'home-top-picks','content'=>'Curated cinema']);
        $this->get('/')->assertOk()->assertSee('Curated cinema')->assertDontSee('Top Picks for You');
        $this->put($url,['content'=>'<script>alert(1)</script>'])->assertRedirect();
        $this->get('/')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;',false)
            ->assertDontSee('<script>alert(1)</script>',false);
        $this->put('/dashboard/appearance/sections/unknown-element',['content'=>'Unlisted'])
            ->assertNotFound();
        $this->delete($url)->assertRedirect()->assertSessionHas('status');
        $this->get('/')->assertOk()->assertSee('Top Picks for You');
        $this->assertDatabaseCount('studio_content_slots',0);
        $this->assertDatabaseHas('admin_audit_events',['action'=>'appearance.slot.updated']);
        $this->assertDatabaseHas('admin_audit_events',['action'=>'appearance.slot.reset']);
    }

    public function test_about_and_footer_edits_keep_sections_and_destination_urls(): void
    {
        $this->actingAs($this->staff('super_admin'));
        $this->put('/dashboard/appearance/sections/about-library-heading',['content'=>'Your private collection'])
            ->assertRedirect();
        $this->put('/dashboard/appearance/sections/footer-browse-label',['content'=>'Visit the library'])
            ->assertRedirect();

        $this->get('/about')->assertOk()->assertSee('Your private collection')
            ->assertSee('gm-about-grid')->assertSee('Visit the library')
            ->assertSee('href="'.route('browse').'"',false);
        $this->get('/')->assertOk()->assertSee('Visit the library');
    }

    public function test_pagination_shows_ten_real_frontend_elements_per_page(): void
    {
        $this->actingAs($this->staff('super_admin'));
        $this->get('/dashboard/appearance')->assertOk()
            ->assertSee('Frontend text elements')
            ->assertSee('home-top-picks')
            ->assertDontSee('header-home-label')
            ->assertSee('Go to');
        $this->get('/dashboard/appearance?sections_page=2')->assertOk()
            ->assertSee('header-home-label')
            ->assertDontSee('home-top-picks');
    }
}
