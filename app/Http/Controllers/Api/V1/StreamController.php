<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserProfile;
use App\Services\Streaming\PlaybackGrantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StreamController extends Controller
{
    public function movie(Request $request, int $id, PlaybackGrantService $playback): JsonResponse
    {
        return $this->issue($request, 'movie', $id, $playback);
    }

    public function episode(Request $request, int $id, PlaybackGrantService $playback): JsonResponse
    {
        return $this->issue($request, 'episode', $id, $playback);
    }

    private function issue(Request $request, string $type, int $id, PlaybackGrantService $playback): JsonResponse
    {
        $data = $request->validate(['profile_id' => ['required', 'integer', 'exists:user_profiles,id']]);
        $profile = UserProfile::query()->where('user_id', $request->user()->id)
            ->findOrFail($data['profile_id']);

        return response()->json(['data' => $playback->issue($request->user(), $profile, $type, $id)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function heartbeat(Request $request, string $sessionId, PlaybackGrantService $playback): JsonResponse
    {
        return response()->json(['data' => $playback->heartbeat($request->user(), $sessionId)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function stop(Request $request, string $sessionId, PlaybackGrantService $playback): JsonResponse
    {
        $playback->stop($request->user(), $sessionId);
        return response()->json(['stopped' => true])->header('Cache-Control', 'private, no-store');
    }
}
