<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\MediaAsset;
use App\Models\Movie;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EdgeAuthorizationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(config('gomovie.edge_enabled') === true, 403);
        $secret = (string) config('gomovie.edge_shared_key');
        $given = (string) $request->header('X-Gomovie-Edge-Key', '');
        abort_unless(strlen($secret) >= 32 && strlen($given) >= 32 && hash_equals($secret, $given), 403);

        $data = $request->validate([
            'sid' => ['required','uuid'],
            'uid' => ['required','integer','min:1'],
            'pid' => ['required','integer','min:1'],
            'manifest_key' => ['required','string','max:1024','regex:/^[A-Za-z0-9_.\/-]+\.m3u8$/'],
        ]);

        $session = DB::table('stream_sessions')->where('id',$data['sid'])
            ->where('user_id',$data['uid'])->where('user_profile_id',$data['pid'])
            ->where('status','active')->where('expires_at','>',now())->first();
        abort_unless($session, 403);

        $user = User::find($data['uid']);
        abort_unless($user, 403);
        $profile = \App\Models\UserProfile::query()
            ->where('user_id', $user->id)->find($data['pid']);
        abort_unless($profile, 403);

        $asset = MediaAsset::query()->where('status','ready')->where('format','hls')
            ->whereNull('drm_scheme')->find($session->media_asset_id);
        abort_unless($asset && hash_equals((string) $asset->getRawOriginal('manifest_key'),$data['manifest_key']),403);
        $title = $asset->streamable;
        abort_unless($title instanceof Movie || $title instanceof Episode,403);

        app(\App\Services\Rights\PlaybackEligibility::class)
            ->requireEligible($user, $profile, $title);

        return response()->json(['allowed'=>true])->header('Cache-Control','private, no-store');
    }
}
