<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $subscription = Subscription::query()->where('user_id', $request->user()->id)
            ->currentlyActive()->orderByDesc('starts_at')->first();
        return response()->json(['data' => $subscription ? [
            'tier' => $subscription->plan_name,
            'status' => $subscription->status,
            'max_concurrent_streams' => $subscription->max_concurrent_streams,
            'ends_at' => $subscription->ends_at,
        ] : [
            'tier' => 'free', 'status' => 'free', 'max_concurrent_streams' => 1,
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function subscribe(): JsonResponse
    {
        // No activation by client POST. Verified payment-provider webhooks and
        // merchant compliance must be implemented before this is enabled.
        return response()->json([
            'message' => 'Purchases and recurring billing are not enabled.',
            'code' => 'BILLING_NOT_CONFIGURED',
        ], 503)->header('Cache-Control', 'no-store');
    }
}
