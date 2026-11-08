<?php

namespace App\Services\Rights;

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserProfile;

/**
 * Single policy decision used for initial grants and repeated edge validation.
 * It is deliberately independent of the Laravel HTTP authentication guard.
 */
final class PlaybackEligibility
{
    public function __construct(private readonly GlobalRights $rights)
    {
    }

    public function requireEligible(User $user, UserProfile $profile, Movie|Episode $title): ?Subscription
    {
        abort_unless($user->is_active && $user->hasVerifiedEmail(), 403, 'Account is not eligible for playback.');
        abort_unless((int) $profile->user_id === (int) $user->id, 403, 'Profile ownership mismatch.');
        abort_if($profile->is_kids, 403, 'Kids profile playback is not enabled.');

        abort_unless(
            $title->status === 'published'
                && (!$title->published_at || !$title->published_at->isFuture()),
            403,
            'This title is not published.'
        );

        $premium = $title->is_premium;
        $type = 'movie';
        if ($title instanceof Episode) {
            $series = $title->season?->series;
            abort_unless(
                $series && $series->status === 'published'
                    && (!$series->published_at || !$series->published_at->isFuture()),
                403,
                'The parent series is not published.'
            );
            $premium = $series->is_premium;
            $type = 'episode';
        }

        $this->rights->requireActive($type, (int) $title->id);

        $subscription = Subscription::query()->where('user_id', $user->id)
            ->currentlyActive()->orderByDesc('starts_at')->first();
        abort_if($premium && !$subscription, 403, 'An active subscription is required.');

        return $subscription;
    }
}
