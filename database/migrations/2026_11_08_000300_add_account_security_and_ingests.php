<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 20)->default('viewer')->index();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('media_ingests', function (Blueprint $table): void {
            $table->id();
            $table->morphs('ingestable');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('disk', 40)->default('local');
            $table->string('object_key', 1024);
            $table->string('original_name');
            $table->string('mime', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64);
            $table->string('status', 24)->default('quarantined')->index();
            $table->text('review_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('resource_type', 80)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->json('details')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamps();
            $table->index(['resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_events');
        Schema::dropIfExists('media_ingests');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
