<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class StudioAccountWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_can_be_renamed_only_by_owner_and_delete_is_guarded(): void
    {
        $owner=User::factory()->create();
        $foreign=User::factory()->create();
        $profile=UserProfile::create(['user_id'=>$owner->id,'name'=>'Cinema','slug'=>'cinema']);
        $this->actingAs($foreign)->patch('/account/profiles/'.$profile->id,[
            'name'=>'Unauthorized','is_kids'=>1,
        ])->assertNotFound();
        $this->actingAs($owner)->patch('/account/profiles/'.$profile->id,[
            'name'=>'Family cinema','is_kids'=>1,
        ])->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('user_profiles',[
            'id'=>$profile->id,'slug'=>'family-cinema','is_kids'=>1,
        ]);
        $this->get('/account/settings')->assertOk()
            ->assertSee('gm-account-nav')->assertSee('data-studio-open')
            ->assertSee('gm-studio-modal')->assertSee('Family cinema');
    }

    public function test_private_watch_history_rows_are_owner_only_and_removable(): void
    {
        $owner=User::factory()->create();
        $other=User::factory()->create();
        $profile=UserProfile::create(['user_id'=>$owner->id,'name'=>'Main','slug'=>'main']);
        $movie=Movie::create(['title'=>'Viewed Documentary','slug'=>'viewed-documentary','status'=>'published']);
        $id=DB::table('watch_history')->insertGetId([
            'user_id'=>$owner->id,'user_profile_id'=>$profile->id,
            'watchable_type'=>'movie','watchable_id'=>$movie->id,
            'progress_seconds'=>20,'duration_seconds'=>40,'last_watched_at'=>now(),
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $url='/account/history/'.$id;
        $this->actingAs($other)->delete($url,['profile_id'=>$profile->id])->assertNotFound();
        $this->actingAs($owner)->get('/account/library/history')->assertOk()
            ->assertSee('Viewed Documentary')->assertSee('50% watched');
        $this->delete($url,['profile_id'=>$profile->id])
            ->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseMissing('watch_history',['id'=>$id]);
        $this->assertDatabaseHas('movies',['id'=>$movie->id]);
    }

    public function test_ten_per_page_is_consistent_on_members_and_media(): void
    {
        $admin=User::factory()->create();
        $admin->forceFill(['role'=>'super_admin'])->save();
        for($i=1;$i<=12;$i++){
            User::factory()->create(['name'=>'Table Viewer '.sprintf('%02d',$i)]);
        }
        $this->actingAs($admin)->get('/dashboard/users')->assertOk()
            ->assertSee('10 items per page')->assertSee('Go to');
        $this->get('/dashboard/users?page=2')->assertOk()
            ->assertSee('Table Viewer 01');
    }
}
