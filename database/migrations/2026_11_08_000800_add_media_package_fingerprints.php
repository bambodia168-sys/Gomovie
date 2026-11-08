<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_publication_reviews', function (Blueprint $table): void {
            $table->char('package_sha256',64)->nullable();
        });
        Schema::table('media_publication_batches', function (Blueprint $table): void {
            $table->char('package_sha256',64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('media_publication_batches', fn (Blueprint $table) => $table->dropColumn('package_sha256'));
        Schema::table('media_publication_reviews', fn (Blueprint $table) => $table->dropColumn('package_sha256'));
    }
};
