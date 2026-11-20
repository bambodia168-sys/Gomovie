<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdministratorTwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdministratorTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private const SETTINGS = '/account/security/two-factor';

    private const CHALLENGE = '/login/two-factor';

    private const PASSWORD = 'test-admin-password-123';

    public function test_an_administrator_must_confirm_enrollment_and_then_supply_a_fresh_code_to_sign_in(): void
    {
        $this->freezeTime();
        $admin = $this->administrator();
        $this->actingAs($admin);
        $this->get('/account/settings')->assertOk()->assertSee('Two-factor authentication');
        $this->post(self::SETTINGS.'/setup', ['current_password' => 'incorrect'])
            ->assertSessionHasErrors('current_password');
        $secret = $this->begin();
        $this->assertNull($admin->fresh()->two_factor_secret);

        $code = $this->code($secret);
        $incorrect = $code === '000000' ? '111111' : '000000';
        $this->from(self::SETTINGS)->post(self::SETTINGS.'/confirm', ['two_factor_code' => $incorrect])
            ->assertSessionHasErrors('two_factor_code');
        $this->assertFalse(session()->hasOldInput('two_factor_code'));

        $enrollment = $this->post(self::SETTINGS.'/confirm', ['two_factor_code' => $code])
            ->assertOk()->assertSee('Save your recovery codes');
        $this->assertStringContainsString('no-store', $enrollment->headers->get('Cache-Control'));
        $codes = $enrollment->viewData('codes');
        $this->assertCount(8, $codes);
        $this->assertCount(8, array_unique($codes));
        $stored = DB::table('users')->find($admin->id);
        $this->assertNotSame($secret, $stored->two_factor_secret);
        $this->assertSame($secret, Crypt::decryptString($stored->two_factor_secret));
        $this->assertStringNotContainsString(str_replace('-', '', $codes[0]), $stored->two_factor_recovery_hashes);
        $this->assertArrayNotHasKey('two_factor_secret', $admin->fresh()->toArray());
        $this->get(self::SETTINGS)->assertOk()->assertDontSee($codes[0]);
        $this->get('/dashboard')->assertOk();
        $this->post('/logout');

        $this->loginToChallenge($admin, true);
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get(self::CHALLENGE)->assertOk()->assertSee('Verify your sign-in');
        $this->from(self::CHALLENGE)->post(self::CHALLENGE, ['two_factor_code' => $code])
            ->assertSessionHasErrors('two_factor_code');
        $this->assertGuest();

        $this->travel(30)->seconds();
        $this->post(self::CHALLENGE, ['two_factor_code' => $this->code($secret)])
            ->assertRedirect(route('admin.dashboard'))
            ->assertCookieMissing(Auth::guard('web')->getRecallerName());
        $this->assertAuthenticatedAs($admin);
        $this->get('/dashboard')->assertOk();
    }

    public function test_recovery_codes_are_single_use_and_replacing_an_authenticator_invalidates_old_codes_and_sessions(): void
    {
        $this->freezeTime();
        $admin = $this->administrator();
        $this->actingAs($admin);
        $secret = $this->begin();
        $codes = $this->post(self::SETTINGS.'/confirm', ['two_factor_code' => $this->code($secret)])
            ->assertOk()->viewData('codes');
        $oldClaim = session('administratorTwoFactor.verified');
        $this->post('/logout');

        $this->loginToChallenge($admin);
        $this->post(self::CHALLENGE, ['two_factor_code' => strtolower($codes[0])])->assertRedirect(route('admin.dashboard'));
        $this->assertCount(7, $admin->fresh()->two_factor_recovery_hashes);
        $this->post('/logout');

        $this->loginToChallenge($admin);
        $this->from(self::CHALLENGE)->post(self::CHALLENGE, ['two_factor_code' => $codes[0]])
            ->assertSessionHasErrors('two_factor_code');
        $this->travel(30)->seconds();
        $this->post(self::CHALLENGE, ['two_factor_code' => $this->code($secret)])->assertRedirect(route('admin.dashboard'));

        $this->from(self::SETTINGS)->post(self::SETTINGS.'/setup', [
            'current_password' => self::PASSWORD, 'two_factor_code' => $codes[0],
        ])->assertSessionHasErrors('two_factor_code');
        $this->assertNull(session(AdministratorTwoFactor::ENROLLMENT));
        $replacement = $this->begin($codes[2]);
        $this->assertSame($secret, $admin->fresh()->two_factor_secret);
        $newCodes = $this->post(self::SETTINGS.'/confirm', ['two_factor_code' => $this->code($replacement)])
            ->assertOk()->viewData('codes');
        $this->assertNotSame($codes, $newCodes);
        $this->assertSame($replacement, $admin->fresh()->two_factor_secret);
        $this->get('/dashboard')->assertOk();

        $this->withSession(['administratorTwoFactor.verified' => $oldClaim])
            ->get('/upload-studio')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->loginToChallenge($admin);
        $this->from(self::CHALLENGE)->post(self::CHALLENGE, ['two_factor_code' => $codes[3]])
            ->assertSessionHasErrors('two_factor_code');
        $this->post(self::CHALLENGE, ['two_factor_code' => $newCodes[0]])->assertRedirect(route('admin.dashboard'));
    }

    public function test_the_final_recovery_code_can_restore_an_account_to_a_new_authenticator(): void
    {
        $this->freezeTime();
        $admin = $this->administrator();
        $this->actingAs($admin);
        $secret = $this->begin();
        $code = $this->post(self::SETTINGS.'/confirm', ['two_factor_code' => $this->code($secret)])
            ->assertOk()->viewData('codes')[0];
        $admin->fresh()->forceFill([
            'two_factor_recovery_hashes' => [hash('sha256', str_replace('-', '', $code))],
        ])->save();
        $this->post('/logout');
        $this->loginToChallenge($admin);
        $this->post(self::CHALLENGE, ['two_factor_code' => $code])->assertRedirect(route('admin.dashboard'));
        $this->assertEmpty($admin->fresh()->two_factor_recovery_hashes);

        $this->get(self::SETTINGS)->assertOk()->assertSee('You signed in with a recovery code.');
        $replacement = $this->begin();
        $this->post(self::SETTINGS.'/confirm', ['two_factor_code' => $this->code($replacement)])
            ->assertOk()->assertSee('Save your recovery codes');
        $this->assertCount(8, $admin->fresh()->two_factor_recovery_hashes);
    }

    public function test_required_enrollment_routes_administrators_to_setup_and_keeps_viewer_sign_in_normal(): void
    {
        config(['gomovie.administrator_two_factor_required' => true]);
        $admin = $this->administrator();
        $this->post('/login', ['email' => $admin->email, 'password' => self::PASSWORD])->assertRedirect(route('admin.dashboard'));
        $this->get('/dashboard')->assertRedirect(self::SETTINGS);
        $this->get('/upload-studio/advanced')->assertRedirect(self::SETTINGS);
        $this->get(self::SETTINGS)->assertOk()->assertSee('Set up authenticator');
        $this->post('/logout')->assertRedirect(route('home'));
        $viewer = User::factory()->create(['password' => self::PASSWORD]);
        $this->post('/login', ['email' => $viewer->email, 'password' => self::PASSWORD])->assertRedirect(route('account.overview'));
        $this->get('/account/settings')->assertOk()->assertDontSee('Two-factor authentication');
        $this->get(self::SETTINGS)->assertForbidden();
        $this->assertAuthenticatedAs($viewer);
    }

    public function test_expired_setup_and_challenges_require_reauthentication_and_challenges_are_account_throttled(): void
    {
        $this->freezeTime();
        $admin = $this->administrator();
        $this->actingAs($admin);
        $first = $this->begin();
        $this->travel(11)->minutes();
        $this->post(self::SETTINGS.'/confirm', ['two_factor_code' => $this->code($first)])
            ->assertRedirect(self::SETTINGS);
        $this->assertNull($admin->fresh()->two_factor_secret);
        $secret = $this->begin();
        $codes = $this->post(self::SETTINGS.'/confirm', ['two_factor_code' => $this->code($secret)])
            ->assertOk()->viewData('codes');
        $this->post('/logout');
        $this->loginToChallenge($admin);
        $this->travel(6)->minutes();
        $this->post(self::CHALLENGE, ['two_factor_code' => $codes[0]])->assertRedirect(route('login'));
        $this->assertGuest();

        $this->loginToChallenge($admin);
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->from(self::CHALLENGE)->post(self::CHALLENGE, ['two_factor_code' => 'wrong'])
                ->assertSessionHasErrors('two_factor_code');
        }
        $this->from(self::CHALLENGE)->post(self::CHALLENGE, ['two_factor_code' => $codes[0]])
            ->assertSessionHasErrors(['two_factor_code' => 'Too many attempts. Wait two minutes and try again.']);
        $this->assertGuest();
        $this->travel(121)->seconds();
        $this->post(self::CHALLENGE, ['two_factor_code' => $codes[0]])->assertRedirect(route('admin.dashboard'));
    }

    private function administrator(): User
    {
        return User::factory()->create(['role' => 'admin', 'password' => self::PASSWORD]);
    }

    private function begin(?string $oldCode = null): string
    {
        $fields = ['current_password' => self::PASSWORD];
        if ($oldCode !== null) {
            $fields['two_factor_code'] = $oldCode;
        }
        $this->post(self::SETTINGS.'/setup', $fields)->assertRedirect(self::SETTINGS);

        return $this->get(self::SETTINGS)->assertOk()->assertSee('Authenticator setup QR code')->viewData('secret');
    }

    private function code(string $secret): string
    {
        return app(Google2FA::class)->oathTotp($secret, intdiv(now()->timestamp, 30));
    }

    private function loginToChallenge(User $user, bool $remember = false): void
    {
        $this->post('/login', [
            'email' => $user->email, 'password' => self::PASSWORD, 'remember' => $remember,
        ])->assertRedirect(self::CHALLENGE);
        $this->assertGuest();
    }
}
