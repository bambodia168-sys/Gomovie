<?php

namespace Tests\Feature;

use App\Models\Episode;
use App\Models\Season;
use App\Models\Series;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeriesStudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_studio_manages_seasons_and_episodes_without_exposing_drafts(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role'=>'admin'])->save();
        $series = Series::create(['title'=>'Example Series','slug'=>'example-series','status'=>'published']);
        $this->actingAs($admin);

        $this->get('/dashboard/series/'.$series->id.'/seasons')->assertOk();
        $this->post('/dashboard/series/'.$series->id.'/seasons',[
            'season_number'=>1,'title'=>'Season One',
        ])->assertRedirect();
        $season=Season::query()->firstOrFail();

        $this->post('/dashboard/series/'.$series->id.'/seasons/'.$season->id.'/episodes',[
            'episode_number'=>1,'title'=>'Pilot','status'=>'draft',
        ])->assertRedirect();
        $episode=Episode::query()->firstOrFail();
        $this->get('/dashboard/series/'.$series->id.'/seasons')->assertOk()->assertSee('Pilot');
        $this->get('/watch/series/example-series')->assertDontSee('Pilot');

        $this->patch('/dashboard/series/'.$series->id.'/seasons/'.$season->id.'/episodes/'.$episode->id,[
            'episode_number'=>1,'title'=>'Pilot','status'=>'published',
        ])->assertRedirect();
        $this->get('/watch/series/example-series')->assertOk()->assertSee('Pilot');

        $this->delete('/dashboard/series/'.$series->id.'/seasons/'.$season->id.'/episodes/'.$episode->id)
            ->assertStatus(422);
    }

    public function test_studio_blocks_cross_series_episode_edits(): void
    {
        $admin=User::factory()->create();
        $admin->forceFill(['role'=>'admin'])->save();
        $first=Series::create(['title'=>'Series One','slug'=>'series-one','status'=>'draft']);
        $other=Series::create(['title'=>'Series Two','slug'=>'series-two','status'=>'draft']);
        $season=Season::create(['series_id'=>$first->id,'season_number'=>1,'title'=>'First season']);
        $this->actingAs($admin);
        $this->post('/dashboard/series/'.$other->id.'/seasons/'.$season->id.'/episodes',[
            'episode_number'=>1,'title'=>'Unexpected','status'=>'draft',
        ])->assertNotFound();
        $this->assertDatabaseCount('episodes',0);
    }
}
