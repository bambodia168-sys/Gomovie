<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationAndAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_defaults_to_viewer_and_requires_email_verification(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Example Viewer',
            'email' => 'VIEWER@example.test',
            'password' => 'ExampleStrong123',
            'password_confirmation' => 'ExampleStrong123',
            'role' => 'super_admin',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $user = User::query()->where('email','viewer@example.test')->firstOrFail();
        self::assertSame('viewer',$user->role);
        self::assertFalse($user->hasVerifiedEmail());
        $this->assertDatabaseHas('user_profiles',['user_id'=>$user->id,'slug'=>'main']);
        Notification::assertSentTo($user,VerifyEmail::class);
        $this->get('/account')->assertRedirect(route('verification.notice'));
    }

    public function test_login_regenerates_session_and_denies_suspended_accounts(): void
    {
        $user = User::factory()->create(['email'=>'login@example.test','password'=>Hash::make('ExampleStrong123')]);
        $this->post('/login',['email'=>'login@example.test','password'=>'ExampleStrong123'])
            ->assertRedirect(route('account.overview'));
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest();

        $user->forceFill(['is_active'=>false])->save();
        $this->post('/login',['email'=>'login@example.test','password'=>'ExampleStrong123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_only_verified_staff_can_access_admin_dashboard(): void
    {
        $viewer = User::factory()->create();
        $this->actingAs($viewer)->get('/dashboard')->assertForbidden();

        $editor = User::factory()->create();
        $editor->forceFill(['role'=>'editor'])->save();
        self::assertSame('editor', $editor->fresh()->role);
        self::assertTrue($editor->fresh()->is_active);
        self::assertTrue($editor->fresh()->hasVerifiedEmail());
        $this->withoutExceptionHandling();
        $this->actingAs($editor)->get('/dashboard')->assertOk();
        $this->actingAs($editor)->get('/dashboard/users')->assertForbidden();

        $unverified = User::factory()->unverified()->create();
        $unverified->forceFill(['role'=>'admin'])->save();
        $this->actingAs($unverified)->get('/dashboard')->assertRedirect(route('verification.notice'));
    }

    public function test_admin_catalog_can_create_draft_and_publish_metadata(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['role'=>'admin'])->save();
        self::assertSame('admin', $user->fresh()->role);
        self::assertTrue($user->fresh()->is_active);
        self::assertTrue($user->fresh()->hasVerifiedEmail());
        $this->actingAs($user);

        $this->post('/dashboard/catalog/movies',[
            'title'=>'Example Movie','release_year'=>2025,
            'is_premium'=>'1','status'=>'draft',
        ])->assertRedirect();

        $movie = Movie::query()->where('slug','example-movie')->firstOrFail();
        self::assertSame('draft',$movie->status);
        $this->get('/watch/movie/example-movie')->assertNotFound();

        $this->put('/dashboard/catalog/movies/'.$movie->id,[
            'title'=>'Example Movie','slug'=>'example-movie','release_year'=>2025,
            'is_premium'=>'0','status'=>'published',
        ])->assertRedirect();
        self::assertSame('published',$movie->fresh()->status);
        $this->get('/watch/movie/example-movie')->assertOk();
        $this->delete('/dashboard/catalog/movies/'.$movie->id)->assertStatus(422);
        $this->assertDatabaseHas('admin_audit_events',['action'=>'catalog.created','resource_id'=>$movie->id]);
    }

    public function test_tmdb_import_fails_closed_without_token(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['role'=>'admin'])->save();
        $this->actingAs($user);
        $this->post('/dashboard/tmdb/import',['type'=>'movie','tmdb_id'=>12345])->assertStatus(503);
    }
}
