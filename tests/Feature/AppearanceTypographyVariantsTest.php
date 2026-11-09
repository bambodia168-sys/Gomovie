<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AppearanceTypographyVariantsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $user=User::factory()->create();
        $user->forceFill(['role'=>$role])->save();
        return $user;
    }

    public function test_default_typography_does_not_change_public_layout(): void
    {
        $this->get('/about')->assertOk()->assertSee('gm-about-grid')
            ->assertDontSee('text-transform:uppercase');
        $this->assertDatabaseCount('studio_typography',0);
    }

    public function test_saved_variants_are_real_and_are_applied_only_when_configured(): void
    {
        $this->actingAs($this->user('super_admin'));
        $this->get('/dashboard/appearance')->assertOk()
            ->assertSee('name="heading_variant"',false)
            ->assertSee('name="body_variant"',false)
            ->assertSee('name="button_variant"',false)
            ->assertSee('data-typography-preview');
        $this->patch('/dashboard/appearance/typography',[
            'family'=>'Georgia','base_size'=>17,'heading_scale'=>105,'weight'=>'700',
            'heading_variant'=>'uppercase','body_variant'=>'relaxed','button_variant'=>'bold',
        ])->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('studio_typography',[
            'family'=>'Georgia','heading_variant'=>'uppercase',
            'body_variant'=>'relaxed','button_variant'=>'bold',
        ]);
        $this->get('/about')->assertOk()
            ->assertSee('gm-about-grid')
            ->assertSee('text-transform:uppercase')
            ->assertSee('line-height:1.85')
            ->assertSee('font-weight:800');
    }

    public function test_invalid_font_variant_or_unauthorized_editor_does_not_change_styles(): void
    {
        $payload=[
            'family'=>'Arial','base_size'=>16,'heading_scale'=>100,'weight'=>'600',
            'heading_variant'=>'malicious-style','body_variant'=>'default','button_variant'=>'bold',
        ];
        $this->actingAs($this->user('super_admin'))->patch('/dashboard/appearance/typography',$payload)
            ->assertSessionHasErrors('heading_variant');
        $this->assertDatabaseCount('studio_typography',0);
        $payload['heading_variant']='tight';
        $this->actingAs($this->user('admin'))->patch('/dashboard/appearance/typography',$payload)
            ->assertForbidden();
        $this->assertDatabaseCount('studio_typography',0);
    }
}
