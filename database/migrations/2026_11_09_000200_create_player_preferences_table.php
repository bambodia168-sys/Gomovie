<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_preferences', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('accent', 16)->default('crimson');
            $table->string('density', 16)->default('comfortable');
            $table->unsignedTinyInteger('seek_seconds')->default(10);
            $table->unsignedTinyInteger('auto_hide_seconds')->default(4);
            $table->boolean('show_volume')->default(true);
            $table->boolean('show_speed')->default(true);
            $table->boolean('show_captions')->default(true);
            $table->boolean('show_pip')->default(true);
            $table->boolean('show_fullscreen')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_preferences');
    }
};
