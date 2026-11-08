<?php

namespace App\Services\Streaming;

use App\Models\Episode;
use App\Models\MediaAsset;
use App\Models\Movie;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class PlaybackGrantService
{
    /**
     * Authorizes a title, reserves a stream slot atomically, and signs a private
     * edge URL. The edge MUST validate all manifests, segments, keys and session
     * status; application signing alone does not constitute DRM.
     */
    public function issue(User $user, UserProfile $profile, string $type, int $id): array
    {
        abort_unless((int) $profile->user_id === (int) $user->id, 403);
        [$asset, $limit] = $this->authorize($user, $profile, $type, $id);
        $this->assertEdgeConfigured();

        return $this->withUserLock($user->id, function () use ($user, $profile, $asset, $limit): array {
            return DB::transaction(function () use ($user, $profile, $asset, $limit): array {
                DB::table('stream_sessions')->where('user_id', $user->id)
                    ->where('status', 'active')->where('expires_at', '<=', now())
                    ->update(['status' => 'expired', 'updated_at' => now()]);

                $current = DB::table('stream_sessions')->where('user_id', $user->id)
                    ->where('status', 'active')->where('expires_at', '>', now())->count();

                abort_if($current >= $limit, 409, 'Your simultaneous playback limit has been reached.');

                $sessionId = (string) Str::uuid();
                DB::table('stream_sessions')->insert([
                    'id' => $sessionId, 'user_id' => $user->id,
                    'user_profile_id' => $profile->id, 'media_asset_id' => $asset->id,
                    'status' => 'active', 'last_heartbeat_at' => now(),
                    'expires_at' => now()->addSeconds((int) config('gomovie.heartbeat_ttl', 90)),
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                return $this->grant($asset, $user, $profile, $sessionId);
            });
        });
    }

    public function heartbeat(User $user, string $sessionId): array
    {
        $this->assertEdgeConfigured();

        return $this->withUserLock($user->id, function () use ($user, $sessionId): array {
            return DB::transaction(function () use ($user, $sessionId): array {
                $session = DB::table('stream_sessions')
                    ->where('id', $sessionId)->where('user_id', $user->id)
                    ->where('status', 'active')->where('expires_at', '>', now())
                    ->lockForUpdate()->first();
                abort_unless($session, 404, 'Playback session is inactive or expired.');

                $profile = UserProfile::query()->where('user_id', $user->id)->findOrFail($session->user_profile_id);
                $asset = MediaAsset::query()->where('status', 'ready')->where('format', 'hls')
                    ->whereNull('drm_scheme')->findOrFail($session->media_asset_id);
                $watchable = $asset->streamable;
                abort_unless($watchable instanceof Movie || $watchable instanceof Episode, 403);
                [$allowedAsset] = $this->authorize($user, $profile, $asset->streamable_type, $watchable->id);
                abort_unless($allowedAsset->id === $asset->id, 403);

                DB::table('stream_sessions')->where('id', $sessionId)->update([
                    'last_heartbeat_at' => now(),
                    'expires_at' => now()->addSeconds((int) config('gomovie.heartbeat_ttl', 90)),
                    'updated_at' => now(),
                ]);

                return $this->grant($asset, $user, $profile, $sessionId);
            });
        });
    }

    public function stop(User $user, string $sessionId): void
    {
        DB::table('stream_sessions')->where('id', $sessionId)
            ->where('user_id', $user->id)->update([
                'status' => 'ended', 'revoked_at' => now(), 'updated_at' => now(),
            ]);
    }

    private function authorize(User $user, UserProfile $profile, string $type, int $id): array
    {
        abort_unless(in_array($type, ['movie', 'episode'], true), 404);
        // Kids playback is held closed until an age-rating/parental-control policy exists.
        abort_if($profile->is_kids, 403, 'Kids profile playback is not enabled.');

        if ($type === 'movie') {
            $title = Movie::query()->where('status', 'published')->findOrFail($id);
            $premium = $title->is_premium;
        } else {
            $title = Episode::query()->where('status', 'published')
                ->whereHas('season.series', fn ($q) => $q->where('status', 'published'))
                ->findOrFail($id);
            $premium = $title->season->series->is_premium;
        }
        abort_if($title->published_at && $title->published_at->isFuture(), 403);

        // Without verified geo-IP at the edge, only explicitly GLOBAL licensed
        // content can be granted. Never trust a client-supplied country header.
        $hasRights = DB::table('content_rights')
            ->where('licensable_type', $type)->where('licensable_id', $title->id)
            ->where('status', 'active')->whereNull('territories')
            ->where(function ($q): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })->where(function ($q): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })->exists();
        abort_unless($hasRights, 403, 'Streaming distribution rights are not active.');

        $subscription = Subscription::query()->where('user_id', $user->id)
            ->currentlyActive()->orderByDesc('starts_at')->first();
        abort_if($premium && !$subscription, 403, 'An active subscription is required.');

        $limit = $subscription ? max(1, (int) $subscription->max_concurrent_streams) : 1;
        $asset = $title->mediaAssets()->where('status', 'ready')->where('format', 'hls')
            ->whereNull('drm_scheme')->orderBy('id')->first();
        abort_unless($asset, 404, 'A playable licensed asset is not available.');

        return [$asset, $limit];
    }

    private function assertEdgeConfigured(): void
    {
        $edge = (string) config('gomovie.media_edge_url');
        $key = (string) config('gomovie.media_signing_key');
        abort_unless(config('gomovie.edge_enabled') === true && str_starts_with($edge, 'https://') && strlen($key) >= 32
            && strlen((string) config('gomovie.edge_shared_key')) >= 32,
            503, 'Private streaming edge is not configured.');
    }

    private function grant(MediaAsset $asset, User $user, UserProfile $profile, string $sessionId): array
    {
        $key = $asset->getRawOriginal('manifest_key');
        abort_unless(is_string($key) && preg_match('#\A(?!/)(?!.*\.\.)[A-Za-z0-9_./-]+\.m3u8\z#', $key),
            503, 'Invalid private manifest key.');

        $expires = now()->addSeconds(max(30, min(300, (int) config('gomovie.stream_token_ttl', 120))))->timestamp;
        $payload = implode("\n", ['gomovie-hls-v1', $key, $user->id, $profile->id, $sessionId, $expires]);
        $signature = hash_hmac('sha256', $payload, (string) config('gomovie.media_signing_key'));
        $path = implode('/', array_map('rawurlencode', explode('/', $key)));
        $query = http_build_query([
            'v' => 1, 'uid' => $user->id, 'pid' => $profile->id,
            'sid' => $sessionId, 'exp' => $expires, 'sig' => $signature,
        ], '', '&', PHP_QUERY_RFC3986);

        return [
            'session_id' => $sessionId,
            'playback_url' => rtrim((string) config('gomovie.media_edge_url'), '/').'/'.$path.'?'.$query,
            'expires_at' => gmdate(DATE_ATOM, $expires),
            'heartbeat_after_seconds' => 30,
        ];
    }

    private function withUserLock(int $userId, callable $callback): mixed
    {
        try {
            // Redis distributed lock ensures consistent stream-slot reservations
            // across all web servers. If Redis fails, fail closed.
            return Cache::store('redis')->lock('gomovie:playback:account:'.$userId, 10)
                ->block(2, $callback);
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            abort(503, 'Playback concurrency coordination is temporarily unavailable.');
        }
    }
}
