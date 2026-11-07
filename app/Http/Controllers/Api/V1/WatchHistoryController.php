<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WatchHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['profile_id' => ['required', 'integer']]);
        $profile = UserProfile::query()->where('user_id', $request->user()->id)->findOrFail($data['profile_id']);
        return response()->json(DB::table('watch_history')
            ->where('user_id', $request->user()->id)->where('user_profile_id', $profile->id)
            ->orderByDesc('last_watched_at')
            ->paginate(25, ['id', 'watchable_type', 'watchable_id', 'progress_seconds',
                'duration_seconds', 'completed', 'last_watched_at']))
            ->header('Cache-Control', 'private, no-store');
    }

    public function syncProgress(Request $request): JsonResponse
    {
        $data = $request->validate([
            'profile_id' => ['required', 'integer'],
            'watchable_type' => ['required', 'in:movie,episode'],
            'watchable_id' => ['required', 'integer', 'min:1'],
            'progress_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'duration_seconds' => ['required', 'integer', 'min:1', 'max:86400'],
        ]);
        UserProfile::query()->where('user_id', $request->user()->id)->findOrFail($data['profile_id']);
        if ($data['progress_seconds'] > $data['duration_seconds']) {
            throw ValidationException::withMessages(['progress_seconds' => 'Progress cannot exceed duration.']);
        }

        if ($data['watchable_type'] === 'movie') {
            Movie::query()->where('status', 'published')->findOrFail($data['watchable_id']);
        } else {
            Episode::query()->where('status', 'published')->whereHas('season.series',
                fn ($q) => $q->where('status', 'published'))->findOrFail($data['watchable_id']);
        }

        $identity = [
            'user_profile_id' => $data['profile_id'],
            'watchable_type' => $data['watchable_type'],
            'watchable_id' => $data['watchable_id'],
        ];
        $now = now();
        DB::table('watch_history')->upsert([array_merge($identity, [
            'user_id' => $request->user()->id,
            'progress_seconds' => $data['progress_seconds'],
            'duration_seconds' => $data['duration_seconds'],
            'completed' => $data['progress_seconds'] >= $data['duration_seconds'] * 0.95,
            'last_watched_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ])], ['user_profile_id', 'watchable_type', 'watchable_id'],
            ['progress_seconds', 'duration_seconds', 'completed', 'last_watched_at', 'updated_at']);

        return response()->json(['saved' => true])->header('Cache-Control', 'private, no-store');
    }
}
