<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bundle_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->enum('network', ['MTN', 'Telecel', 'AirtelTigo']);
            $table->string('name');
            $table->string('size_label');
            $table->decimal('internal_cost', 10, 2);
            $table->unsignedInteger('stock_count')->default(0);
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_packages');
    }
};
