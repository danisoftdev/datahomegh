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
    | - encarta: MTN (X-API-Key, POST /purchase with bundle_id from GET /bundles)
    |
    | geonet / iget product codes are fixed from config. Encarta uses bundle_id per package or catalogue match.
    |
    */
    'agent_shop' => [
        'wallet_cutoff_ghs' => (float) env('AGENT_SHOP_WALLET_CUTOFF_GHS', 5),
        'withdrawal_min_amount' => (float) env('AGENT_WITHDRAWAL_MIN_GHS', 10),
        'withdrawal_fee_ghs' => (float) env('AGENT_WITHDRAWAL_FEE_GHS', 0),
    ],

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
                'place_path' => env('FULFILLMENT_ENCARTA_PLACE_PATH', '/purchase'),
                'webhook_secret' => env('FULFILLMENT_ENCARTA_WEBHOOK_SECRET'),
                'webhook_url' => env('FULFILLMENT_ENCARTA_WEBHOOK_URL'),
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
