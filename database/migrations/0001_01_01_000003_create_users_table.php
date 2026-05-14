<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique();
            $table->string('name', 100);
            $table->string('email')->nullable()->unique();
            $table->string('phone', 20);
            $table->string('password');
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('shop_slug')->nullable()->unique();
            $table->string('shop_name')->nullable();
            $table->string('profile_picture')->nullable();
            $table->string('logo')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('whatsapp_channel')->nullable();
            $table->text('business_description')->nullable();
            $table->enum('status', ['pending', 'active', 'held', 'declined', 'deleted'])->default('pending');
            $table->boolean('wallet_frozen')->default(false);
            $table->unsignedInteger('daily_order_limit')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
