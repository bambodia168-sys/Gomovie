<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => UserProfile::query()->where('user_id', $request->user()->id)
            ->orderBy('id')->get(['id', 'name', 'slug', 'is_kids'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:80'],
            'is_kids' => ['sometimes', 'boolean'],
        ]);
        $profile = DB::transaction(function () use ($request, $data): UserProfile {
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $count = UserProfile::query()->where('user_id', $request->user()->id)->count();
            if ($count >= 5) throw ValidationException::withMessages(['name' => 'Maximum five profiles per account.']);
            $slug = Str::slug($data['name']);
            if (!$slug) throw ValidationException::withMessages(['name' => 'Enter a usable profile name.']);
            if (UserProfile::query()->where('user_id', $request->user()->id)->where('slug', $slug)->exists()) {
                throw ValidationException::withMessages(['name' => 'A profile with this name already exists.']);
            }
            return UserProfile::query()->create([
                'user_id' => $request->user()->id,
                'name' => $data['name'], 'slug' => $slug,
                'is_kids' => $data['is_kids'] ?? false,
            ]);
        });
        return response()->json(['data' => $profile->only(['id', 'name', 'slug', 'is_kids'])], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        UserProfile::query()->where('user_id', $request->user()->id)->findOrFail($id)->delete();
        return response()->json(['deleted' => true]);
    }
}
