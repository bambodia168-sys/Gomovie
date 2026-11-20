<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('two_factor_secret')->nullable();
            $table->uuid('two_factor_version')->nullable();
            $table->unsignedBigInteger('two_factor_last_step')->nullable();
            $table->json('two_factor_recovery_hashes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_version',
                'two_factor_last_step',
                'two_factor_recovery_hashes',
            ]);
        });
    }
};
