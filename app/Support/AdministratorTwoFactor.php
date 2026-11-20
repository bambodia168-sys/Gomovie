<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use SensitiveParameter;

final class AdministratorTwoFactor
{
    public const CHALLENGE = 'administratorTwoFactor.challenge';

    public const ENROLLMENT = 'administratorTwoFactor.enrollment';

    private const VERIFIED = 'administratorTwoFactor.verified';

    private const RECOVERY = 'administratorTwoFactor.recovery';

    public function __construct(private Google2FA $totp) {}

    public static function enabled(User $user): bool
    {
        return $user->two_factor_version !== null;
    }

    public static function required(User $user): bool
    {
        return config('gomovie.administrator_two_factor_required')
            && StudioNavigation::administrator($user);
    }

    public static function verified(Request $request, User $user): bool
    {
        $claim = $request->session()->get(self::VERIFIED);

        return is_string($claim) && hash_equals(self::credentialFingerprint($user), $claim);
    }

    public static function markVerified(Request $request, User $user): void
    {
        $request->session()->put(self::VERIFIED, self::credentialFingerprint($user));
    }

    public static function recordRecoverySignIn(Request $request, User $user): void
    {
        $request->session()->put(self::RECOVERY, [
            'credential' => self::credentialFingerprint($user),
            'expires' => now()->addMinutes(10)->timestamp,
        ]);
    }

    public static function recentlyRecovered(Request $request, User $user): bool
    {
        $recovery = $request->session()->get(self::RECOVERY);

        return $recovery && $recovery['expires'] > now()->timestamp
            && hash_equals($recovery['credential'], self::credentialFingerprint($user));
    }

    public static function credentialFingerprint(User $user): string
    {
        return hash('sha256', $user->id.'|'.$user->two_factor_version.'|'.$user->getAuthPassword());
    }

    public function generateSecret(): string
    {
        return $this->totp->generateSecretKey();
    }

    public function accept(User $candidate, #[SensitiveParameter] string $code): ?User
    {
        return DB::transaction(function () use ($candidate, $code): ?User {
            $user = User::query()->lockForUpdate()->find($candidate->id);
            if (! $user?->is_active || ! self::enabled($user)
                || ! hash_equals(self::credentialFingerprint($candidate), self::credentialFingerprint($user))) {
                return null;
            }

            if (preg_match('/^\d{6}$/D', $code)) {
                $step = $this->matchStep($user->two_factor_secret, $code, $user->two_factor_last_step);
                if ($step === false) {
                    return null;
                }
                $user->forceFill(['two_factor_last_step' => $step])->save();

                return $user;
            }

            $normalized = strtoupper(str_replace('-', '', $code));
            if (! preg_match('/^[A-F0-9]{24}$/D', $normalized)) {
                return null;
            }
            $hash = hash('sha256', $normalized);
            $hashes = $user->two_factor_recovery_hashes ?? [];
            foreach ($hashes as $index => $saved) {
                if (hash_equals($saved, $hash)) {
                    unset($hashes[$index]);
                    $user->forceFill(['two_factor_recovery_hashes' => array_values($hashes)])->save();

                    return $user;
                }
            }

            return null;
        }, 3);
    }

    public function enroll(User $candidate, array $pending, #[SensitiveParameter] string $secret, #[SensitiveParameter] string $code): ?array
    {
        $step = $this->matchStep($secret, $code);
        if ($step === false) {
            return null;
        }

        return DB::transaction(function () use ($candidate, $pending, $secret, $step): ?array {
            $user = User::query()->lockForUpdate()->find($candidate->id);
            if (! $user || ! StudioNavigation::administrator($user)
                || ! hash_equals($pending['credential'], self::credentialFingerprint($user))) {
                return null;
            }
            $codes = [];
            $hashes = [];
            for ($i = 0; $i < 8; $i++) {
                $value = strtoupper(bin2hex(random_bytes(12)));
                $codes[] = implode('-', str_split($value, 8));
                $hashes[] = hash('sha256', $value);
            }
            $user->forceFill([
                'two_factor_secret' => $secret,
                'two_factor_version' => (string) Str::uuid(),
                'two_factor_last_step' => $step,
                'two_factor_recovery_hashes' => $hashes,
                'remember_token' => Str::random(60),
            ])->save();

            return ['user' => $user, 'codes' => $codes];
        }, 3);
    }

    private function matchStep(#[SensitiveParameter] string $secret, #[SensitiveParameter] string $code, ?int $lastStep = null): int|false
    {
        // One adjacent window for device clock skew. A database row lock keeps
        // each accepted time step and recovery code single-use across sessions.
        $step = intdiv(now()->timestamp, $this->totp->getKeyRegeneration());

        return $this->totp->verifyKeyNewer($secret, $code, $lastStep ?? 0, 1, $step);
    }
}
