<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/config/env.php';

final class Response
{
    /**
     * Escape string values for JSON clients that may embed API text in HTML (XSS mitigation).
     */
    public static function sanitizeStringsForJson(mixed $data): mixed
    {
        if (is_string($data)) {
            return htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        if (! is_array($data)) {
            return $data;
        }

        $out = [];
        foreach ($data as $k => $v) {
            $out[$k] = self::sanitizeStringsForJson($v);
        }

        return $out;
    }

    public static function json_response(array $payload, int $code = 200): never
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
        echo json_encode(self::sanitizeStringsForJson($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        exit;
    }

    public static function success(mixed $data = null, string $msg = 'OK', int $code = 200): never
    {
        self::json_response([
            'success' => true,
            'message' => $msg,
            'data' => $data,
        ], $code);
    }

    public static function error(string $msg, int $code = 400): never
    {
        self::json_response([
            'success' => false,
            'message' => $msg,
        ], $code);
    }
}
