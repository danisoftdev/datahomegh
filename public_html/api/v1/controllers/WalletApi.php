<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/middleware/Auth.php';
require_once dirname(__DIR__).'/helpers/Request.php';
require_once dirname(__DIR__).'/services/Support.php';

final class WalletApi
{
    public static function balance(PDO $pdo): void
    {
        $user = api_require_auth($pdo);
        $st = $pdo->prepare('SELECT * FROM wallets WHERE user_id = ? LIMIT 1');
        $st->execute([(int) $user['id']]);
        $w = $st->fetch(PDO::FETCH_ASSOC);
        if ($w === false) {
            Response::error('Wallet not found', 404);
        }

        Response::success($w);
    }

    public static function ledger(PDO $pdo): void
    {
        $user = api_require_auth($pdo);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 30)));
        $offset = ($page - 1) * $perPage;

        $st = $pdo->prepare(
            'SELECT * FROM wallet_ledger WHERE user_id = ? ORDER BY id DESC LIMIT '.$perPage.' OFFSET '.$offset
        );
        $st->execute([(int) $user['id']]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $cst = $pdo->prepare('SELECT COUNT(*) FROM wallet_ledger WHERE user_id = ?');
        $cst->execute([(int) $user['id']]);
        $total = (int) $cst->fetchColumn();

        Response::success([
            'data' => $rows,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    /**
     * Initialize Paystack top-up (POST via cURL in {@see api_paystack_initialize}).
     */
    public static function initTopup(PDO $pdo): void
    {
        $user = api_require_auth($pdo);
        $body = api_json_body();
        $amount = (float) ($body['amount'] ?? 0);

        if ($amount < 1 || $amount > 10000) {
            Response::error('Amount must be between 1 and 10000 GHS', 422);
        }

        $init = api_paystack_initialize($user, $amount);

        $pdo->prepare(
            'INSERT INTO paystack_transactions (user_id, reference, amount, status, channel, paid_at, metadata, created_at, updated_at)
             VALUES (?, ?, ?, ?, NULL, NULL, ?, NOW(), NOW())'
        )->execute([
            (int) $user['id'],
            $init['reference'],
            number_format($amount, 2, '.', ''),
            'pending',
            json_encode(['initialized_via' => 'api'], JSON_THROW_ON_ERROR),
        ]);

        Response::success([
            'authorization_url' => $init['authorization_url'],
            'reference' => $init['reference'],
        ]);
    }

    public static function verifyTopup(PDO $pdo, string $reference): void
    {
        $user = api_require_auth($pdo);
        $reference = rawurldecode($reference);

        $data = api_paystack_verify_remote($reference);

        if (($data['status'] ?? '') !== 'success') {
            Response::error('Payment was not successful', 400);
        }

        $metaUid = api_paystack_metadata_user_id($data);
        if ($metaUid !== (int) $user['id']) {
            Response::error('Payment does not match this account', 403);
        }

        $amountGhs = api_paystack_amount_ghs($data);

        try {
            $pdo->beginTransaction();

            $tst = $pdo->prepare('SELECT * FROM paystack_transactions WHERE reference = ? FOR UPDATE');
            $tst->execute([$reference]);
            $txn = $tst->fetch(PDO::FETCH_ASSOC);

            if ($txn !== false && ($txn['status'] ?? '') === 'success') {
                $pdo->commit();
                Response::success(['reference' => $reference, 'already_applied' => true]);

                return;
            }

            $dup = $pdo->prepare(
                'SELECT 1 FROM wallet_ledger WHERE user_id = ? AND reference = ? AND source = ? AND type = ? LIMIT 1'
            );
            $dup->execute([(int) $user['id'], $reference, 'PAYSTACK', 'CREDIT']);
            if ($dup->fetchColumn() !== false) {
                $pdo->commit();
                Response::success(['reference' => $reference, 'already_applied' => true]);

                return;
            }

            api_wallet_credit_in_tx($pdo, (int) $user['id'], $amountGhs, 'PAYSTACK', $reference, null);

            $upd = $pdo->prepare(
                'UPDATE paystack_transactions SET user_id = ?, amount = ?, status = ?, channel = ?, paid_at = ?, metadata = ?, updated_at = NOW() WHERE reference = ?'
            );
            $upd->execute([
                (int) $user['id'],
                $amountGhs,
                'success',
                isset($data['channel']) ? (string) $data['channel'] : null,
                isset($data['paid_at']) ? date('Y-m-d H:i:s', strtotime((string) $data['paid_at'])) : date('Y-m-d H:i:s'),
                json_encode($data, JSON_THROW_ON_ERROR),
                $reference,
            ]);

            if ($upd->rowCount() === 0) {
                $pdo->prepare(
                    'INSERT INTO paystack_transactions (user_id, reference, amount, status, channel, paid_at, metadata, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                )->execute([
                    (int) $user['id'],
                    $reference,
                    $amountGhs,
                    'success',
                    isset($data['channel']) ? (string) $data['channel'] : null,
                    isset($data['paid_at']) ? date('Y-m-d H:i:s', strtotime((string) $data['paid_at'])) : date('Y-m-d H:i:s'),
                    json_encode($data, JSON_THROW_ON_ERROR),
                ]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            Response::error($e->getMessage(), 400);
        }

        Response::success(['reference' => $reference, 'amount_ghs' => $amountGhs]);
    }
}
