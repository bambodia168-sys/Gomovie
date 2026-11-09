<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fingerprints for publicly deliverable HLS files; never serve the raw ingest.
        Schema::create('public_media_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->string('relative_path', 255);
            $table->char('sha256', 64);
            $table->unsignedBigInteger('size_bytes');
            $table->unique(['media_asset_id', 'relative_path'], 'public_media_file_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_media_files');
    }
};
