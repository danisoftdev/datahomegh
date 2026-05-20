<?php

return [

    /*
    |--------------------------------------------------------------------------
    | External fulfillment (data packages)
    |--------------------------------------------------------------------------
    |
    | provider_type on each API profile selects the client:
    | - iget: Telecel (bundleType, X-API-Key)
    | - geonet: MTN (network_key, Bearer token)
    |
    | geonet MTN network_key and iget Telecel bundleType are fixed from config, not bundle labels.
    |
    */
    'fulfillment' => [
        'providers' => [
            'iget' => [
                'default_base_url' => env('FULFILLMENT_IGET_BASE_URL', 'https://iget.onrender.com'),
            ],
            'geonet' => [
                'default_base_url' => env('FULFILLMENT_GEONET_BASE_URL', 'https://send.geonettech.com/api'),
            ],
        ],
        'fallback_codes' => [
            'iget' => [
                'TELECEL' => env('FULFILLMENT_IGET_TELECEL_BUNDLE_TYPE', 'Telecel-5959'),
            ],
            'geonet' => [
                'MTN' => env('FULFILLMENT_GEONET_MTN_NETWORK_KEY', 'YELLO'),
            ],
        ],
    ],

];
