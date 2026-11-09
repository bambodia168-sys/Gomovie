<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.settings', [
            'profiles' => UserProfile::query()->where('user_id', $request->user()->id)
                ->orderBy('id')->get(['id', 'name', 'slug', 'is_kids']),
        ]);
    }

    public function updateIdentity(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255',
                Rule::unique('users','email')->ignore($request->user()->id)],
        ]);
        $user = $request->user();
        $emailChanged = $user->email !== $data['email'];
        DB::transaction(function () use ($user, $data, $emailChanged): void {
            $user->name = $data['name'];
            $user->email = $data['email'];
            if ($emailChanged) $user->email_verified_at = null;
            $user->save();
        });
        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
            return redirect()->route('verification.notice')
                ->with('status', 'Confirm your new email address to continue.');
        }
        return back()->with('status', 'Account details updated.');
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);

        DB::transaction(function () use ($request, $data): void {
            $user = $request->user();
            $user->forceFill([
                'password' => Hash::make($data['password']),
                'remember_token' => Str::random(60),
            ])->save();
            $user->tokens()->delete();
        });
        $request->session()->regenerate();

        return back()->with('status', 'Password updated and API tokens revoked.');
    }

    public function storeProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required','string','max:80'],
            'is_kids' => ['sometimes','boolean'],
        ]);
        DB::transaction(function () use ($request, $data): void {
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if (UserProfile::query()->where('user_id',$request->user()->id)->count() >= 5) {
                throw ValidationException::withMessages(['name' => 'Up to five profiles are allowed.']);
            }
            $slug = Str::slug($data['name']);
            if (!$slug) {
                throw ValidationException::withMessages(['name' => 'Choose a usable profile name.']);
            }
            if (UserProfile::query()->where('user_id',$request->user()->id)
                ->where('slug',$slug)->exists()) {
                throw ValidationException::withMessages(['name' => 'A profile with this name already exists.']);
            }
            UserProfile::query()->create([
                'user_id' => $request->user()->id,
                'name' => $data['name'], 'slug' => $slug,
                'is_kids' => (bool) ($data['is_kids'] ?? false),
            ]);
        });
        return back()->with('status','Profile added.');
    }

    public function updateProfile(Request $request, int $profileId): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required','string','max:80'],
            'is_kids' => ['sometimes','boolean'],
        ]);
        DB::transaction(function () use ($request,$profileId,$data): void {
            $profile=UserProfile::query()->where('user_id',$request->user()->id)
                ->lockForUpdate()->findOrFail($profileId);
            $slug=Str::slug($data['name']);
            if ($slug === '') {
                throw ValidationException::withMessages(['name'=>'Choose a usable profile name.']);
            }
            if (UserProfile::query()->where('user_id',$request->user()->id)
                ->where('slug',$slug)->whereKeyNot($profileId)->exists()) {
                throw ValidationException::withMessages(['name'=>'A profile with this name already exists.']);
            }
            $profile->update([
                'name'=>$data['name'], 'slug'=>$slug,
                'is_kids'=>(bool)($data['is_kids']??false),
            ]);
        });
        return back()->with('status','Profile updated.');
    }

    public function removeProfile(Request $request, int $profileId): RedirectResponse
    {
        DB::transaction(function () use ($request, $profileId): void {
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $profile = UserProfile::query()->where('user_id',$request->user()->id)
                ->findOrFail($profileId);
            abort_if(UserProfile::query()->where('user_id',$request->user()->id)->count() <= 1,
                422, 'The last profile cannot be deleted.');
            abort_if(DB::table('stream_sessions')->where('user_profile_id',$profile->id)
                ->where('status','active')->where('expires_at','>',now())->exists(),
                409, 'Stop active playback before deleting this profile.');
            $profile->delete();
        });
        return back()->with('status','Profile removed.');
    }
}
