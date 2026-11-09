<?php

namespace Tests\Feature;

use App\Models\Episode;
use App\Models\Season;
use App\Models\Series;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class StudioConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user=User::factory()->create();
        $user->forceFill(['role'=>'super_admin'])->save();
        return $user;
    }

    public function test_episode_tables_have_independent_ten_item_pagination_and_outside_action_menus(): void
    {
        $this->actingAs($this->superAdmin());
        $series=Series::create(['title'=>'Table QA Series','slug'=>'table-qa-series','status'=>'published']);
        $first=Season::create(['series_id'=>$series->id,'season_number'=>1,'title'=>'First season']);
        $second=Season::create(['series_id'=>$series->id,'season_number'=>2,'title'=>'Second season']);
        for ($number=1;$number<=13;$number++) {
            Episode::create(['season_id'=>$first->id,'episode_number'=>$number,
                'title'=>sprintf('First Episode %02d',$number),'status'=>'draft']);
        }
        Episode::create(['season_id'=>$second->id,'episode_number'=>1,
            'title'=>'Independent season episode','status'=>'draft']);

        $url='/dashboard/series/'.$series->id.'/seasons';
        $this->get($url)->assertOk()
            ->assertSee('First Episode 10')
            ->assertDontSee('First Episode 11')
            ->assertSee('Independent season episode')
            ->assertSee('data-studio-menu')
            ->assertSee('gm-studio-modal')
            ->assertSee('Go to')
            ->assertSee('10 items per page')
            ->assertSee('gm-episode-page-'.$first->id);
        $this->get($url.'?episodes_'.$first->id.'=2')->assertOk()
            ->assertSee('First Episode 11')
            ->assertSee('First Episode 13')
            ->assertDontSee('First Episode 10')
            ->assertSee('Independent season episode');
    }

    public function test_watch_template_and_reset_email_pages_have_editable_copy_without_changing_routes(): void
    {
        $this->actingAs($this->superAdmin());
        $update=function (string $slug,string $heading): void {
            $this->put('/dashboard/appearance/pages/'.$slug,[
                'heading'=>$heading,
            ])->assertRedirect();
        };
        $update('email-verification','Mail confirmation pending');
        $update('reset-password','Create another secure password');
        $update('watch','About this title');

        $unverified=User::factory()->unverified()->create();
        $this->actingAs($unverified)->get('/email/verify')->assertOk()
            ->assertSee('Mail confirmation pending')
            ->assertSee('Resend verification email');
        // Password reset is guest-only. Sign out before loading its form.
        $this->post('/logout')->assertRedirect();
        $this->get('/reset-password/example-token')
            ->assertOk()->assertSee('Create another secure password')
            ->assertSee('name="token"',false);
        $this->actingAs($this->superAdmin())->get('/dashboard/appearance')->assertOk()
            ->assertSee('Email verification')
            ->assertSee('Reset password form');
        $this->get('/watch/movie/not-a-published-title')->assertNotFound();

        $movie=\App\Models\Movie::create([
            'title'=>'Test Watch Heading','slug'=>'test-watch-heading','status'=>'published',
        ]);
        $this->get('/watch/movie/'.$movie->slug)->assertOk()
            ->assertSee('About this title')
            ->assertSee('Related Movies');
        $this->assertDatabaseCount('studio_pages',3);
    }

    public function test_catalog_editor_primary_actions_are_in_top_right_workspace(): void
    {
        $this->actingAs($this->superAdmin());
        $response=$this->get('/dashboard/catalog/movies/create');
        $response->assertOk()
            ->assertSee('gm-page-heading')
            ->assertSee('gm-page-actions')
            ->assertSee('form="title-form"',false)
            ->assertSee('Create title');
    }
}
