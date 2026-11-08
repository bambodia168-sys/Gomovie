<?php

namespace Tests\Feature;

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Season;
use App\Models\Series;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Rights\GlobalRights;
use App\Services\Rights\PlaybackEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CentralRightsPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function right(Movie|Episode $title, array $overrides = []): void
    {
        DB::table('content_rights')->insert(array_merge([
            'licensable_type'=>$title instanceof Movie ? 'movie' : 'episode',
            'licensable_id'=>$title->id,'status'=>'active',
            'territories'=>null,'created_at'=>now(),'updated_at'=>now(),
        ], $overrides));
    }

    public function test_only_global_rights_inside_active_window_are_eligible(): void
    {
        $movie=Movie::create(['title'=>'Test film','slug'=>'test-film','status'=>'published','is_premium'=>false]);
        $rights=app(GlobalRights::class);
        self::assertFalse($rights->allows('movie',$movie->id));
        $this->right($movie,['starts_at'=>now()->addDay()]);
        self::assertFalse($rights->allows('movie',$movie->id));
        DB::table('content_rights')->where('licensable_id',$movie->id)->update([
            'starts_at'=>now()->subDay(),'ends_at'=>now()->subMinute()]);
        self::assertFalse($rights->allows('movie',$movie->id));
        DB::table('content_rights')->where('licensable_id',$movie->id)->update([
            'ends_at'=>null,'territories'=>json_encode(['KH'])]);
        self::assertFalse($rights->allows('movie',$movie->id));
        DB::table('content_rights')->where('licensable_id',$movie->id)->update(['territories'=>null]);
        self::assertTrue($rights->allows('movie',$movie->id));
        self::assertFalse($rights->allows('series',$movie->id));
    }

    public function test_premium_requires_a_subscription_even_with_global_rights(): void
    {
        $user=User::factory()->create();
        $profile=UserProfile::create(['user_id'=>$user->id,'name'=>'Main','slug'=>'main']);
        $movie=Movie::create(['title'=>'Premium film','slug'=>'premium-film',
            'status'=>'published','is_premium'=>true]);
        $this->right($movie);
        try {
            app(PlaybackEligibility::class)->requireEligible($user,$profile,$movie);
            self::fail('Premium streaming was allowed without entitlement.');
        } catch (HttpException $e) {
            self::assertSame(403,$e->getStatusCode());
        }
        Subscription::create(['user_id'=>$user->id,'plan_name'=>'QA plan',
            'status'=>'active','max_concurrent_streams'=>2,
            'starts_at'=>now()->subHour(),'ends_at'=>now()->addHour()]);
        self::assertNotNull(app(PlaybackEligibility::class)->requireEligible($user,$profile,$movie));
    }

    public function test_episode_requires_a_published_parent_series(): void
    {
        $user=User::factory()->create();
        $profile=UserProfile::create(['user_id'=>$user->id,'name'=>'Main','slug'=>'main']);
        $series=Series::create(['title'=>'Series','slug'=>'series','status'=>'draft','is_premium'=>false]);
        $season=Season::create(['series_id'=>$series->id,'season_number'=>1,'title'=>'One']);
        $episode=Episode::create(['season_id'=>$season->id,'episode_number'=>1,
            'title'=>'Pilot','status'=>'published']);
        $this->right($episode);
        $policy=app(PlaybackEligibility::class);
        try {
            $policy->requireEligible($user,$profile,$episode);
            self::fail('Unpublished parent series bypassed catalog gating.');
        } catch (HttpException $e) {
            self::assertSame(403,$e->getStatusCode());
        }
        $series->update(['status'=>'published']);
        self::assertNull($policy->requireEligible($user,$profile,$episode));
    }

    public function test_suspended_account_and_kids_profiles_remain_blocked(): void
    {
        $user=User::factory()->create();
        $profile=UserProfile::create(['user_id'=>$user->id,'name'=>'Kids','slug'=>'kids','is_kids'=>true]);
        $movie=Movie::create(['title'=>'Free film','slug'=>'free-film','status'=>'published','is_premium'=>false]);
        $this->right($movie);
        foreach (['is_kids'=>true,'is_active'=>false] as $field=>$value) {
            if ($field==='is_kids') {
                $profile->is_kids = $value;
            } else {
                $profile->is_kids = false;
                $user->is_active = $value;
            }
            try {
                app(PlaybackEligibility::class)->requireEligible($user,$profile,$movie);
                self::fail('Ineligible account or profile received playback approval.');
            } catch (HttpException $e) {
                self::assertSame(403,$e->getStatusCode());
            }
        }
    }
}
