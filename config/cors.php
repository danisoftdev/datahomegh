<?php

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    /*
    | Production: restrict to your SPA / site origin — not "*".
    | Override with FRONTEND_URL in .env (e.g. https://datahomegh.shop).
    */
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', 'https://datahomegh.shop')))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
