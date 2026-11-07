<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('slug', 100);
            $table->boolean('is_kids')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'slug']);
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan_name', 20)->default('basic');
            $table->unsignedSmallInteger('max_concurrent_streams')->default(1);
            $table->string('status', 20)->default('pending');
            $table->string('provider_name', 40)->nullable();
            $table->string('provider_subscription_id')->nullable()->unique();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'ends_at']);
        });

        Schema::create('rental_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('rentable');
            $table->string('status', 20)->default('pending');
            $table->string('provider_transaction_id')->nullable()->unique();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('watchlists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_profile_id')->constrained()->cascadeOnDelete();
            $table->morphs('watchable');
            $table->timestamps();
            $table->unique(['user_profile_id', 'watchable_type', 'watchable_id'], 'watchlist_profile_title_unique');
        });

        Schema::create('watch_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_profile_id')->constrained()->cascadeOnDelete();
            $table->morphs('watchable');
            $table->unsignedInteger('progress_seconds')->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->boolean('completed')->default(false);
            $table->timestamp('last_watched_at')->nullable();
            $table->timestamps();
            $table->unique(['user_profile_id', 'watchable_type', 'watchable_id'], 'watch_history_profile_title_unique');
            $table->index(['user_id', 'last_watched_at']);
        });

        Schema::create('ratings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_profile_id')->constrained()->cascadeOnDelete();
            $table->morphs('rateable');
            $table->unsignedSmallInteger('rating'); // Validate 1..10 at API boundary.
            $table->timestamps();
            $table->unique(['user_profile_id', 'rateable_type', 'rateable_id'], 'ratings_profile_title_unique');
        });

        Schema::create('stream_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_asset_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        foreach (['stream_sessions', 'ratings', 'watch_history', 'watchlists',
                  'rental_entitlements', 'subscriptions', 'user_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
