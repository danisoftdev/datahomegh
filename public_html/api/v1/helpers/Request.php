<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/helpers/Response.php';

/**
 * JSON request body per API contract: json_decode(file_get_contents('php://input'), true).
 *
 * @return array<string, mixed>
 */
function api_json_body(): array
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw === false || $raw === '' ? '' : $raw, true);

    if ($raw !== false && $raw !== '' && ! is_array($decoded)) {
        Response::error('Invalid JSON body', 400);
    }

    return is_array($decoded) ? $decoded : [];
}
