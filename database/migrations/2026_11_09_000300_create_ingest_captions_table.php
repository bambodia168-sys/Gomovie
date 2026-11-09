<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_ingest_captions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_ingest_id')->constrained('media_ingests')->cascadeOnDelete();
            $table->string('language', 12);
            $table->string('label', 80);
            $table->string('disk', 24)->default('local');
            $table->string('object_key', 255);
            $table->string('sha256', 64);
            $table->unsignedInteger('size_bytes');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['media_ingest_id', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_ingest_captions');
    }
};
