<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_upload_transfers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('source_id')->unique();
            $table->unsignedBigInteger('uploader_id');
            $table->string('workflow', 10);
            $table->string('filename');
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('chunk_bytes');
            $table->unsignedBigInteger('received_bytes')->default(0);
            $table->json('chunk_hashes');
            $table->unsignedBigInteger('ingest_id')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_upload_transfers');
    }
};
