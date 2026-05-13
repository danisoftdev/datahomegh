<?php

declare(strict_types=1);

function api_cors_headers(): void
{
    $origin = api_env('API_CORS_ORIGIN', 'https://datahomegh.shop');
    header('Access-Control-Allow-Origin: '.$origin);
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept, X-Requested-With');
    header('Access-Control-Max-Age: 86400');
}

function api_handle_options_preflight(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'OPTIONS') {
        return;
    }

    api_cors_headers();
    http_response_code(200);
    exit;
}
