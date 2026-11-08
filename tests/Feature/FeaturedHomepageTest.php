<?php

namespace Tests\Feature;

use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeaturedHomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_movie_appears_in_featured_carousel_but_draft_does_not(): void
    {
        Movie::create(['title'=>'An Available Feature','slug'=>'an-available-feature','status'=>'published','is_premium'=>false]);
        Movie::create(['title'=>'Unreleased Draft','slug'=>'unreleased-draft','status'=>'draft']);
        $this->get('/')->assertOk()->assertSee('An Available Feature')->assertDontSee('Unreleased Draft')
            ->assertSee('/watch/movie/an-available-feature');
    }

    public function test_empty_homepage_renders_exploration_instead_of_a_broken_image(): void
    {
        $this->get('/')->assertOk()->assertSee('Browse catalog');
    }
}
