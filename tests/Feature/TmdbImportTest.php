<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TmdbImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_tmdb_import_only_creates_a_draft_without_streaming_rights(): void
    {
        config()->set('gomovie.tmdb_token',str_repeat('x',50));
        Http::fake(['api.themoviedb.org/*'=>Http::response([
            'id'=>98765, 'title'=>'A Fictional Title',
            'overview'=>'Example synopsis for metadata testing',
            'release_date'=>'2025-01-15','runtime'=>102,
            'poster_path'=>'/example.jpg','backdrop_path'=>'/backdrop.jpg',
        ])]);
        $admin=User::factory()->create();
        $admin->forceFill(['role'=>'admin'])->save();
        $this->actingAs($admin);
        $this->post('/dashboard/tmdb/import',['type'=>'movie','tmdb_id'=>98765])->assertRedirect();

        $movie=Movie::query()->where('tmdb_id',98765)->firstOrFail();
        self::assertSame('draft',$movie->status);
        self::assertSame('a-fictional-title-98765',$movie->slug);
        $this->assertDatabaseCount('content_rights',0);
        $this->assertDatabaseCount('media_assets',0);
        $this->get('/watch/movie/'.$movie->slug)->assertNotFound();
    }
}
