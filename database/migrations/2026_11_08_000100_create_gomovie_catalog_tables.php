<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tmdb_id')->nullable()->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('overview')->nullable();
            $table->unsignedInteger('release_year')->nullable();
            $table->unsignedInteger('runtime_minutes')->nullable();
            $table->char('country_code', 2)->nullable()->index();
            $table->string('poster_path', 1024)->nullable();
            $table->string('backdrop_path', 1024)->nullable();
            $table->boolean('is_premium')->default(true);
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('series', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tmdb_id')->nullable()->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('overview')->nullable();
            $table->date('first_air_date')->nullable();
            $table->char('country_code', 2)->nullable()->index();
            $table->string('poster_path', 1024)->nullable();
            $table->string('backdrop_path', 1024)->nullable();
            $table->boolean('is_premium')->default(true);
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('seasons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('series_id')->constrained('series')->cascadeOnDelete();
            $table->unsignedInteger('season_number');
            $table->string('title');
            $table->string('poster_path', 1024)->nullable();
            $table->timestamps();
            $table->unique(['series_id', 'season_number']);
        });

        Schema::create('episodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('episode_number');
            $table->string('title');
            $table->text('overview')->nullable();
            $table->unsignedInteger('runtime_minutes')->nullable();
            $table->date('air_date')->nullable();
            $table->string('still_path', 1024)->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['season_id', 'episode_number']);
        });

        Schema::create('genres', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('genreables', function (Blueprint $table): void {
            $table->foreignId('genre_id')->constrained()->cascadeOnDelete();
            $table->morphs('genreable');
            $table->unique(['genre_id', 'genreable_type', 'genreable_id'], 'genreables_unique');
        });

        Schema::create('people', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tmdb_id')->nullable()->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('credits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->morphs('creditable');
            $table->string('role', 20);
            $table->string('character_name')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
            $table->index(['role', 'creditable_type', 'creditable_id'], 'credits_role_target');
        });

        Schema::create('content_rights', function (Blueprint $table): void {
            $table->id();
            $table->morphs('licensable');
            $table->string('rights_reference')->nullable();
            $table->json('territories')->nullable(); // null means global; restricted rights require trusted geo validation.
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamps();
        });

        Schema::create('media_assets', function (Blueprint $table): void {
            $table->id();
            $table->morphs('streamable');
            $table->string('storage_disk', 40)->default('private');
            $table->string('manifest_key', 1024); // PRIVATE object key, never a public URL.
            $table->string('format', 10)->default('hls');
            $table->string('status', 20)->default('processing')->index();
            $table->string('drm_scheme', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('subtitle_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_asset_id')->constrained()->cascadeOnDelete();
            $table->string('language', 10);
            $table->string('label')->nullable();
            $table->string('format', 10);
            $table->string('storage_key', 1024);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['subtitle_assets', 'media_assets', 'content_rights', 'credits',
                  'people', 'genreables', 'genres', 'episodes', 'seasons', 'series', 'movies'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
