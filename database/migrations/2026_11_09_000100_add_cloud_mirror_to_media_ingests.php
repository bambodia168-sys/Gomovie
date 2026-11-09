<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_ingests', function (Blueprint $table): void {
            $table->string('cloud_disk', 20)->nullable();
            $table->string('cloud_object_key', 1024)->nullable();
            $table->string('cloud_status', 20)->default('local_only')->index();
            $table->timestamp('cloud_mirrored_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('media_ingests', function (Blueprint $table): void {
            $table->dropColumn([
                'cloud_disk', 'cloud_object_key', 'cloud_status', 'cloud_mirrored_at',
            ]);
        });
    }
};
