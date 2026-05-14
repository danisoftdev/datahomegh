<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bundle_packages', function (Blueprint $table) {
            $table->string('package_kind', 32)->default('data');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->json('afa_registration')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bundle_packages', function (Blueprint $table) {
            $table->dropColumn('package_kind');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('afa_registration');
        });
    }
};
