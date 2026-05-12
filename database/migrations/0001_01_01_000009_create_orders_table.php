<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->enum('network', ['MTN', 'Telecel', 'AirtelTigo']);
            $table->string('phone_number', 20);
            $table->foreignId('bundle_package_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['PENDING', 'PROCESSING', 'SENT', 'FAILED', 'REFUNDED'])->default('PENDING');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
