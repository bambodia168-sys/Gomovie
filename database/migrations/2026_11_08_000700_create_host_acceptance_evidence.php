<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('host_acceptance_evidence', function (Blueprint $table): void {
            $table->id();
            $table->string('target', 20);
            $table->string('control', 80);
            $table->string('status', 20)->default('submitted');
            $table->string('evidence_reference', 200);
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['target', 'control']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('host_acceptance_evidence');
    }
};
