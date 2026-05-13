<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/middleware/Auth.php';
require_once dirname(__DIR__).'/helpers/Request.php';
require_once dirname(__DIR__).'/services/Support.php';

final class AdminApi
{
    /**
     * @param  array<string, mixed>  $user
     */
    private static function requireSupplier(array $user): void
    {
        if (($user['role_slug'] ?? '') !== ROLE_SUPPLIER) {
            Response::error('Forbidden', 403);
        }
    }

    public static function usersIndex(PDO $pdo): void
    {
        $user = api_require_auth($pdo);
        self::requireSupplier($user);

        $role = $_GET['role'] ?? '';
        $status = $_GET['status'] ?? '';
        $search = trim((string) ($_GET['search'] ?? ''));

        $sql = 'SELECT u.*, r.slug AS role_slug, r.name AS role_name FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                WHERE u.deleted_at IS NULL AND r.slug IN (\'agent\', \'buyer\')';
        $params = [];

        if ($role !== '' && in_array($role, [ROLE_AGENT, ROLE_BUYER], true)) {
            $sql .= ' AND r.slug = ?';
            $params[] = $role;
        }

        if ($status !== '') {
            $sql .= ' AND u.status = ?';
            $params[] = $status;
        }

        if ($search !== '') {
            $sql .= ' AND (u.username LIKE ? OR u.name LIKE ? OR u.phone LIKE ? OR u.shop_name LIKE ?)';
            $s = '%'.$search.'%';
            array_push($params, $s, $s, $s, $s);
        }

        $sql .= ' ORDER BY u.id DESC LIMIT 200';

        $st = $pdo->prepare($sql);
        $st->execute($params);

        Response::success($st->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function approveUser(PDO $pdo, int $id): void
    {
        $actor = api_require_auth($pdo);
        self::requireSupplier($actor);

        if ($id === (int) $actor['id']) {
            Response::error('Cannot approve yourself', 403);
        }

        $target = api_find_user($pdo, $id);
        if ($target === null) {
            Response::error('Not found', 404);
        }

        $roleSlug = (string) ($target['role_slug'] ?? '');
        if (! in_array($roleSlug, [ROLE_AGENT, ROLE_BUYER], true)) {
            Response::error('User cannot be approved via this action', 422);
        }

        if (($target['status'] ?? '') !== 'pending') {
            Response::error('User is not pending approval', 422);
        }

        if ($roleSlug === ROLE_AGENT) {
            $shopName = trim((string) ($target['shop_name'] ?: $target['name']));
            if ($shopName === '') {
                Response::error('Agent must have shop name or display name', 422);
            }

            $slug = api_slug_unique($pdo, self::slugify($shopName), $id);
            $finalShopName = trim((string) ($target['shop_name'] ?? '')) !== '' ? trim((string) $target['shop_name']) : $shopName;

            $pdo->prepare(
                'UPDATE users SET status = ?, shop_slug = ?, shop_name = ?, updated_at = NOW() WHERE id = ?'
            )->execute(['active', $slug, $finalShopName, $id]);

            api_notify_user($pdo, $id, 'Account approved', 'Your agent account is active. Your shop slug is '.$slug.'.', 'account_approved');

            Response::success(['shop_slug' => $slug], 'Agent approved');

            return;
        }

        $pdo->prepare('UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?')->execute(['active', $id]);
        api_notify_user($pdo, $id, 'Account approved', 'Your account is now active.', 'account_approved');

        Response::success(null, 'User approved');
    }

    public static function freezeUser(PDO $pdo, int $id): void
    {
        $actor = api_require_auth($pdo);
        self::requireSupplier($actor);

        if ($id === (int) $actor['id']) {
            Response::error('Cannot freeze yourself', 403);
        }

        $target = api_find_user($pdo, $id);
        if ($target === null) {
            Response::error('Not found', 404);
        }

        if (! in_array(($target['role_slug'] ?? ''), [ROLE_AGENT, ROLE_BUYER], true)) {
            Response::error('Cannot freeze this user', 403);
        }

        $pdo->prepare('UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?')->execute(['held', $id]);

        Response::success(null, 'User placed on hold');
    }

    public static function destroyUser(PDO $pdo, int $id): void
    {
        $actor = api_require_auth($pdo);
        self::requireSupplier($actor);

        if ($id === (int) $actor['id']) {
            Response::error('Cannot delete yourself', 403);
        }

        $target = api_find_user($pdo, $id);
        if ($target === null) {
            Response::error('Not found', 404);
        }

        if (! in_array(($target['role_slug'] ?? ''), [ROLE_AGENT, ROLE_BUYER], true)) {
            Response::error('Cannot delete this user', 403);
        }

        $pdo->prepare('UPDATE users SET deleted_at = NOW(), updated_at = NOW() WHERE id = ?')->execute([$id]);

        Response::success(null, 'User removed');
    }

    public static function walletCredit(PDO $pdo): void
    {
        $actor = api_require_auth($pdo);
        self::requireSupplier($actor);

        $body = api_json_body();
        $uid = (int) ($body['user_id'] ?? 0);
        $amount = $body['amount'] ?? null;
        $note = isset($body['note']) ? htmlspecialchars(trim((string) $body['note']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : null;

        if ($uid < 1 || ! is_numeric($amount)) {
            Response::error('user_id and amount required', 422);
        }

        $amountStr = api_money((string) $amount);
        if (bccomp($amountStr, '0.01', 2) < 0) {
            Response::error('Invalid amount', 422);
        }

        $ref = 'admin_credit_'.uniqid('', true);

        $pdo->beginTransaction();

        try {
            api_wallet_credit_in_tx($pdo, $uid, $amountStr, 'ADMIN_CREDIT', $ref, $note);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            Response::error($e->getMessage(), 400);
        }

        Response::success(['reference' => $ref], 'Wallet credited');
    }

    public static function walletDebit(PDO $pdo): void
    {
        $actor = api_require_auth($pdo);
        self::requireSupplier($actor);

        $body = api_json_body();
        $uid = (int) ($body['user_id'] ?? 0);
        $amount = $body['amount'] ?? null;
        $note = isset($body['note']) ? htmlspecialchars(trim((string) $body['note']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : null;

        if ($uid < 1 || ! is_numeric($amount)) {
            Response::error('user_id and amount required', 422);
        }

        $amountStr = api_money((string) $amount);
        $ref = 'admin_debit_'.uniqid('', true);

        $pdo->beginTransaction();

        try {
            api_wallet_debit_in_tx($pdo, $uid, $amountStr, 'ADMIN_DEBIT', $ref, $note);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            Response::error($e->getMessage(), 400);
        }

        Response::success(['reference' => $ref], 'Wallet debited');
    }

    public static function notificationsBroadcast(PDO $pdo): void
    {
        $actor = api_require_auth($pdo);
        self::requireSupplier($actor);

        $body = api_json_body();
        $title = htmlspecialchars(trim((string) ($body['title'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $message = htmlspecialchars(trim((string) ($body['message'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $type = trim((string) ($body['type'] ?? 'broadcast'));
        $targetRoles = $body['target_roles'] ?? [];

        if ($title === '' || $message === '') {
            Response::error('title and message required', 422);
        }

        if (! is_array($targetRoles)) {
            Response::error('target_roles must be an array', 422);
        }

        $roles = [];
        foreach ($targetRoles as $r) {
            $r = (string) $r;
            if (in_array($r, [ROLE_BUYER, ROLE_AGENT, ROLE_SUPPLIER], true)) {
                $roles[] = $r;
            }
        }
        $roles = array_values(array_unique($roles));
        $rolesJson = $roles === [] ? null : json_encode($roles, JSON_THROW_ON_ERROR);

        try {
            $pdo->prepare(
                'INSERT INTO notifications (user_id, broadcast_group_id, broadcast_role_slugs, title, message, type, is_read, created_at)
                 VALUES (NULL, NULL, ?, ?, ?, ?, 0, NOW())'
            )->execute([$rolesJson, $title, $message, $type !== '' ? $type : 'broadcast']);
        } catch (Throwable $e) {
            Response::error('Broadcast failed', 500);
        }

        Response::success(null, 'Broadcast created');
    }

    public static function rolesIndex(PDO $pdo): void
    {
        $user = api_require_auth($pdo);
        self::requireSupplier($user);

        $st = $pdo->query('SELECT r.* FROM roles r ORDER BY r.name');
        $roles = $st->fetchAll(PDO::FETCH_ASSOC);

        $pst = $pdo->query(
            'SELECT rp.role_id, p.id AS permission_id, p.slug, p.name
             FROM role_permission rp
             INNER JOIN permissions p ON p.id = rp.permission_id'
        );
        $byRole = [];
        while ($row = $pst->fetch(PDO::FETCH_ASSOC)) {
            $rid = (int) $row['role_id'];
            $byRole[$rid][] = [
                'id' => (int) $row['permission_id'],
                'slug' => $row['slug'],
                'name' => $row['name'],
            ];
        }

        foreach ($roles as &$r) {
            $r['permissions'] = $byRole[(int) $r['id']] ?? [];
        }
        unset($r);

        Response::success($roles);
    }

    public static function rolesStore(PDO $pdo): void
    {
        $user = api_require_auth($pdo);
        self::requireSupplier($user);

        $body = api_json_body();
        $name = htmlspecialchars(trim((string) ($body['name'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $slug = trim((string) ($body['slug'] ?? ''));
        $permissionIds = $body['permission_ids'] ?? [];

        if ($name === '' || $slug === '') {
            Response::error('name and slug required', 422);
        }

        if (! is_array($permissionIds)) {
            Response::error('permission_ids must be an array', 422);
        }

        $slug = strtolower(preg_replace('/[^a-z0-9\-]+/i', '-', $slug));

        $chk = $pdo->prepare('SELECT 1 FROM roles WHERE slug = ? LIMIT 1');
        $chk->execute([$slug]);
        if ($chk->fetchColumn() !== false) {
            Response::error('Slug already exists', 422);
        }

        $pdo->beginTransaction();

        try {
            $pdo->prepare('INSERT INTO roles (name, slug, is_enabled, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())')->execute([$name, $slug]);
            $roleId = (int) $pdo->lastInsertId();

            self::syncRolePermissions($pdo, $roleId, $permissionIds);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            Response::error($e->getMessage(), 400);
        }

        Response::success(['id' => $roleId], 'Role created', 201);
    }

    public static function rolesUpdate(PDO $pdo, int $id): void
    {
        $user = api_require_auth($pdo);
        self::requireSupplier($user);

        $body = api_json_body();
        $name = isset($body['name']) ? htmlspecialchars(trim((string) $body['name']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : null;
        $enabled = $body['is_enabled'] ?? null;

        $row = $pdo->prepare('SELECT * FROM roles WHERE id = ? LIMIT 1');
        $row->execute([$id]);
        $role = $row->fetch(PDO::FETCH_ASSOC);
        if ($role === false) {
            Response::error('Not found', 404);
        }

        if (($role['slug'] ?? '') === ROLE_SUPPLIER && $enabled === false) {
            Response::error('Cannot disable supplier role', 422);
        }

        $pdo->beginTransaction();

        try {
            if ($name !== null && $name !== '') {
                $pdo->prepare('UPDATE roles SET name = ?, updated_at = NOW() WHERE id = ?')->execute([$name, $id]);
            }

            if ($enabled !== null) {
                $pdo->prepare('UPDATE roles SET is_enabled = ?, updated_at = NOW() WHERE id = ?')->execute([filter_var($enabled, FILTER_VALIDATE_BOOLEAN) ? 1 : 0, $id]);
            }

            if (array_key_exists('permission_ids', $body)) {
                $pids = $body['permission_ids'];
                if (! is_array($pids)) {
                    throw new RuntimeException('permission_ids must be an array');
                }
                self::syncRolePermissions($pdo, $id, $pids);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            Response::error($e->getMessage(), 400);
        }

        Response::success(['id' => $id], 'Role updated');
    }

    /**
     * @param  list<mixed>  $permissionIds
     */
    private static function syncRolePermissions(PDO $pdo, int $roleId, array $permissionIds): void
    {
        $ids = [];
        foreach ($permissionIds as $pid) {
            $pid = (int) $pid;
            if ($pid > 0) {
                $ids[] = $pid;
            }
        }
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            $pdo->prepare('DELETE FROM role_permission WHERE role_id = ?')->execute([$roleId]);

            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $chk = $pdo->prepare('SELECT COUNT(*) FROM permissions WHERE id IN ('.$placeholders.')');
        $chk->execute($ids);
        if ((int) $chk->fetchColumn() !== count($ids)) {
            throw new RuntimeException('One or more permission_ids are invalid');
        }

        $pdo->prepare('DELETE FROM role_permission WHERE role_id = ?')->execute([$roleId]);

        $ins = $pdo->prepare('INSERT INTO role_permission (role_id, permission_id) VALUES (?, ?)');
        foreach ($ids as $pid) {
            $ins->execute([$roleId, $pid]);
        }
    }

    private static function slugify(string $shopName): string
    {
        $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $shopName), '-'));

        return $s !== '' ? $s : 'shop';
    }
}
