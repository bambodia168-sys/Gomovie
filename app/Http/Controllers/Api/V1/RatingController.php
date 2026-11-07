<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\Series;
use App\Models\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RatingController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $values = $request->validate([
            'profile_id' => ['required', 'integer'],
            'rateable_type' => ['required', 'in:movie,series'],
            'rateable_id' => ['required', 'integer', 'min:1'],
            'rating' => ['required', 'integer', 'between:1,10'],
        ]);
        UserProfile::query()->where('user_id', $request->user()->id)->findOrFail($values['profile_id']);
        $model = $values['rateable_type'] === 'movie' ? Movie::class : Series::class;
        $model::query()->where('status', 'published')->findOrFail($values['rateable_id']);

        $identity = [
            'user_profile_id' => $values['profile_id'],
            'rateable_type' => $values['rateable_type'],
            'rateable_id' => $values['rateable_id'],
        ];
        DB::table('ratings')->upsert([array_merge($identity, [
            'rating' => $values['rating'], 'created_at' => now(), 'updated_at' => now(),
        ])], ['user_profile_id', 'rateable_type', 'rateable_id'], ['rating', 'updated_at']);

        return response()->json(['saved' => true, 'rating' => $values['rating']]);
    }
}
