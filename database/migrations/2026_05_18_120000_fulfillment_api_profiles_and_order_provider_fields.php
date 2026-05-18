<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillment_api_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('network', 32);
            $table->string('name', 120);
            $table->string('base_url', 512);
            $table->text('api_key');
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index(['supplier_user_id', 'network']);
            $table->index(['supplier_user_id', 'network', 'is_active'], 'fa_profiles_supplier_net_active');
        });

        Schema::table('bundle_packages', function (Blueprint $table) {
            $table->string('provider_bundle_type', 120)->nullable()->after('size_label');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('provider_order_reference', 191)->nullable()->after('status');
            $table->string('provider_status', 64)->nullable()->after('provider_order_reference');
            $table->timestamp('provider_status_synced_at')->nullable()->after('provider_status');
            $table->foreignId('fulfillment_api_profile_id')->nullable()->after('provider_status_synced_at')
                ->constrained('fulfillment_api_profiles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['fulfillment_api_profile_id']);
            $table->dropColumn([
                'fulfillment_api_profile_id',
                'provider_status_synced_at',
                'provider_status',
                'provider_order_reference',
            ]);
        });

        Schema::table('bundle_packages', function (Blueprint $table) {
            $table->dropColumn('provider_bundle_type');
        });

        Schema::dropIfExists('fulfillment_api_profiles');
    }
};
