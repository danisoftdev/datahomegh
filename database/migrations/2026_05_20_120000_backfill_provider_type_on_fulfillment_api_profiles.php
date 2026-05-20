<?php

use App\Support\FulfillmentProviderType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('fulfillment_api_profiles')
            ->where('network', 'MTN')
            ->update(['provider_type' => FulfillmentProviderType::GEONET]);

        DB::table('fulfillment_api_profiles')
            ->where('network', 'Telecel')
            ->update(['provider_type' => FulfillmentProviderType::IGET]);
    }

    public function down(): void
    {
        // no-op: provider_type values are business data
    }
};
