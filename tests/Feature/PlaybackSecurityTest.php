<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlaybackSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_stream_token_requires_authentication(): void
    {
        $this->postJson('/api/v1/stream/movie/1/token', ['profile_id' => 1])->assertUnauthorized();
    }

    public function test_missing_distribution_rights_fail_closed(): void
    {
        $user = User::factory()->create();
        $profile = UserProfile::create(['user_id' => $user->id, 'name' => 'Main', 'slug' => 'main']);
        $movie = Movie::create(['title' => 'Licensed Later', 'slug' => 'licensed-later', 'status' => 'published', 'is_premium' => false]);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/stream/movie/{$movie->id}/token", [
            'profile_id' => $profile->id,
        ])->assertForbidden();
    }

    public function test_other_account_profile_cannot_receive_stream_grant(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $profile = UserProfile::create(['user_id' => $other->id, 'name' => 'Other', 'slug' => 'other']);
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/stream/movie/1/token', [
            'profile_id' => $profile->id,
        ])->assertNotFound();
    }

    public function test_subscription_purchase_endpoint_is_disabled(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/user/subscription/subscribe')
            ->assertStatus(503)->assertJsonPath('code', 'BILLING_NOT_CONFIGURED');
    }
}
