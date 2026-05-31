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
    | - encarta: MTN (X-API-Key, POST /ishare or /purchase)
    |
    | geonet / encarta / iget product codes are fixed from config, not bundle labels.
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
            'encarta' => [
                'default_base_url' => env('FULFILLMENT_ENCARTA_BASE_URL', 'https://encartastores.com/api'),
                'place_path' => env('FULFILLMENT_ENCARTA_PLACE_PATH', '/ishare'),
                'status_path' => env('FULFILLMENT_ENCARTA_STATUS_PATH', '/ishare-status'),
            ],
        ],
        'fallback_codes' => [
            'iget' => [
                'TELECEL' => env('FULFILLMENT_IGET_TELECEL_BUNDLE_TYPE', 'Telecel-5959'),
            ],
            'geonet' => [
                'MTN' => env('FULFILLMENT_GEONET_MTN_NETWORK_KEY', 'YELLO'),
            ],
            'encarta' => [
                'MTN' => env('FULFILLMENT_ENCARTA_MTN_NETWORK', 'MTN'),
            ],
        ],
    ],

];
