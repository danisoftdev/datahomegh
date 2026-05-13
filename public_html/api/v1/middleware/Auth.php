<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/helpers/Response.php';

/**
 * Authenticate via Authorization: Bearer {token}. Updates api_tokens.last_used_at.
 *
 * @return array<string, mixed>|null User row with role_slug, or null
 */
function api_auth_user(PDO $pdo): ?array
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

    if (! preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
        return null;
    }

    $token = trim($m[1]);
    if ($token === '') {
        return null;
    }

    $hash = hash('sha256', $token);

    $sql = <<<'SQL'
        SELECT u.*, r.slug AS role_slug
        FROM api_tokens t
        INNER JOIN users u ON u.id = t.user_id AND u.deleted_at IS NULL
        INNER JOIN roles r ON r.id = u.role_id
        WHERE t.token_hash = :hash
          AND (t.expires_at IS NULL OR t.expires_at > NOW())
        LIMIT 1
        SQL;

    $st = $pdo->prepare($sql);
    $st->execute(['hash' => $hash]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if ($row === false) {
        return null;
    }

    $upd = $pdo->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE token_hash = ?');
    $upd->execute([$hash]);

    return $row;
}

/**
 * @return array<string, mixed>
 */
function api_require_auth(PDO $pdo): array
{
    $user = api_auth_user($pdo);
    if ($user === null) {
        Response::error('Unauthorized', 401);
    }

    return $user;
}

function api_require_role(array $user, string ...$slugs): void
{
    $slug = $user['role_slug'] ?? '';

    foreach ($slugs as $s) {
        if ($slug === $s) {
            return;
        }
    }

    Response::error('Forbidden', 403);
}
