<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/middleware/Auth.php';
require_once dirname(__DIR__).'/helpers/Request.php';
require_once dirname(__DIR__).'/services/Support.php';

final class BundleApi
{
    public static function index(PDO $pdo): void
    {
        $user = api_require_auth($pdo);
        $slug = $user['role_slug'] ?? '';
        $networkFilter = trim((string) ($_GET['network'] ?? ''));
        $networkClause = '';
        $networkParams = [];
        if ($networkFilter !== '' && in_array($networkFilter, ['MTN', 'Telecel', 'AirtelTigo'], true)) {
            $networkClause = ' AND bp.network = ?';
            $networkParams[] = $networkFilter;
        }

        if ($slug === ROLE_SUPPLIER) {
            $sql = 'SELECT bp.*, u.username AS agent_username FROM bundle_packages bp
                 LEFT JOIN users u ON u.id = bp.agent_id AND u.deleted_at IS NULL
                 WHERE 1=1'.$networkClause.'
                 ORDER BY bp.id DESC LIMIT 500';
            $st = $pdo->prepare($sql);
            $st->execute($networkParams);
            Response::success($st->fetchAll(PDO::FETCH_ASSOC));

            return;
        }

        if ($slug === ROLE_AGENT) {
            $sql = 'SELECT * FROM bundle_packages WHERE agent_id = ?';
            if ($networkFilter !== '' && in_array($networkFilter, ['MTN', 'Telecel', 'AirtelTigo'], true)) {
                $sql .= ' AND network = ?';
            }
            $sql .= ' ORDER BY id DESC LIMIT 500';
            $st = $pdo->prepare($sql);
            $params = [(int) $user['id']];
            if ($networkFilter !== '' && in_array($networkFilter, ['MTN', 'Telecel', 'AirtelTigo'], true)) {
                $params[] = $networkFilter;
            }
            $st->execute($params);
            Response::success($st->fetchAll(PDO::FETCH_ASSOC));

            return;
        }

        if ($slug === ROLE_BUYER) {
            $agentId = isset($user['agent_id']) && $user['agent_id'] !== null ? (int) $user['agent_id'] : 0;

            if ($agentId === 0) {
                $sql = 'SELECT bp.*, NULL AS resale_plan_price, NULL AS resale_plan_label
                FROM bundle_packages bp
                WHERE bp.is_available = 1 AND bp.stock_count >= 1 AND bp.agent_id IS NULL
                '.$networkClause.'
                ORDER BY bp.network, bp.name LIMIT 500';
                $st = $pdo->prepare($sql);
                $st->execute($networkParams);
            } else {
                $sql = 'SELECT bp.*, rp.price AS resale_plan_price, rp.label AS resale_plan_label
                FROM bundle_packages bp
                LEFT JOIN resale_plans rp ON rp.bundle_package_id = bp.id AND rp.agent_id = ? AND rp.is_active = 1
                WHERE bp.is_available = 1 AND bp.stock_count >= 1
                AND (bp.agent_id IS NULL OR bp.agent_id = ?)
                '.$networkClause.'
                ORDER BY bp.network, bp.name LIMIT 500';
                $params = [$agentId, $agentId];
                $params = array_merge($params, $networkParams);
                $st = $pdo->prepare($sql);
                $st->execute($params);
            }
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$row) {
                $row['price'] = api_resolve_price($pdo, $user, $row);
            }
            unset($row);
            Response::success($rows);

            return;
        }

        Response::error('Forbidden', 403);
    }

    public static function store(PDO $pdo): void
    {
        $user = api_require_auth($pdo);
        $slug = $user['role_slug'] ?? '';
        $body = api_json_body();

        $data = self::validateBundlePayload($body);

        if ($slug === ROLE_AGENT) {
            $data['agent_id'] = (int) $user['id'];
        } elseif ($slug === ROLE_SUPPLIER) {
            $data['agent_id'] = isset($body['agent_id']) ? (int) $body['agent_id'] : null;
            if ($data['agent_id'] !== null) {
                $chk = $pdo->prepare(
                    'SELECT 1 FROM users u INNER JOIN roles r ON r.id = u.role_id
                     WHERE u.id = ? AND r.slug = ? AND u.status = ? AND u.deleted_at IS NULL LIMIT 1'
                );
                $chk->execute([$data['agent_id'], ROLE_AGENT, 'active']);
                if ($chk->fetchColumn() === false) {
                    Response::error('Invalid agent_id', 422);
                }
            }
        } else {
            Response::error('Forbidden', 403);
        }

        $pdo->prepare(
            'INSERT INTO bundle_packages (agent_id, network, package_kind, name, size_label, internal_cost, stock_count, is_available, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        )->execute([
            $data['agent_id'],
            $data['network'],
            $data['package_kind'],
            $data['name'],
            $data['size_label'],
            $data['internal_cost'],
            $data['stock_count'],
            $data['is_available'] ? 1 : 0,
        ]);

        Response::success(['id' => (int) $pdo->lastInsertId()], 'Created', 201);
    }

    public static function update(PDO $pdo, int $id): void
    {
        $user = api_require_auth($pdo);
        $bundle = self::fetchBundle($pdo, $id);
        if ($bundle === null) {
            Response::error('Not found', 404);
        }

        self::authorizeBundleMutation($user, $bundle);

        $body = api_json_body();
        $data = self::validateBundlePayload($body);

        $slug = $user['role_slug'] ?? '';

        $agentIdForRow = (int) $user['id'];
        if ($slug === ROLE_SUPPLIER) {
            if (array_key_exists('agent_id', $body)) {
                $agentIdForRow = $body['agent_id'] === null || $body['agent_id'] === '' ? null : (int) $body['agent_id'];
            } else {
                $agentIdForRow = $bundle['agent_id'] !== null ? (int) $bundle['agent_id'] : null;
            }

            if ($agentIdForRow !== null) {
                $chk = $pdo->prepare(
                    'SELECT 1 FROM users u INNER JOIN roles r ON r.id = u.role_id
                     WHERE u.id = ? AND r.slug = ? AND u.status = ? AND u.deleted_at IS NULL LIMIT 1'
                );
                $chk->execute([$agentIdForRow, ROLE_AGENT, 'active']);
                if ($chk->fetchColumn() === false) {
                    Response::error('Invalid agent_id', 422);
                }
            }
        }

        $pdo->prepare(
            'UPDATE bundle_packages SET agent_id = ?, network = ?, package_kind = ?, name = ?, size_label = ?, internal_cost = ?, stock_count = ?, is_available = ?, updated_at = NOW()
             WHERE id = ?'
        )->execute([
            $agentIdForRow,
            $data['network'],
            $data['package_kind'],
            $data['name'],
            $data['size_label'],
            $data['internal_cost'],
            $data['stock_count'],
            $data['is_available'] ? 1 : 0,
            $id,
        ]);

        Response::success(['id' => $id], 'Updated');
    }

    public static function destroy(PDO $pdo, int $id): void
    {
        $user = api_require_auth($pdo);
        $bundle = self::fetchBundle($pdo, $id);
        if ($bundle === null) {
            Response::error('Not found', 404);
        }

        self::authorizeBundleMutation($user, $bundle);

        $chk = $pdo->prepare(
            'SELECT 1 FROM orders WHERE bundle_package_id = ? AND status IN (\'PENDING\', \'PROCESSING\') LIMIT 1'
        );
        $chk->execute([$id]);
        if ($chk->fetchColumn() !== false) {
            Response::error('Cannot delete bundle with active orders', 409);
        }

        $pdo->prepare('DELETE FROM bundle_packages WHERE id = ?')->execute([$id]);

        Response::success(null, 'Deleted');
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function fetchBundle(PDO $pdo, int $id): ?array
    {
        $st = $pdo->prepare('SELECT * FROM bundle_packages WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @param  array<string, mixed>  $user
     * @param  array<string, mixed>  $bundle
     */
    private static function authorizeBundleMutation(array $user, array $bundle): void
    {
        $slug = $user['role_slug'] ?? '';

        if ($slug === ROLE_SUPPLIER) {
            return;
        }

        if ($slug === ROLE_AGENT && (int) ($bundle['agent_id'] ?? 0) === (int) $user['id']) {
            return;
        }

        Response::error('Forbidden', 403);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{network: string, name: string, size_label: string, internal_cost: string, stock_count: int, is_available: bool, agent_id: int|null}
     */
    private static function validateBundlePayload(array $body): array
    {
        $network = $body['network'] ?? '';
        $name = htmlspecialchars(trim((string) ($body['name'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $sizeLabel = htmlspecialchars(trim((string) ($body['size_label'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $cost = $body['internal_cost'] ?? null;
        $stock = (int) ($body['stock_count'] ?? 0);

        if (! in_array($network, ['MTN', 'Telecel', 'AirtelTigo'], true)) {
            Response::error('Invalid network', 422);
        }

        $packageKind = isset($body['package_kind']) ? trim((string) $body['package_kind']) : 'data';
        if (! in_array($packageKind, ['data', 'mtn_afa'], true)) {
            Response::error('Invalid package_kind', 422);
        }

        if ($packageKind === 'mtn_afa' && $network !== 'MTN') {
            Response::error('MTN AFA bundles require network MTN', 422);
        }

        if ($name === '' || $sizeLabel === '') {
            Response::error('name and size_label required', 422);
        }

        if (! is_numeric($cost) || (float) $cost < 0) {
            Response::error('internal_cost invalid', 422);
        }

        $isAvailable = filter_var($body['is_available'] ?? true, FILTER_VALIDATE_BOOLEAN);

        return [
            'network' => $network,
            'package_kind' => $packageKind,
            'name' => $name,
            'size_label' => $sizeLabel,
            'internal_cost' => api_money((string) $cost),
            'stock_count' => max(0, min(99999999, $stock)),
            'is_available' => $isAvailable,
            'agent_id' => isset($body['agent_id']) ? (int) $body['agent_id'] : null,
        ];
    }
}
