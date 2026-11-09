<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('studio_typography', function (Blueprint $table) {
            $table->string('heading_variant',16)->default('default');
            $table->string('body_variant',16)->default('default');
            $table->string('button_variant',16)->default('default');
        });
    }

    public function down(): void
    {
        Schema::table('studio_typography', function (Blueprint $table) {
            $table->dropColumn(['heading_variant','body_variant','button_variant']);
        });
    }
};
