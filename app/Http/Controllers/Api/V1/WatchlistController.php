<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\Series;
use App\Models\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WatchlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $values = $request->validate(['profile_id' => ['required', 'integer']]);
        $profile = $this->profile($request, $values['profile_id']);
        return response()->json(DB::table('watchlists')->where('user_profile_id', $profile->id)
            ->orderByDesc('created_at')->paginate(25, ['id', 'watchable_type', 'watchable_id', 'created_at']))
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request): JsonResponse
    {
        $values = $request->validate([
            'profile_id' => ['required', 'integer'],
            'watchable_type' => ['required', 'in:movie,series'],
            'watchable_id' => ['required', 'integer', 'min:1'],
        ]);
        $profile = $this->profile($request, $values['profile_id']);
        $model = $values['watchable_type'] === 'movie' ? Movie::class : Series::class;
        $model::query()->where('status', 'published')->findOrFail($values['watchable_id']);
        DB::table('watchlists')->insertOrIgnore([
            'user_profile_id' => $profile->id, 'watchable_type' => $values['watchable_type'],
            'watchable_id' => $values['watchable_id'], 'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['saved' => true], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $values = $request->validate(['profile_id' => ['required', 'integer']]);
        $profile = $this->profile($request, $values['profile_id']);
        DB::table('watchlists')->where('user_profile_id', $profile->id)->where('id', $id)->delete();
        return response()->json(['deleted' => true]);
    }

    private function profile(Request $request, int $id): UserProfile
    {
        return UserProfile::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }
}
