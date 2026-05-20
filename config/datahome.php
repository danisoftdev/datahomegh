<?php

return [

    /*
    |--------------------------------------------------------------------------
    | External fulfillment (data packages)
    |--------------------------------------------------------------------------
    |
    | When a bundle has no "Provider bundle code" and the active API profile
    | has no default code either, data orders (non-AFA) can use these as
    | bundleType for the provider API. Set empty in .env to disable per network.
    |
    */
    'fulfillment' => [
        // iGet developer API host (not https://console.igetghana.com — that is the dashboard only).
        'default_provider_base_url' => env('FULFILLMENT_DEFAULT_PROVIDER_BASE_URL', 'https://iget.onrender.com'),
        'mtn_data_fallback_bundle_type' => env('FULFILLMENT_MTN_DATA_FALLBACK_BUNDLE_TYPE', 'mtnup2u'),
        'telecel_data_fallback_bundle_type' => env('FULFILLMENT_TELECEL_DATA_FALLBACK_BUNDLE_TYPE', ''),
        'airteltigo_data_fallback_bundle_type' => env('FULFILLMENT_AIRTELTIGO_DATA_FALLBACK_BUNDLE_TYPE', ''),
    ],

];
