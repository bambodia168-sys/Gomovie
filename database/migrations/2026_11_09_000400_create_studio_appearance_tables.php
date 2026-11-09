<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('studio_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 90)->unique();
            $table->boolean('is_custom')->default(false);
            $table->boolean('is_published')->default(false);
            $table->string('eyebrow', 120)->nullable();
            $table->string('heading', 180)->nullable();
            $table->text('description')->nullable();
            $table->text('body')->nullable();
            $table->string('seo_title', 160)->nullable();
            $table->string('seo_description', 300)->nullable();
            $table->timestamps();
        });
        Schema::create('studio_typography', function (Blueprint $table) {
            $table->id();
            $table->string('family', 30)->default('Instrument Sans');
            $table->unsignedTinyInteger('base_size')->default(16);
            $table->unsignedSmallInteger('heading_scale')->default(100);
            $table->unsignedSmallInteger('weight')->default(600);
            $table->string('custom_font_key', 180)->nullable();
            $table->string('custom_font_sha256', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_typography');
        Schema::dropIfExists('studio_pages');
    }
};
