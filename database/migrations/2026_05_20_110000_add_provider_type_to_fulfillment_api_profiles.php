<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fulfillment_api_profiles', function (Blueprint $table) {
            $table->string('provider_type', 32)->default('iget')->after('network');
        });
    }

    public function down(): void
    {
        Schema::table('fulfillment_api_profiles', function (Blueprint $table) {
            $table->dropColumn('provider_type');
        });
    }
};
