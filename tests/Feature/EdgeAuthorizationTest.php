<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\Movie;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EdgeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_edge_rejects_unconfigured_or_invalid_secrets(): void
    {
        $payload = ['sid'=>(string) Str::uuid(),'uid'=>1,'pid'=>1,'manifest_key'=>'movie/1/private/master.m3u8'];
        $this->postJson('/api/v1/internal/media/authorize',$payload)->assertForbidden();
        config()->set('gomovie.edge_enabled',true);
        config()->set('gomovie.edge_shared_key',str_repeat('a',48));
        $this->withHeader('X-Gomovie-Edge-Key',str_repeat('b',48))
            ->postJson('/api/v1/internal/media/authorize',$payload)->assertForbidden();
    }

    public function test_edge_requires_live_session_and_active_distribution_rights(): void
    {
        $secret = str_repeat('s',48);
        config()->set('gomovie.edge_enabled',true);
        config()->set('gomovie.edge_shared_key',$secret);
        $user = User::factory()->create();
        $profile = UserProfile::create(['user_id'=>$user->id,'name'=>'Main','slug'=>'main']);
        $movie = Movie::create(['title'=>'Free Licensed Film','slug'=>'free-licensed-film',
            'status'=>'published','is_premium'=>false]);
        $asset = MediaAsset::create([
            'streamable_type'=>'movie','streamable_id'=>$movie->id,
            'storage_disk'=>'local','manifest_key'=>'movie/1/private/master.m3u8',
            'format'=>'hls','status'=>'ready',
        ]);
        $sid = (string) Str::uuid();
        DB::table('stream_sessions')->insert([
            'id'=>$sid,'user_id'=>$user->id,'user_profile_id'=>$profile->id,
            'media_asset_id'=>$asset->id,'status'=>'active','last_heartbeat_at'=>now(),
            'expires_at'=>now()->addMinute(),'created_at'=>now(),'updated_at'=>now(),
        ]);
        $payload = ['sid'=>$sid,'uid'=>$user->id,'pid'=>$profile->id,
            'manifest_key'=>'movie/1/private/master.m3u8'];
        $this->withHeader('X-Gomovie-Edge-Key',$secret)
            ->postJson('/api/v1/internal/media/authorize',$payload)->assertForbidden();

        DB::table('content_rights')->insert([
            'licensable_type'=>'movie','licensable_id'=>$movie->id,'status'=>'active',
            'territories'=>null,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->withHeader('X-Gomovie-Edge-Key',$secret)
            ->postJson('/api/v1/internal/media/authorize',$payload)
            ->assertOk()->assertJsonPath('allowed',true);

        DB::table('stream_sessions')->where('id',$sid)->update(['status'=>'ended']);
        $this->withHeader('X-Gomovie-Edge-Key',$secret)
            ->postJson('/api/v1/internal/media/authorize',$payload)->assertForbidden();
    }
}
