<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_cannot_sync_watch_history_to_another_users_profile(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $profile = UserProfile::create(['user_id' => $other->id, 'name' => 'Other', 'slug' => 'other']);
        Sanctum::actingAs($viewer);
        $this->postJson('/api/v1/user/watch-history/sync', [
            'profile_id' => $profile->id, 'watchable_type' => 'movie',
            'watchable_id' => 1, 'progress_seconds' => 10, 'duration_seconds' => 120,
        ])->assertNotFound();
    }

    public function test_profiles_endpoint_only_returns_own_profiles(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        UserProfile::create(['user_id' => $viewer->id, 'name' => 'Home', 'slug' => 'home']);
        UserProfile::create(['user_id' => $other->id, 'name' => 'Private', 'slug' => 'private']);
        Sanctum::actingAs($viewer);
        $this->getJson('/api/v1/user/profiles')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Home');
    }
}
