<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_preferences', function (Blueprint $table): void {
            $table->string('engine', 16)->default('gomovie');
            $table->text('ad_tag_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('player_preferences', function (Blueprint $table): void {
            $table->dropColumn(['engine', 'ad_tag_url']);
        });
    }
};
