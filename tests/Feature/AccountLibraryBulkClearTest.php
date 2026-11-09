<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AccountLibraryBulkClearTest extends TestCase
{
    use RefreshDatabase;

    public function test_clearing_watchlist_only_removes_selected_owner_profile_records(): void
    {
        $owner=User::factory()->create();
        $other=User::factory()->create();
        $selected=UserProfile::create(['user_id'=>$owner->id,'name'=>'Selected','slug'=>'selected']);
        $otherProfile=UserProfile::create(['user_id'=>$owner->id,'name'=>'Keep','slug'=>'keep']);
        $foreign=UserProfile::create(['user_id'=>$other->id,'name'=>'Foreign','slug'=>'foreign']);
        $movie=Movie::create(['title'=>'Private list QA','slug'=>'private-list-qa','status'=>'published']);
        foreach ([$selected,$otherProfile,$foreign] as $profile) {
            DB::table('watchlists')->insert([
                'user_profile_id'=>$profile->id,'watchable_type'=>'movie','watchable_id'=>$movie->id,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        }

        $this->actingAs($owner)->get('/account/library/watchlist?profile_id='.$selected->id)
            ->assertOk()->assertSee('Clear My List')->assertSee('gm-studio-modal');

        $this->delete('/account/library/watchlist/clear',['profile_id'=>$foreign->id])
            ->assertNotFound();
        $this->delete('/account/library/watchlist/clear',['profile_id'=>$selected->id])
            ->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseMissing('watchlists',['user_profile_id'=>$selected->id]);
        $this->assertDatabaseHas('watchlists',['user_profile_id'=>$otherProfile->id]);
        $this->assertDatabaseHas('watchlists',['user_profile_id'=>$foreign->id]);
        $this->assertDatabaseHas('movies',['id'=>$movie->id]);
    }

    public function test_history_clear_scopes_to_own_profile_and_does_not_delete_other_records(): void
    {
        $owner=User::factory()->create();
        $foreign=User::factory()->create();
        $mine=UserProfile::create(['user_id'=>$owner->id,'name'=>'Mine','slug'=>'mine']);
        $theirs=UserProfile::create(['user_id'=>$foreign->id,'name'=>'Theirs','slug'=>'theirs']);
        $movie=Movie::create(['title'=>'Watch History QA','slug'=>'watch-history-qa','status'=>'published']);

        foreach ([[$owner,$mine],[$foreign,$theirs]] as [$user,$profile]) {
            DB::table('watch_history')->insert([
                'user_id'=>$user->id,'user_profile_id'=>$profile->id,'watchable_type'=>'movie',
                'watchable_id'=>$movie->id,'progress_seconds'=>12,'duration_seconds'=>60,
                'last_watched_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
            ]);
        }
        $this->actingAs($owner)->get('/account/library/history')->assertOk()
            ->assertSee('Clear watch history');
        $this->delete('/account/library/history/clear',['profile_id'=>$mine->id])
            ->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseMissing('watch_history',['user_profile_id'=>$mine->id]);
        $this->assertDatabaseHas('watch_history',['user_profile_id'=>$theirs->id]);
        $this->delete('/account/library/history/clear',['profile_id'=>$theirs->id])
            ->assertNotFound();
        $this->assertDatabaseHas('movies',['id'=>$movie->id]);
    }

    public function test_clear_routes_reject_unknown_sections_and_unauthenticated_users(): void
    {
        $owner=User::factory()->create();
        $profile=UserProfile::create(['user_id'=>$owner->id,'name'=>'Main','slug'=>'main']);
        $this->delete('/account/library/watchlist/clear',['profile_id'=>$profile->id])
            ->assertRedirect('/login');
        $this->actingAs($owner)->delete('/account/library/unknown/clear',['profile_id'=>$profile->id])
            ->assertNotFound();
    }
}
