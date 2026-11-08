<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ViewerLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_can_save_and_remove_published_movie_using_owned_profile(): void
    {
        $user = User::factory()->create();
        $profile = UserProfile::create(['user_id'=>$user->id,'name'=>'Main','slug'=>'main']);
        $movie = Movie::create(['title'=>'Saved Film','slug'=>'saved-film','status'=>'published']);
        $this->actingAs($user);
        $this->post('/account/watchlist/toggle',[
            'profile_id'=>$profile->id,'kind'=>'movie','title_id'=>$movie->id,'action'=>'add',
        ])->assertRedirect();
        $this->assertDatabaseHas('watchlists',[
            'user_profile_id'=>$profile->id,'watchable_type'=>'movie','watchable_id'=>$movie->id,
        ]);
        $this->get('/watch/movie/saved-film')->assertOk()->assertSee('Remove from My List');
        $this->get('/account/library/watchlist?profile_id='.$profile->id)->assertOk()->assertSee('Saved Film');
        $this->post('/account/watchlist/toggle',[
            'profile_id'=>$profile->id,'kind'=>'movie','title_id'=>$movie->id,'action'=>'remove',
        ])->assertRedirect();
        $this->assertDatabaseCount('watchlists',0);
    }

    public function test_viewer_cannot_modify_another_accounts_watchlist_or_save_draft(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreign=UserProfile::create(['user_id'=>$other->id,'name'=>'Other','slug'=>'other']);
        $own=UserProfile::create(['user_id'=>$user->id,'name'=>'Main','slug'=>'main']);
        $published=Movie::create(['title'=>'Public Movie','slug'=>'public-movie','status'=>'published']);
        $draft=Movie::create(['title'=>'Draft Movie','slug'=>'draft-movie','status'=>'draft']);
        $this->actingAs($user);
        $this->post('/account/watchlist/toggle',[
            'profile_id'=>$foreign->id,'kind'=>'movie','title_id'=>$published->id,'action'=>'add',
        ])->assertNotFound();
        $this->post('/account/watchlist/toggle',[
            'profile_id'=>$own->id,'kind'=>'movie','title_id'=>$draft->id,'action'=>'add',
        ])->assertNotFound();
        $this->get('/account/library/watchlist?profile_id='.$foreign->id)->assertNotFound();
        $this->assertDatabaseCount('watchlists',0);
    }

    public function test_private_history_shows_progress_to_owner(): void
    {
        $viewer = User::factory()->create();
        $profile = UserProfile::create(['user_id'=>$viewer->id,'name'=>'Main','slug'=>'main']);
        $movie = Movie::create(['title'=>'Continue Watching','slug'=>'continue-watching','status'=>'published']);
        DB::table('watch_history')->insert([
            'user_id'=>$viewer->id,'user_profile_id'=>$profile->id,
            'watchable_type'=>'movie','watchable_id'=>$movie->id,
            'progress_seconds'=>50,'duration_seconds'=>100,'completed'=>false,
            'last_watched_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->actingAs($viewer)->get('/account/library/history')
            ->assertOk()->assertSee('Continue Watching')->assertSee('50% watched');
    }
}
