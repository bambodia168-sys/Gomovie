<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive, nullable linkage. Historical assets stay unlinked and therefore
 * ineligible for the independently reviewed public-HLS path until reconciled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->foreignId('media_publication_review_id')->nullable()
                ->constrained('media_publication_reviews')->restrictOnDelete();
            $table->foreignId('published_by')->nullable()
                ->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('published_by');
            $table->dropConstrainedForeignId('media_publication_review_id');
        });
    }
};
