<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_can_update_name_but_role_cannot_be_self_promoted(): void
    {
        $viewer=User::factory()->create();
        $this->actingAs($viewer);
        $this->patch('/account/settings/identity',[
            'name'=>'Updated Viewer','email'=>$viewer->email,'role'=>'super_admin',
        ])->assertRedirect();
        self::assertSame('Updated Viewer',$viewer->fresh()->name);
        self::assertSame('viewer',$viewer->fresh()->role);
    }

    public function test_password_change_requires_current_password_and_revokes_api_tokens(): void
    {
        $viewer=User::factory()->create(['password'=>Hash::make('OldPassword123')]);
        $token=$viewer->createToken('test-client');
        $this->actingAs($viewer);
        $this->patch('/account/settings/password',[
            'current_password'=>'incorrect','password'=>'NewPassword123',
            'password_confirmation'=>'NewPassword123',
        ])->assertSessionHasErrors('current_password');
        $this->patch('/account/settings/password',[
            'current_password'=>'OldPassword123','password'=>'NewPassword123',
            'password_confirmation'=>'NewPassword123',
        ])->assertRedirect();
        self::assertTrue(Hash::check('NewPassword123',$viewer->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens',0);
    }

    public function test_account_cannot_delete_other_users_profile_or_last_profile(): void
    {
        $viewer=User::factory()->create();
        $other=User::factory()->create();
        $own=UserProfile::create(['user_id'=>$viewer->id,'name'=>'Main','slug'=>'main']);
        $foreign=UserProfile::create(['user_id'=>$other->id,'name'=>'Other','slug'=>'other']);
        $this->actingAs($viewer);
        $this->delete('/account/profiles/'.$foreign->id)->assertNotFound();
        $this->delete('/account/profiles/'.$own->id)->assertStatus(422);
        $this->assertDatabaseHas('user_profiles',['id'=>$own->id]);
    }

    public function test_account_can_manage_additional_viewing_profile(): void
    {
        $viewer=User::factory()->create();
        UserProfile::create(['user_id'=>$viewer->id,'name'=>'Main','slug'=>'main']);
        $this->actingAs($viewer);
        $this->get('/account/settings')->assertOk()->assertSee('Main');
        $this->post('/account/profiles',['name'=>'Cinema','is_kids'=>0])->assertRedirect();
        $profile=UserProfile::query()->where('user_id',$viewer->id)->where('slug','cinema')->firstOrFail();
        $this->delete('/account/profiles/'.$profile->id)->assertRedirect();
        $this->assertDatabaseMissing('user_profiles',['id'=>$profile->id]);
    }
}
