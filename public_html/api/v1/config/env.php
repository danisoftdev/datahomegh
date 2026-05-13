<?php

declare(strict_types=1);

/**
 * Load Laravel-style .env into getenv / $_ENV (does not overwrite existing env).
 */
function api_load_env(string $path): void
{
    if (! is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (! str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($name) !== false) {
            continue;
        }

        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
    }
}

function api_env(string $key, ?string $default = null): ?string
{
    $v = $_ENV[$key] ?? getenv($key);

    return ($v === false || $v === null || $v === '') ? $default : (string) $v;
}
