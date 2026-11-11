<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive, disabled-by-default private resumable intake.
 * Active sessions contain only chunks, never approved playable media.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resumable_upload_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('original_name', 255);
            $table->unsignedBigInteger('total_bytes');
            $table->unsignedBigInteger('received_bytes')->default(0);
            $table->unsignedInteger('chunk_size')->default(1048576);
            $table->string('status', 24)->default('uploading');
            $table->foreignId('movie_id')->nullable()->constrained('movies')->nullOnDelete();
            $table->foreignId('media_ingest_id')->nullable()->constrained('media_ingests')->nullOnDelete();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->index(['uploaded_by', 'status', 'expires_at'], 'resumable_uploads_owner_status_idx');
        });

        Schema::create('resumable_upload_chunks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('session_id');
            $table->unsignedBigInteger('offset');
            $table->unsignedInteger('bytes');
            $table->string('sha256', 64);
            $table->timestamps();
            $table->foreign('session_id')->references('id')
                ->on('resumable_upload_sessions')->cascadeOnDelete();
            $table->unique(['session_id', 'offset'], 'resumable_chunk_offset_unique');
        });

        Schema::table('media_ingests', function (Blueprint $table): void {
            $table->uuid('resumable_upload_session_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('media_ingests', function (Blueprint $table): void {
            $table->dropUnique(['resumable_upload_session_id']);
            $table->dropColumn('resumable_upload_session_id');
        });
        Schema::dropIfExists('resumable_upload_chunks');
        Schema::dropIfExists('resumable_upload_sessions');
    }
};
