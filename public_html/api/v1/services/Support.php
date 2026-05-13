<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/helpers/Response.php';
require_once dirname(__DIR__).'/config/env.php';

const ROLE_SUPPLIER = 'supplier';
const ROLE_AGENT = 'agent';
const ROLE_BUYER = 'buyer';

function api_uuid_v4(): string
{
    $b = random_bytes(16);
    $b[6] = chr(ord($b[6]) & 0x0F | 0x40);
    $b[8] = chr(ord($b[8]) & 0x3F | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

/**
 * @return array<string, mixed>|null
 */
function api_find_user(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare(
        'SELECT u.*, r.slug AS role_slug FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.deleted_at IS NULL LIMIT 1'
    );
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

function api_slug_unique(PDO $pdo, string $base, ?int $exceptId = null): string
{
    $slug = $base !== '' ? $base : 'shop';
    $i = 2;

    while (true) {
        $q = 'SELECT 1 FROM users WHERE shop_slug = ? AND deleted_at IS NULL';
        $params = [$slug];
        if ($exceptId !== null) {
            $q .= ' AND id != ?';
            $params[] = $exceptId;
        }

        $st = $pdo->prepare($q.' LIMIT 1');
        $st->execute($params);
        if ($st->fetchColumn() === false) {
            return $slug;
        }
        $slug = $base.'-'.$i;
        $i++;
    }
}

/**
 * @param  array<string, mixed>  $buyer
 * @param  array<string, mixed>  $bundle
 */
function api_resolve_price(PDO $pdo, array $buyer, array $bundle): string
{
    $roleId = (int) $buyer['role_id'];
    $bundleId = (int) $bundle['id'];

    $st = $pdo->prepare(
        'SELECT price FROM role_prices WHERE role_id = ? AND bundle_package_id = ? LIMIT 1'
    );
    $st->execute([$roleId, $bundleId]);
    $p = $st->fetchColumn();
    if ($p !== false) {
        return api_money((string) $p);
    }

    $agentId = $buyer['agent_id'] ?? null;
    if ($agentId !== null) {
        $st = $pdo->prepare(
            'SELECT price FROM resale_plans WHERE bundle_package_id = ? AND agent_id = ? AND is_active = 1 LIMIT 1'
        );
        $st->execute([$bundleId, (int) $agentId]);
        $p = $st->fetchColumn();
        if ($p !== false) {
            return api_money((string) $p);
        }
    }

    return api_money((string) $bundle['internal_cost']);
}

function api_money(string $amount): string
{
    if (! is_numeric($amount)) {
        Response::error('Invalid amount', 500);
    }

    return bcadd($amount, '0', 2);
}

/**
 * Credit wallet inside an already-open transaction (no nested begin/commit).
 */
function api_wallet_credit_in_tx(
    PDO $pdo,
    int $userId,
    string $amount,
    string $source,
    ?string $ref = null,
    ?string $note = null
): void {
    $amount = api_money($amount);

    $st = $pdo->prepare('SELECT id, balance, is_frozen FROM wallets WHERE user_id = ? FOR UPDATE');
    $st->execute([$userId]);
    $w = $st->fetch(PDO::FETCH_ASSOC);
    if ($w === false) {
        throw new RuntimeException('Wallet not found');
    }

    if ((int) $w['is_frozen'] === 1) {
        throw new RuntimeException('Wallet is frozen');
    }

    $before = api_money((string) $w['balance']);

    $upd = $pdo->prepare(
        'UPDATE wallets SET balance = balance + ?, updated_at = NOW() WHERE user_id = ? AND is_frozen = 0'
    );
    $upd->execute([$amount, $userId]);
    if ($upd->rowCount() !== 1) {
        throw new RuntimeException('Wallet update failed');
    }

    $st2 = $pdo->prepare('SELECT balance FROM wallets WHERE user_id = ? LIMIT 1');
    $st2->execute([$userId]);
    $after = api_money((string) $st2->fetchColumn());

    $pdo->prepare(
        'INSERT INTO wallet_ledger (user_id, type, amount, balance_before, balance_after, source, reference, note, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    )->execute([
        $userId,
        'CREDIT',
        $amount,
        $before,
        $after,
        $source,
        $ref,
        $note,
    ]);
}

function api_wallet_credit(
    PDO $pdo,
    int $userId,
    string $amount,
    string $source,
    ?string $ref = null,
    ?string $note = null
): void {
    $pdo->beginTransaction();

    try {
        api_wallet_credit_in_tx($pdo, $userId, $amount, $source, $ref, $note);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Debit wallet inside an open transaction using UPDATE … balance >= ? (rowCount must be 1) + ledger row.
 */
function api_wallet_debit_in_tx(
    PDO $pdo,
    int $userId,
    string $amount,
    string $source,
    ?string $ref = null,
    ?string $note = null
): void {
    $amount = api_money($amount);

    $st = $pdo->prepare('SELECT balance, is_frozen FROM wallets WHERE user_id = ? FOR UPDATE');
    $st->execute([$userId]);
    $w = $st->fetch(PDO::FETCH_ASSOC);
    if ($w === false) {
        throw new RuntimeException('Wallet not found');
    }

    if ((int) $w['is_frozen'] === 1) {
        throw new RuntimeException('Wallet is frozen');
    }

    $before = api_money((string) $w['balance']);

    $upd = $pdo->prepare(
        'UPDATE wallets SET balance = balance - ?, updated_at = NOW() WHERE user_id = ? AND is_frozen = 0 AND balance >= ?'
    );
    $upd->execute([$amount, $userId, $amount]);
    if ($upd->rowCount() !== 1) {
        throw new RuntimeException('Insufficient wallet balance or wallet unavailable');
    }

    $st2 = $pdo->prepare('SELECT balance FROM wallets WHERE user_id = ? LIMIT 1');
    $st2->execute([$userId]);
    $after = api_money((string) $st2->fetchColumn());

    $pdo->prepare(
        'INSERT INTO wallet_ledger (user_id, type, amount, balance_before, balance_after, source, reference, note, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    )->execute([
        $userId,
        'DEBIT',
        $amount,
        $before,
        $after,
        $source,
        $ref,
        $note,
    ]);
}

function api_wallet_debit(
    PDO $pdo,
    int $userId,
    string $amount,
    string $source,
    ?string $ref = null,
    ?string $note = null
): void {
    $pdo->beginTransaction();

    try {
        api_wallet_debit_in_tx($pdo, $userId, $amount, $source, $ref, $note);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function api_notify_user(PDO $pdo, int $userId, string $title, string $message, string $type): void
{
    $pdo->prepare(
        'INSERT INTO notifications (user_id, broadcast_group_id, broadcast_role_slugs, title, message, type, is_read, created_at)
         VALUES (?, NULL, NULL, ?, ?, ?, 0, NOW())'
    )->execute([$userId, $title, $message, $type]);
}

function api_notify_suppliers(PDO $pdo, string $title, string $message, string $type): void
{
    $st = $pdo->query(
        "SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.slug = 'supplier' AND u.deleted_at IS NULL"
    );
    while (($id = $st->fetchColumn()) !== false) {
        api_notify_user($pdo, (int) $id, $title, $message, $type);
    }
}

/**
 * @param  list<string>  $roles
 */
function api_broadcast_notifications(PDO $pdo, string $title, string $message, array $roles, string $type): void
{
    $groupId = api_uuid_v4();
    $rolesJson = $roles === [] ? null : json_encode(array_values($roles), JSON_THROW_ON_ERROR);

    $pdo->beginTransaction();

    try {
        $pdo->prepare(
            'INSERT INTO notifications (user_id, broadcast_group_id, broadcast_role_slugs, title, message, type, is_read, created_at)
             VALUES (NULL, ?, ?, ?, ?, ?, 0, NOW())'
        )->execute([$groupId, $rolesJson, $title, $message, $type]);

        if ($roles !== []) {
            $placeholders = implode(',', array_fill(0, count($roles), '?'));
            $sql = "SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id AND r.slug IN ($placeholders) AND u.deleted_at IS NULL";
            $st = $pdo->prepare($sql);
            $st->execute($roles);

            $ins = $pdo->prepare(
                'INSERT INTO notifications (user_id, broadcast_group_id, broadcast_role_slugs, title, message, type, is_read, created_at)
                 VALUES (?, ?, NULL, ?, ?, ?, 0, NOW())'
            );

            while (($uid = $st->fetchColumn()) !== false) {
                $ins->execute([(int) $uid, $groupId, $title, $message, $type]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * @param  array<string, mixed>  $verifyData
 */
function api_paystack_metadata_user_id(array $verifyData): ?int
{
    $meta = $verifyData['metadata'] ?? null;
    if (is_string($meta)) {
        $decoded = json_decode($meta, true);
        $meta = is_array($decoded) ? $decoded : null;
    }

    if (is_array($meta) && isset($meta['user_id'])) {
        return (int) $meta['user_id'];
    }

    return null;
}

function api_paystack_amount_ghs(array $verifyData): string
{
    $pesewas = (int) ($verifyData['amount'] ?? 0);

    return number_format($pesewas / 100, 2, '.', '');
}

/**
 * @return array<string, mixed>
 */
function api_paystack_verify_remote(string $reference): array
{
    $secret = api_env('PAYSTACK_SECRET_KEY', '');
    $base = rtrim(api_env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co'), '/');

    if ($secret === '') {
        Response::error('Paystack is not configured', 503);
    }

    $url = $base.'/transaction/verify/'.rawurlencode($reference);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer '.$secret,
            'Accept: application/json',
        ],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $code >= 400) {
        Response::error('Paystack verify failed', 502);
    }

    try {
        $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        Response::error('Invalid Paystack response', 502);
    }

    if (empty($json['status'])) {
        Response::error((string) ($json['message'] ?? 'Verification failed'), 400);
    }

    return is_array($json['data'] ?? null) ? $json['data'] : [];
}

/**
 * @param  array<string, mixed>  $userRow
 * @return array{authorization_url: string, reference: string}
 */
function api_paystack_initialize(array $userRow, float $amount): array
{
    $secret = api_env('PAYSTACK_SECRET_KEY', '');
    $base = rtrim(api_env('PAYSTACK_PAYMENT_URL', 'https://api.paystack.co'), '/');

    if ($secret === '') {
        Response::error('Paystack is not configured', 503);
    }

    $reference = uniqid('dhgh_', true);
    $amountPesewas = (int) round($amount * 100);
    $email = ! empty($userRow['email']) ? $userRow['email'] : ($userRow['username'].'@users.datahomegh.local');

    $callbackBase = rtrim(api_env('APP_URL', 'http://localhost'), '/');
    $callbackUrl = api_env('PAYSTACK_CALLBACK_URL', $callbackBase.'/wallet/topup/callback');

    $payload = json_encode([
        'email' => $email,
        'amount' => $amountPesewas,
        'currency' => 'GHS',
        'reference' => $reference,
        'callback_url' => $callbackUrl,
        'metadata' => [
            'user_id' => (int) $userRow['id'],
            'type' => 'wallet_topup',
        ],
    ], JSON_THROW_ON_ERROR);

    $ch = curl_init($base.'/transaction/initialize');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer '.$secret,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);

    $body = curl_exec($ch);
    curl_close($ch);

    if ($body === false) {
        Response::error('Paystack initialize failed', 502);
    }

    try {
        $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        Response::error('Invalid Paystack response', 502);
    }

    if (empty($json['status'])) {
        Response::error((string) ($json['message'] ?? 'Initialize failed'), 400);
    }

    $data = $json['data'] ?? [];

    return [
        'authorization_url' => (string) ($data['authorization_url'] ?? ''),
        'reference' => (string) ($data['reference'] ?? $reference),
    ];
}

function api_login_rate_limit_endpoint(): string
{
    return 'POST:auth/login';
}

function api_login_client_ip(): string
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));

    return $ip !== '' ? $ip : '0.0.0.0';
}

/**
 * Enforce max 5 failed login windows per IP per rolling minute (see rate_limits table).
 */
function api_login_rate_limit_prune_and_check(PDO $pdo): void
{
    $ip = api_login_client_ip();
    $endpoint = api_login_rate_limit_endpoint();
    $now = date('Y-m-d H:i:s');

    $pdo->beginTransaction();

    try {
        $sel = $pdo->prepare('SELECT * FROM rate_limits WHERE ip = ? AND endpoint = ? FOR UPDATE');
        $sel->execute([$ip, $endpoint]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            $ins = $pdo->prepare(
                'INSERT INTO rate_limits (ip, endpoint, attempts, last_attempt) VALUES (?, ?, 0, ?)'
            );
            $ins->execute([$ip, $endpoint, $now]);
            $pdo->commit();

            return;
        }

        $last = strtotime((string) ($row['last_attempt'] ?? '')) ?: 0;
        if (time() - $last >= 60) {
            $pdo->prepare(
                'UPDATE rate_limits SET attempts = 0, last_attempt = ? WHERE id = ?'
            )->execute([$now, (int) $row['id']]);
            $row['attempts'] = '0';
        }

        if ((int) ($row['attempts'] ?? 0) >= 5) {
            $pdo->commit();
            Response::error('Too many login attempts. Try again in a minute.', 429);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();

        throw $e;
    }
}

function api_login_rate_limit_record_failure(PDO $pdo): void
{
    $ip = api_login_client_ip();
    $endpoint = api_login_rate_limit_endpoint();
    $now = date('Y-m-d H:i:s');
    $upd = $pdo->prepare(
        'UPDATE rate_limits SET attempts = attempts + 1, last_attempt = ? WHERE ip = ? AND endpoint = ?'
    );
    $upd->execute([$now, $ip, $endpoint]);
}

function api_login_rate_limit_clear(PDO $pdo): void
{
    $ip = api_login_client_ip();
    $endpoint = api_login_rate_limit_endpoint();
    $pdo->prepare('DELETE FROM rate_limits WHERE ip = ? AND endpoint = ?')->execute([$ip, $endpoint]);
}
