<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/middleware/Auth.php';
require_once dirname(__DIR__).'/helpers/Request.php';
require_once dirname(__DIR__).'/services/Support.php';

final class OrderApi
{
    private const INDEX_LIMIT = 10;

    public static function index(PDO $pdo): void
    {
        $user = api_require_auth($pdo);
        $slug = $user['role_slug'] ?? '';

        if ($slug === ROLE_SUPPLIER) {
            Response::error('Use GET /orders/all for full order list', 400);
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $offset = ($page - 1) * self::INDEX_LIMIT;

        if ($slug === ROLE_BUYER) {
            $st = $pdo->prepare(
                'SELECT o.* FROM orders o WHERE o.user_id = ? ORDER BY o.created_at DESC LIMIT '.self::INDEX_LIMIT.' OFFSET '.$offset
            );
            $st->execute([(int) $user['id']]);
            Response::success($st->fetchAll(PDO::FETCH_ASSOC));

            return;
        }

        if ($slug === ROLE_AGENT) {
            $st = $pdo->prepare(
                'SELECT o.* FROM orders o WHERE o.agent_id = ? ORDER BY o.created_at DESC LIMIT '.self::INDEX_LIMIT.' OFFSET '.$offset
            );
            $st->execute([(int) $user['id']]);
            Response::success($st->fetchAll(PDO::FETCH_ASSOC));

            return;
        }

        Response::error('Forbidden', 403);
    }

    public static function indexAll(PDO $pdo): void
    {
        $user = api_require_auth($pdo);
        api_require_role($user, ROLE_SUPPLIER);

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(200, max(1, (int) ($_GET['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;

        $status = trim((string) ($_GET['status'] ?? ''));
        $network = trim((string) ($_GET['network'] ?? ''));
        $phone = trim((string) ($_GET['phone'] ?? ''));
        $userId = (int) ($_GET['user_id'] ?? 0);
        $from = trim((string) ($_GET['from'] ?? $_GET['date_from'] ?? ''));
        $to = trim((string) ($_GET['to'] ?? $_GET['date_to'] ?? ''));

        $sql = 'SELECT o.* FROM orders o WHERE 1=1';
        $params = [];

        if ($status !== '' && in_array($status, ['PENDING', 'PROCESSING', 'SENT', 'FAILED', 'REFUNDED'], true)) {
            $sql .= ' AND o.status = ?';
            $params[] = $status;
        }

        if ($network !== '' && in_array($network, ['MTN', 'Telecel', 'AirtelTigo'], true)) {
            $sql .= ' AND o.network = ?';
            $params[] = $network;
        }

        if ($phone !== '') {
            $sql .= ' AND o.phone_number LIKE ?';
            $params[] = '%'.$phone.'%';
        }

        if ($userId > 0) {
            $sql .= ' AND o.user_id = ?';
            $params[] = $userId;
        }

        if ($from !== '') {
            $sql .= ' AND DATE(o.created_at) >= ?';
            $params[] = $from;
        }

        if ($to !== '') {
            $sql .= ' AND DATE(o.created_at) <= ?';
            $params[] = $to;
        }

        $sql .= ' ORDER BY o.created_at DESC LIMIT '.$perPage.' OFFSET '.$offset;

        $st = $pdo->prepare($sql);
        $st->execute($params);

        Response::success($st->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function show(PDO $pdo, int $id): void
    {
        $user = api_require_auth($pdo);
        $order = self::fetchOrder($pdo, $id);
        if ($order === null) {
            Response::error('Not found', 404);
        }

        self::authorizeOrderAccess($pdo, $user, $order);

        $hist = $pdo->prepare(
            'SELECT h.*, u.username AS changed_by_username FROM order_status_history h
             INNER JOIN users u ON u.id = h.changed_by AND u.deleted_at IS NULL
             WHERE h.order_id = ?
             ORDER BY h.created_at ASC, h.id ASC'
        );
        $hist->execute([$id]);
        $historyRows = $hist->fetchAll(PDO::FETCH_ASSOC);

        if (($user['role_slug'] ?? '') === ROLE_BUYER) {
            $historyRows = array_values(array_filter($historyRows, fn ($r) => ! empty($r['visible_to_buyer'])));
        }

        Response::success([
            'order' => $order,
            'history' => $historyRows,
        ]);
    }

    public static function store(PDO $pdo): void
    {
        $user = api_require_auth($pdo);
        api_require_role($user, ROLE_BUYER);

        $body = api_json_body();
        $networkRaw = trim((string) ($body['network'] ?? ''));
        $phone = trim((string) ($body['phone_number'] ?? ''));
        $bundlePackageId = (int) ($body['bundle_id'] ?? $body['bundle_package_id'] ?? 0);
        $confirm = filter_var($body['confirm'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! $confirm) {
            Response::error('confirm must be true', 422);
        }

        if (! in_array($networkRaw, ['MTN', 'MTN_AFA', 'Telecel', 'AirtelTigo'], true)) {
            Response::error('Invalid network', 422);
        }

        $network = $networkRaw === 'MTN_AFA' ? 'MTN' : $networkRaw;

        if (! preg_match('/^0\d{9}$/', $phone)) {
            Response::error('phone_number must be 10 digits starting with 0', 422);
        }

        if ($bundlePackageId < 1) {
            Response::error('bundle_id required', 422);
        }

        try {
            $orderId = self::placeOrder($pdo, $user, $network, $phone, $bundlePackageId, $body);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }

        Response::success(self::fetchOrder($pdo, $orderId), 'Order placed', 201);
    }

    public static function patchStatus(PDO $pdo, int $id): void
    {
        $user = api_require_auth($pdo);
        $order = self::fetchOrder($pdo, $id);
        if ($order === null) {
            Response::error('Not found', 404);
        }

        self::authorizeStaffOrder($pdo, $user, $order);

        $body = api_json_body();
        $newStatus = (string) ($body['status'] ?? '');
        $note = isset($body['note']) ? htmlspecialchars(trim((string) $body['note']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : null;
        $visible = filter_var($body['visible_to_buyer'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! in_array($newStatus, ['PROCESSING', 'SENT', 'FAILED', 'REFUNDED'], true)) {
            Response::error('Invalid status', 422);
        }

        try {
            self::updateOrderStatus($pdo, $order, $newStatus, (int) $user['id'], $note, $visible);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }

        Response::success(self::fetchOrder($pdo, $id), 'Updated');
    }

    public static function addNote(PDO $pdo, int $id): void
    {
        $user = api_require_auth($pdo);
        $order = self::fetchOrder($pdo, $id);
        if ($order === null) {
            Response::error('Not found', 404);
        }

        self::authorizeStaffOrder($pdo, $user, $order);

        $body = api_json_body();
        $note = htmlspecialchars(trim((string) ($body['note'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $visible = filter_var($body['visible_to_buyer'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($note === '') {
            Response::error('note required', 422);
        }

        $status = (string) $order['status'];

        $pdo->prepare(
            'INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, note, visible_to_buyer, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        )->execute([$id, $status, $status, (int) $user['id'], $note, $visible ? 1 : 0]);

        if ($visible) {
            api_notify_user($pdo, (int) $order['user_id'], 'Order update', 'A note was added to your order #'.$id.'.', 'order_note');
        }

        Response::success(null, 'Note added');
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function fetchOrder(PDO $pdo, int $id): ?array
    {
        $st = $pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @param  array<string, mixed>  $user
     * @param  array<string, mixed>  $order
     */
    private static function authorizeOrderAccess(PDO $pdo, array $user, array $order): void
    {
        $slug = $user['role_slug'] ?? '';

        if ($slug === ROLE_SUPPLIER) {
            return;
        }

        if ($slug === ROLE_BUYER && (int) $order['user_id'] === (int) $user['id']) {
            return;
        }

        if ($slug === ROLE_AGENT) {
            $buyerId = (int) $order['user_id'];
            $st = $pdo->prepare('SELECT agent_id FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
            $st->execute([$buyerId]);
            $agentId = $st->fetchColumn();
            if ($agentId !== false && (int) $agentId === (int) $user['id']) {
                return;
            }
        }

        Response::error('Forbidden', 403);
    }

    /**
     * @param  array<string, mixed>  $user
     * @param  array<string, mixed>  $order
     */
    private static function authorizeStaffOrder(PDO $pdo, array $user, array $order): void
    {
        $slug = $user['role_slug'] ?? '';

        if ($slug === ROLE_SUPPLIER) {
            return;
        }

        if ($slug === ROLE_AGENT) {
            $buyerId = (int) $order['user_id'];
            $st = $pdo->prepare('SELECT agent_id FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
            $st->execute([$buyerId]);
            $agentId = $st->fetchColumn();
            if ($agentId !== false && (int) $agentId === (int) $user['id']) {
                return;
            }
        }

        Response::error('Forbidden', 403);
    }

    /**
     * @param  array<string, mixed>  $buyer
     * @param  array<string, mixed>  $body
     */
    private static function placeOrder(PDO $pdo, array $buyer, string $network, string $phone, int $bundlePackageId, array $body = []): int
    {
        $uid = (int) $buyer['id'];

        $pdo->beginTransaction();

        try {
            $st = $pdo->prepare(
                'SELECT u.*, r.slug AS role_slug FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = ? FOR UPDATE'
            );
            $st->execute([$uid]);
            $u = $st->fetch(PDO::FETCH_ASSOC);
            if ($u === false || ($u['status'] ?? '') !== 'active') {
                throw new RuntimeException('Your account must be active to place orders.');
            }

            if (! empty($u['wallet_frozen'])) {
                throw new RuntimeException('Your wallet is frozen.');
            }

            $wst = $pdo->prepare('SELECT * FROM wallets WHERE user_id = ? FOR UPDATE');
            $wst->execute([$uid]);
            $wallet = $wst->fetch(PDO::FETCH_ASSOC);
            if ($wallet === false || ! empty($wallet['is_frozen'])) {
                throw new RuntimeException('Your wallet is not available or is frozen.');
            }

            $dup = $pdo->prepare(
                'SELECT 1 FROM orders WHERE user_id = ? AND phone_number = ? AND network = ? AND bundle_package_id = ?
                 AND status IN (\'PENDING\', \'PROCESSING\') LIMIT 1'
            );
            $dup->execute([$uid, $phone, $network, $bundlePackageId]);
            if ($dup->fetchColumn() !== false) {
                throw new RuntimeException('You already have a pending or processing order for this number, network, and bundle.');
            }

            $limit = $u['daily_order_limit'] ?? null;
            if ($limit !== null && (int) $limit > 0) {
                $c = $pdo->prepare(
                    'SELECT COUNT(*) FROM orders WHERE user_id = ? AND DATE(created_at) = CURDATE()'
                );
                $c->execute([$uid]);
                if ((int) $c->fetchColumn() >= (int) $limit) {
                    throw new RuntimeException('Daily order limit reached.');
                }
            }

            $bst = $pdo->prepare('SELECT * FROM bundle_packages WHERE id = ? FOR UPDATE');
            $bst->execute([$bundlePackageId]);
            $bundle = $bst->fetch(PDO::FETCH_ASSOC);
            if ($bundle === false) {
                throw new RuntimeException('Bundle not found.');
            }

            if (empty($bundle['is_available']) || (int) $bundle['stock_count'] < 1) {
                throw new RuntimeException('This bundle is not available or is out of stock.');
            }

            if (($bundle['network'] ?? '') !== $network) {
                throw new RuntimeException('Network does not match selected bundle.');
            }

            $packageKind = $bundle['package_kind'] ?? 'data';
            $afaJson = null;
            if ($packageKind === 'mtn_afa') {
                $afaJson = self::validateAfaRegistrationJson($body['afa_registration'] ?? null);
            } elseif (isset($body['afa_registration']) && $body['afa_registration'] !== null && $body['afa_registration'] !== []) {
                throw new RuntimeException('afa_registration is only for MTN AFA bundles.');
            }

            $price = api_resolve_price($pdo, $u, $bundle);

            if (bccomp((string) $wallet['balance'], $price, 2) < 0) {
                throw new RuntimeException('Insufficient wallet balance.');
            }

            $agentId = $u['agent_id'] ?? null;

            $pendingLedgerRef = 'PENDING_'.api_uuid_v4();
            api_wallet_debit_in_tx($pdo, $uid, $price, 'ORDER', $pendingLedgerRef, 'Order placement');

            $pdo->prepare(
                'INSERT INTO orders (user_id, agent_id, network, phone_number, afa_registration, bundle_package_id, amount, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, \'PENDING\', NOW(), NOW())'
            )->execute([
                $uid,
                $agentId !== null ? (int) $agentId : null,
                $network,
                $phone,
                $afaJson,
                $bundlePackageId,
                $price,
            ]);

            $orderId = (int) $pdo->lastInsertId();

            $lr = $pdo->prepare(
                'UPDATE wallet_ledger SET reference = ?, note = ? WHERE user_id = ? AND reference = ? AND source = ? LIMIT 1'
            );
            $lr->execute([(string) $orderId, 'Order #'.$orderId, $uid, $pendingLedgerRef, 'ORDER']);
            if ($lr->rowCount() !== 1) {
                throw new RuntimeException('Ledger reference update failed.');
            }

            $pdo->prepare(
                'INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, note, visible_to_buyer, created_at)
                 VALUES (?, NULL, \'PENDING\', ?, NULL, 1, NOW())'
            )->execute([$orderId, $uid]);

            $bstk = $pdo->prepare(
                'UPDATE bundle_packages SET stock_count = stock_count - 1,
                 is_available = IF(stock_count - 1 >= 1, 1, 0), updated_at = NOW()
                 WHERE id = ? AND stock_count >= 1'
            );
            $bstk->execute([$bundlePackageId]);
            if ($bstk->rowCount() !== 1) {
                throw new RuntimeException('Bundle stock could not be reserved.');
            }

            $pdo->commit();

            api_notify_user(
                $pdo,
                $uid,
                'Order received',
                'Your order #'.$orderId.' for '.$bundle['name'].' ('.$network.') was received and is pending.',
                'order_received'
            );

            return $orderId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function validateAfaRegistrationJson(mixed $ar): string
    {
        if (! is_array($ar)) {
            throw new RuntimeException('afa_registration object is required for this bundle.');
        }

        $fields = ['name', 'phone', 'ghana_card_number', 'date_of_birth', 'occupation', 'location'];
        $out = [];
        foreach ($fields as $f) {
            $v = isset($ar[$f]) ? trim((string) $ar[$f]) : '';
            if ($v === '') {
                throw new RuntimeException('afa_registration.'.$f.' is required.');
            }
            $out[$f] = $v;
        }

        if (! preg_match('/^0[235]\d{8}$/', $out['phone'])) {
            throw new RuntimeException('afa_registration.phone must be a valid Ghana mobile number.');
        }

        $ts = strtotime($out['date_of_birth']);
        if ($ts === false) {
            throw new RuntimeException('afa_registration.date_of_birth is invalid.');
        }
        if ($ts >= strtotime('today')) {
            throw new RuntimeException('afa_registration.date_of_birth must be before today.');
        }

        try {
            return json_encode($out, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new RuntimeException('Could not encode registration payload.');
        }
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private static function updateOrderStatus(
        PDO $pdo,
        array $order,
        string $newStatus,
        int $changedBy,
        ?string $note,
        bool $visibleToBuyer
    ): void {
        $orderId = (int) $order['id'];
        $old = (string) $order['status'];

        if (in_array($old, ['SENT', 'REFUNDED'], true)) {
            throw new RuntimeException('Cannot change status from '.$old.'.');
        }

        self::assertStatusTransition($old, $newStatus);

        if ($old === $newStatus) {
            throw new RuntimeException('New status is the same as the current status.');
        }

        $pdo->beginTransaction();

        try {
            $ost = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $ost->execute([$orderId]);
            $locked = $ost->fetch(PDO::FETCH_ASSOC);
            if ($locked === false) {
                throw new RuntimeException('Order not found.');
            }

            $old = (string) $locked['status'];

            if ($newStatus === 'REFUNDED') {
                api_wallet_credit_in_tx(
                    $pdo,
                    (int) $locked['user_id'],
                    (string) $locked['amount'],
                    'ORDER_REFUND',
                    (string) $orderId,
                    'Refund for order #'.$orderId
                );

                $pdo->prepare('UPDATE bundle_packages SET stock_count = stock_count + 1, updated_at = NOW() WHERE id = ?')->execute([(int) $locked['bundle_package_id']]);
            }

            $pdo->prepare('UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$newStatus, $orderId]);

            $pdo->prepare(
                'INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, note, visible_to_buyer, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())'
            )->execute([$orderId, $old, $newStatus, $changedBy, $note, $visibleToBuyer ? 1 : 0]);

            $pdo->commit();

            api_notify_user(
                $pdo,
                (int) $locked['user_id'],
                'Order status updated',
                'Your order #'.$orderId.' is now '.$newStatus.'.',
                'order_status'
            );
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function assertStatusTransition(string $from, string $to): void
    {
        $allowed = [
            'PENDING' => ['PROCESSING', 'FAILED', 'REFUNDED'],
            'PROCESSING' => ['SENT', 'FAILED', 'REFUNDED'],
            'FAILED' => ['REFUNDED'],
        ];

        if (! in_array($to, $allowed[$from] ?? [], true)) {
            throw new RuntimeException("Cannot transition from {$from} to {$to}.");
        }
    }
}
