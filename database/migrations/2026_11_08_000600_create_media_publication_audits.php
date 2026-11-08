<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_publication_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_ingest_id')->unique()->constrained('media_ingests')->restrictOnDelete();
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->string('rights_reference', 180);
            $table->string('scan_report_reference', 180);
            $table->string('quality_report_reference', 180);
            $table->char('manifest_sha256', 64);
            $table->string('status', 20)->default('reviewed')->index();
            $table->timestamp('reviewed_at');
            $table->timestamps();
        });

        Schema::create('media_publication_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_ingest_id')->unique()->constrained('media_ingests')->restrictOnDelete();
            $table->foreignId('media_publication_review_id')->constrained('media_publication_reviews')->restrictOnDelete();
            $table->foreignId('staged_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('staging')->index();
            $table->string('object_prefix', 512);
            $table->string('manifest_key', 1024);
            $table->char('manifest_sha256', 64);
            $table->unsignedInteger('file_count')->default(0);
            $table->string('failure_code', 64)->nullable();
            $table->timestamp('staged_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_publication_batches');
        Schema::dropIfExists('media_publication_reviews');
    }
};
