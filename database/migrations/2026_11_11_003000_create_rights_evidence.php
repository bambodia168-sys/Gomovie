<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rights_evidence', function (Blueprint $table): void {
            $table->id();
            $table->string('licensable_type', 16);
            $table->unsignedBigInteger('licensable_id');
            $table->string('rights_reference', 180)->unique();
            $table->string('rights_holder', 180);
            $table->string('license_scope', 32)->default('global');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('document_key', 255);
            $table->char('document_sha256', 64);
            $table->unsignedBigInteger('document_bytes');
            $table->string('document_mime', 64);
            $table->timestamp('document_scanned_at');
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 16)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_reference', 180)->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason', 255)->nullable();
            $table->timestamps();
            $table->index(['licensable_type', 'licensable_id', 'status'], 'rights_evidence_title_status');
            $table->index(['status', 'ends_at'], 'rights_evidence_expiry');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('rights_evidence');
    }
};
