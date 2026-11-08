<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_has_baseline_defensive_response_headers(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options','nosniff')
            ->assertHeader('X-Frame-Options','DENY')
            ->assertHeader('Referrer-Policy','strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy',"frame-ancestors 'none'");
    }

    public function test_account_and_admin_pages_are_not_cacheable(): void
    {
        $admin=User::factory()->create();
        $admin->forceFill(['role'=>'admin'])->save();
        $dashboard = $this->actingAs($admin)->get('/dashboard/operations')
            ->assertOk()->assertHeader('Pragma','no-cache');
        $account = $this->get('/account/settings')->assertOk();

        foreach ([$dashboard, $account] as $response) {
            foreach (['private','no-store','max-age=0'] as $directive) {
                self::assertStringContainsString($directive, (string) $response->headers->get('Cache-Control'));
            }
        }
    }

    public function test_internal_media_authorization_failures_are_never_cacheable(): void
    {
        $response = $this->postJson('/api/v1/internal/media/authorize',[])->assertForbidden();
        foreach (['private','no-store','max-age=0'] as $directive) {
            self::assertStringContainsString($directive, (string) $response->headers->get('Cache-Control'));
        }
    }
}
