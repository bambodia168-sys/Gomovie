<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_ingests', function (Blueprint $table): void {
            $table->string('security_scan_status', 20)->default('pending')->index();
            $table->string('rights_review_status', 20)->default('pending')->index();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('encoded_manifest_key', 1024)->nullable();
            $table->timestamp('encoded_at')->nullable();
            $table->string('pipeline_error', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('media_ingests', function (Blueprint $table): void {
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'security_scan_status', 'rights_review_status', 'approved_at',
                'approved_by', 'encoded_manifest_key', 'encoded_at', 'pipeline_error',
            ]);
        });
    }
};
