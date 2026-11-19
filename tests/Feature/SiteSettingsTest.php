<?php

namespace Tests\Feature;

use App\Models\Episode;
use App\Models\Movie;
use App\Models\Season;
use App\Models\Series;
use App\Models\User;
use App\Services\Media\AutomaticPublicationService;
use App\Services\Rights\GlobalRights;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_upload_limit_applies_to_every_video_intake_path(): void
    {
        Storage::fake('local');
        Queue::fake();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get('/dashboard/site-settings')->assertOk()->assertSee('Maximum video size (MB)');
        $this->patch('/dashboard/site-settings/uploads', ['video_upload_max_mb' => 0])
            ->assertSessionHasErrors('video_upload_max_mb');
        $this->patch('/dashboard/site-settings/uploads', ['video_upload_max_mb' => 1])
            ->assertRedirect('/dashboard/site-settings#uploads');
        self::assertSame(1, SiteSettings::get('video_upload_max_mb'));

        $movie = Movie::create(['title' => 'Test Movie', 'slug' => 'test-movie', 'status' => 'draft']);
        $series = Series::create(['title' => 'Test TV Show', 'slug' => 'test-show', 'status' => 'draft']);
        $season = Season::create(['series_id' => $series->id, 'season_number' => 1, 'title' => 'Season 1']);
        $episode = Episode::create(['season_id' => $season->id, 'episode_number' => 1, 'title' => 'First Episode']);

        foreach ([
            ['/upload-studio', ['type' => 'movie', 'title_id' => $movie->id]],
            ['/upload-studio', ['type' => 'episode', 'title_id' => $episode->id]],
            ['/upload-studio/quick', ['rights_confirmed' => true]],
            [route('admin.media.ingest'), ['type' => 'movie', 'title_id' => $movie->id]],
        ] as [$url, $data]) {
            $this->postJson($url, $data + ['video' => UploadedFile::fake()->create('oversize.mp4', 1025, 'video/mp4')])
                ->assertUnprocessable()->assertJsonValidationErrors('video');
        }
        $this->assertDatabaseCount('media_ingests', 0);

        foreach ([
            ['movie', $movie],
            ['episode', $episode],
        ] as [$type, $title]) {
            $this->get(route('upload-studio.advanced', ['type' => $type, 'title_id' => $title->id]))
                ->assertOk()->assertSee('data-max-bytes="1048576"', false)
                ->assertSee('data-resume-target="'.$title->id.'"', false);
            $file = UploadedFile::fake()->create('within-limit.mp4', 1024, 'video/mp4');
            file_put_contents($file->getRealPath(), str_repeat('a', 1048576));
            $this->postJson('/upload-studio', ['type' => $type, 'title_id' => $title->id, 'video' => $file])
                ->assertCreated();
            $this->assertDatabaseHas('media_ingests', [
                'ingestable_type' => $type, 'ingestable_id' => $title->id, 'size_bytes' => 1048576,
            ]);
        }

        $this->actingAs(User::factory()->create())->patch('/dashboard/site-settings/uploads', ['video_upload_max_mb' => 2])
            ->assertForbidden();
        self::assertSame(1, SiteSettings::get('video_upload_max_mb'));
    }

    public function test_dashboard_media_switches_override_server_defaults_and_reach_playback(): void
    {
        config()->set('gomovie.transcoding_enabled', true);
        config()->set('gomovie.public_hls_enabled', true);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get('/dashboard/site-settings')->assertOk()->assertSee('Test FFmpeg');
        self::assertTrue(SiteSettings::get('transcoding_enabled'));

        $this->patch('/dashboard/site-settings/media', [
            'transcoding_enabled' => '0',
            'high_res_transcoding_enabled' => '1',
            'automatic_publication_enabled' => '0',
            'public_hls_enabled' => '0',
            'media_staging_enabled' => '0',
        ])->assertRedirect('/dashboard/site-settings#media');

        self::assertFalse(SiteSettings::get('transcoding_enabled'));
        self::assertTrue(SiteSettings::get('high_res_transcoding_enabled'));
        self::assertFalse(app(AutomaticPublicationService::class)->publicEnabled());
        self::assertTrue(config('gomovie.transcoding_enabled'));
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'site.settings.updated']);
        $this->assertDatabaseCount('media_ingests', 0);
    }

    public function test_tmdb_credential_is_private_and_switch_controls_catalog_import(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $token = str_repeat('test-token-', 3);

        $this->from('/dashboard/site-settings')->patch('/dashboard/site-settings/tmdb', [
            'tmdb_enabled' => 'invalid', 'tmdb_token' => $token,
        ])->assertSessionHasErrors('tmdb_enabled');
        $this->assertNull(session()->getOldInput('tmdb_token'));

        $this->patch('/dashboard/site-settings/tmdb', ['tmdb_enabled' => '1', 'tmdb_token' => $token])
            ->assertRedirect('/dashboard/site-settings#tmdb');
        $this->get('/dashboard/site-settings')->assertOk()->assertDontSee($token);
        $this->assertStringNotContainsString($token, DB::table('site_settings')->where('key', 'tmdb_token')->value('value'));
        $this->assertStringNotContainsString($token, DB::table('admin_audit_events')->where('action', 'site.settings.updated')->value('details'));

        Http::fake(['https://api.themoviedb.org/*' => Http::response(['id' => 10, 'title' => 'QA Movie'])]);
        $this->postJson('/dashboard/tmdb/preview', ['type' => 'movie', 'tmdb_id' => 10])
            ->assertOk()->assertJsonPath('data.title', 'QA Movie');
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer '.$token));

        $this->patch('/dashboard/site-settings/tmdb', ['tmdb_enabled' => '0'])
            ->assertRedirect('/dashboard/site-settings#tmdb');
        $this->postJson('/dashboard/tmdb/preview', ['type' => 'movie', 'tmdb_id' => 10])->assertStatus(503);
        Http::assertSentCount(1);
        self::assertSame($token, SiteSettings::get('tmdb_token'));
    }

    public function test_editor_cannot_read_change_or_test_site_configuration(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'editor']));
        $this->get('/dashboard/site-settings')->assertForbidden();
        $this->patch('/dashboard/site-settings/media', [])->assertForbidden();
        $this->post('/dashboard/site-settings/test-ffmpeg')->assertForbidden();
        $this->assertDatabaseCount('site_settings', 0);
    }

    public function test_rights_editor_requires_attestation_and_can_revoke_an_active_record(): void
    {
        $movie = Movie::query()->create(['title' => 'Rights QA', 'slug' => 'rights-qa', 'status' => 'draft']);
        $url = '/dashboard/distribution-rights/movie/'.$movie->id;
        $fields = ['rights_reference' => 'QA-AGREEMENT', 'status' => 'active'];
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get($url)->assertOk()->assertSee('A super administrator can add');
        $this->post($url, [...$fields, 'rights_confirmed' => 1])->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $this->from($url)->post($url, $fields)->assertSessionHasErrors('rights_confirmed');
        self::assertFalse(app(GlobalRights::class)->allows('movie', $movie->id));
        $this->post($url, [...$fields, 'rights_confirmed' => 1])->assertRedirect($url);
        self::assertTrue(app(GlobalRights::class)->allows('movie', $movie->id));
        $record = DB::table('content_rights')->where('licensable_id', $movie->id)->value('id');
        $this->patch($url.'/'.$record, [...$fields, 'status' => 'revoked'])->assertRedirect($url);
        self::assertFalse(app(GlobalRights::class)->allows('movie', $movie->id));
        $this->assertDatabaseHas('content_rights', ['id' => $record, 'status' => 'revoked']);
        $this->get('/dashboard/distribution-rights')->assertOk()->assertSee('QA-AGREEMENT');
    }
}
