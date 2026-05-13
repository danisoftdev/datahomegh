<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_limits', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45);
            $table->string('endpoint', 128);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempt')->nullable();

            $table->unique(['ip', 'endpoint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_limits');
    }
};
