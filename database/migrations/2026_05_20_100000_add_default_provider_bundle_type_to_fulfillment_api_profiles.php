<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fulfillment_api_profiles', function (Blueprint $table) {
            $table->string('default_provider_bundle_type', 120)->nullable()->after('api_key');
        });
    }

    public function down(): void
    {
        Schema::table('fulfillment_api_profiles', function (Blueprint $table) {
            $table->dropColumn('default_provider_bundle_type');
        });
    }
};
