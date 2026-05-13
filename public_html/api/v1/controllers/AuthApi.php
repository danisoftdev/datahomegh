<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/middleware/Auth.php';
require_once dirname(__DIR__).'/helpers/Request.php';
require_once dirname(__DIR__).'/services/Support.php';

final class AuthApi
{
    public static function login(PDO $pdo): void
    {
        api_login_rate_limit_prune_and_check($pdo);

        $body = api_json_body();
        $username = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($username === '' || $password === '') {
            Response::error('Username and password are required', 422);
        }

        $st = $pdo->prepare(
            'SELECT u.*, r.slug AS role_slug FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.username = ? AND u.deleted_at IS NULL LIMIT 1'
        );
        $st->execute([$username]);
        $user = $st->fetch(PDO::FETCH_ASSOC);

        if ($user === false || ! password_verify($password, (string) $user['password'])) {
            api_login_rate_limit_record_failure($pdo);
            Response::error('Invalid credentials', 401);
        }

        if (($user['status'] ?? '') !== 'active') {
            Response::error('Account is not active', 403);
        }

        api_login_rate_limit_clear($pdo);

        $plain = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plain);
        $expires = (new DateTimeImmutable('+30 days'))->format('Y-m-d H:i:s');

        $pdo->prepare(
            'INSERT INTO api_tokens (user_id, token_hash, name, expires_at, last_used_at, created_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())'
        )->execute([(int) $user['id'], $hash, 'api', $expires]);

        unset($user['password'], $user['remember_token']);

        Response::success([
            'token' => $plain,
            'token_type' => 'Bearer',
            'expires_at' => $expires,
            'user' => $user,
        ], 'OK');
    }

    public static function register(PDO $pdo): void
    {
        $body = api_json_body();

        $username = trim((string) ($body['username'] ?? ''));
        $name = htmlspecialchars(trim((string) ($body['name'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $phone = trim((string) ($body['phone'] ?? ''));
        $password = (string) ($body['password'] ?? '');
        $passwordConfirmation = (string) ($body['password_confirmation'] ?? $body['confirm_password'] ?? '');
        $email = isset($body['email']) ? trim((string) $body['email']) : '';
        $agentSlug = isset($body['agent_slug']) ? trim((string) $body['agent_slug']) : '';

        if ($username === '' || $name === '' || $phone === '' || strlen($password) < 8) {
            Response::error('Validation failed: username, name, phone, password (min 8) required', 422);
        }

        if ($password !== $passwordConfirmation) {
            Response::error('Password confirmation does not match', 422);
        }

        if (! preg_match('/^0\d{9}$/', $phone)) {
            Response::error('Phone must be 10 digits starting with 0', 422);
        }

        $st = $pdo->prepare('SELECT id FROM users WHERE username = ? AND deleted_at IS NULL LIMIT 1');
        $st->execute([$username]);
        if ($st->fetchColumn() !== false) {
            Response::error('Username already taken', 422);
        }

        if ($email !== '') {
            $st = $pdo->prepare('SELECT id FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1');
            $st->execute([$email]);
            if ($st->fetchColumn() !== false) {
                Response::error('Email already taken', 422);
            }
        }

        $roleSt = $pdo->query("SELECT id FROM roles WHERE slug = 'buyer' LIMIT 1");
        $buyerRoleId = $roleSt ? $roleSt->fetchColumn() : false;
        if ($buyerRoleId === false) {
            Response::error('Buyer role missing in database', 500);
        }

        $agentId = null;
        if ($agentSlug !== '') {
            $ast = $pdo->prepare(
                'SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id
                 WHERE u.shop_slug = ? AND u.status = ? AND r.slug = ? AND u.deleted_at IS NULL LIMIT 1'
            );
            $ast->execute([$agentSlug, 'active', ROLE_AGENT]);
            $agentId = $ast->fetchColumn();
            if ($agentId === false) {
                Response::error('Invalid agent_slug', 422);
            }
            $agentId = (int) $agentId;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $pdo->beginTransaction();

        try {
            $pdo->prepare(
                'INSERT INTO users (username, name, email, phone, password, role_id, agent_id, status, wallet_frozen, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())'
            )->execute([
                $username,
                $name,
                $email !== '' ? $email : null,
                $phone,
                $hash,
                (int) $buyerRoleId,
                $agentId,
                'pending',
            ]);

            $uid = (int) $pdo->lastInsertId();

            $pdo->prepare('INSERT INTO wallets (user_id, balance, is_frozen, created_at, updated_at) VALUES (?, 0, 0, NOW(), NOW())')->execute([$uid]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            Response::error('Registration failed', 500);
        }

        api_notify_suppliers(
            $pdo,
            'New buyer registration',
            "{$name} (@{$username}) registered and is awaiting approval.",
            'buyer_registered'
        );

        if ($agentId !== null) {
            api_notify_user(
                $pdo,
                $agentId,
                'New buyer registration',
                "{$name} (@{$username}) registered and is awaiting approval.",
                'buyer_registered'
            );
        }

        $user = api_find_user($pdo, $uid);
        Response::success(['user' => $user], 'Registration submitted. Await approval.', 201);
    }

    public static function logout(PDO $pdo): void
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (! preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
            Response::error('Unauthorized', 401);
        }

        $token = trim($m[1]);
        $hash = hash('sha256', $token);

        $pdo->prepare('DELETE FROM api_tokens WHERE token_hash = ?')->execute([$hash]);

        Response::success(null, 'Logged out');
    }

    public static function passwordResetRequest(PDO $pdo): void
    {
        $body = api_json_body();
        $username = trim((string) ($body['username'] ?? ''));

        if ($username === '') {
            Response::error('Username is required', 422);
        }

        $st = $pdo->prepare('SELECT id FROM users WHERE username = ? AND deleted_at IS NULL LIMIT 1');
        $st->execute([$username]);
        $uid = $st->fetchColumn();

        if ($uid === false) {
            Response::error('Unknown username', 404);
        }

        $uid = (int) $uid;

        $pdo->beginTransaction();

        try {
            $pdo->prepare(
                'UPDATE password_reset_codes SET used_at = NOW()
                 WHERE user_id = ? AND used_at IS NULL AND code_hash IS NOT NULL AND expires_at <= NOW()'
            )->execute([$uid]);

            $check = $pdo->prepare(
                'SELECT 1 FROM password_reset_codes WHERE user_id = ? AND used_at IS NULL AND code_hash IS NULL LIMIT 1'
            );
            $check->execute([$uid]);
            if ($check->fetchColumn() !== false) {
                $pdo->rollBack();
                Response::success(null, 'You already have a pending reset request. Please contact the admin.');
            }

            $check = $pdo->prepare(
                'SELECT 1 FROM password_reset_codes WHERE user_id = ? AND used_at IS NULL AND code_hash IS NOT NULL AND expires_at > NOW() LIMIT 1'
            );
            $check->execute([$uid]);
            if ($check->fetchColumn() !== false) {
                $pdo->rollBack();
                Response::success(null, 'You already have an active reset code.');
            }

            $pdo->prepare(
                'INSERT INTO password_reset_codes (user_id, code_hash, expires_at, used_at, attempts, created_at)
                 VALUES (?, NULL, NULL, NULL, 0, NOW())'
            )->execute([$uid]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            Response::error('Could not submit request', 500);
        }

        api_notify_suppliers(
            $pdo,
            'Password reset requested',
            "{$username} has requested a password reset.",
            'password_reset_requested'
        );

        Response::success(null, 'Request submitted. Please contact admin now.');
    }

    public static function passwordReset(PDO $pdo): void
    {
        $body = api_json_body();
        $username = trim((string) ($body['username'] ?? ''));
        $code = strtoupper(trim((string) ($body['reset_code'] ?? $body['code'] ?? '')));
        $newPassword = (string) ($body['new_password'] ?? '');
        $confirm = (string) ($body['confirm_password'] ?? $body['new_password_confirmation'] ?? '');

        if ($username === '' || $code === '' || strlen($newPassword) < 8) {
            Response::error('username, reset_code, new_password (min 8) required', 422);
        }

        if ($newPassword !== $confirm) {
            Response::error('Password confirmation mismatch', 422);
        }

        $st = $pdo->prepare(
            'SELECT u.*, r.slug AS role_slug FROM users u INNER JOIN roles r ON r.id = u.role_id
             WHERE u.username = ? AND u.deleted_at IS NULL LIMIT 1'
        );
        $st->execute([$username]);
        $user = $st->fetch(PDO::FETCH_ASSOC);

        if ($user === false) {
            Response::error('Unknown username', 404);
        }

        $uid = (int) $user['id'];

        $rst = $pdo->prepare(
            'SELECT * FROM password_reset_codes WHERE user_id = ? AND used_at IS NULL AND code_hash IS NOT NULL ORDER BY id DESC LIMIT 1'
        );
        $rst->execute([$uid]);
        $rec = $rst->fetch(PDO::FETCH_ASSOC);

        if ($rec === false) {
            Response::error('No active reset code', 400);
        }

        $expiresAt = $rec['expires_at'] ?? null;
        if ($expiresAt === null || strtotime((string) $expiresAt) <= time()) {
            Response::error('Reset code expired', 400);
        }

        if ((int) $rec['attempts'] >= 3) {
            Response::error('Too many attempts', 429);
        }

        $expectedHash = (string) $rec['code_hash'];
        $actualHash = hash('sha256', $code);

        if (! hash_equals($expectedHash, $actualHash)) {
            $pdo->prepare('UPDATE password_reset_codes SET attempts = attempts + 1 WHERE id = ?')->execute([(int) $rec['id']]);
            $attempts = (int) $rec['attempts'] + 1;
            if ($attempts >= 3) {
                Response::error('Too many invalid attempts', 429);
            }
            Response::error('Invalid reset code', 400);
        }

        $pdo->beginTransaction();

        try {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?')->execute([$hash, $uid]);
            $pdo->prepare('UPDATE password_reset_codes SET used_at = NOW() WHERE id = ?')->execute([(int) $rec['id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            Response::error('Reset failed', 500);
        }

        $plain = bin2hex(random_bytes(32));
        $tokHash = hash('sha256', $plain);
        $expires = (new DateTimeImmutable('+30 days'))->format('Y-m-d H:i:s');

        $pdo->prepare(
            'INSERT INTO api_tokens (user_id, token_hash, name, expires_at, last_used_at, created_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())'
        )->execute([$uid, $tokHash, 'api', $expires]);

        unset($user['password'], $user['remember_token']);

        Response::success([
            'token' => $plain,
            'token_type' => 'Bearer',
            'expires_at' => $expires,
            'user' => $user,
            'message' => 'Password reset successfully.',
        ], 'Password reset successfully.', 200);
    }
}
