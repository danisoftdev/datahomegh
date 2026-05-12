<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bundle_package_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_prices');
    }
};
