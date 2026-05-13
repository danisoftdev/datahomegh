<?php

declare(strict_types=1);

require_once __DIR__.'/env.php';

/**
 * @return PDO
 */
function api_db()
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = api_env('DB_HOST', '127.0.0.1');
    $port = api_env('DB_PORT', '3306');
    $database = api_env('DB_DATABASE', 'u123456789_datahomegh');
    $username = api_env('DB_USERNAME', 'root');
    $password = api_env('DB_PASSWORD', '');

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $host,
        $port,
        $database
    );

    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
