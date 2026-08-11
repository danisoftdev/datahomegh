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
    | - skanka5: MTN/Telecel/AirtelTigo (x-api-key, POST /orders with network_id + volume_mb)
    |
    | geonet / iget product codes are fixed from config. Encarta uses bundle_id per package or catalogue match.
    | Skanka5 uses network_id from GET /fetch-networks and volume_mb from size label.
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
            'skanka5' => [
                'default_base_url' => env('FULFILLMENT_SKANKA5_BASE_URL', 'https://agent.skanka5.com/api/v1'),
                'webhook_secret' => env('FULFILLMENT_SKANKA5_WEBHOOK_SECRET'),
            ],
        ],
        'fallback_codes' => [
            'iget' => [
                'TELECEL' => env('FULFILLMENT_IGET_TELECEL_BUNDLE_TYPE', 'Telecel-5959'),
            ],
            'geonet' => [
                'MTN' => env('FULFILLMENT_GEONET_MTN_NETWORK_KEY', 'YELLO'),
            ],
            'skanka5' => [
                'MTN' => env('FULFILLMENT_SKANKA5_MTN_NETWORK_ID'),
                'TELECEL' => env('FULFILLMENT_SKANKA5_TELECEL_NETWORK_ID'),
                'AIRTELTIGO' => env('FULFILLMENT_SKANKA5_AIRTELTIGO_NETWORK_ID'),
            ],
        ],
    ],

];
