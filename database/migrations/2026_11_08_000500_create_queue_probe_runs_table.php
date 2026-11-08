<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_probe_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('queue_name', 64)->index();
            $table->timestamp('dispatched_at');
            $table->timestamp('processed_at')->nullable()->index();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_probe_runs');
    }
};
