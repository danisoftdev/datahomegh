<?php

namespace App\Support;

use App\Models\AgentEarningsLedger;
use App\Models\AgentWithdrawalRequest;
use App\Models\User;
use App\Models\WalletLedger;
use Carbon\CarbonInterface;

final class TransactionReceipt
{
    public static function walletId(int $ledgerId): string
    {
        return 'WL-'.$ledgerId;
    }

    public static function withdrawalId(int $withdrawalId): string
    {
        return 'WD-'.$withdrawalId;
    }

    public static function earningsId(int $ledgerId): string
    {
        return 'AE-'.$ledgerId;
    }

    public static function formatDateTime(?CarbonInterface $at): ?string
    {
        return $at?->timezone(config('app.timezone'))->format('l, j M Y · g:i A T');
    }

    public static function formatDateTimeShort(?CarbonInterface $at): ?string
    {
        return $at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromWalletLedger(WalletLedger $ledger, ?string $accountLabel = null): array
    {
        return [
            'kind' => 'wallet',
            'title' => self::walletActionLabel($ledger),
            'transaction_id' => self::walletId((int) $ledger->id),
            'type' => $ledger->type,
            'source' => $ledger->source,
            'amount' => number_format((float) $ledger->amount, 2),
            'balance_before' => number_format((float) $ledger->balance_before, 2),
            'balance_after' => number_format((float) $ledger->balance_after, 2),
            'reference' => $ledger->reference,
            'note' => $ledger->note,
            'occurred_at' => self::formatDateTime($ledger->created_at),
            'occurred_at_short' => self::formatDateTimeShort($ledger->created_at),
            'account_label' => $accountLabel,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromWithdrawal(AgentWithdrawalRequest $withdrawal): array
    {
        return [
            'kind' => 'withdrawal',
            'title' => __('Withdrawal request'),
            'transaction_id' => self::withdrawalId((int) $withdrawal->id),
            'status' => $withdrawal->status,
            'amount' => number_format((float) $withdrawal->amount, 2),
            'fee' => number_format((float) $withdrawal->fee, 2),
            'net_amount' => number_format((float) $withdrawal->net_amount, 2),
            'payout_method' => strtoupper((string) $withdrawal->payout_method),
            'reference' => (string) $withdrawal->id,
            'note' => $withdrawal->agent_note,
            'occurred_at' => self::formatDateTime($withdrawal->created_at),
            'occurred_at_short' => self::formatDateTimeShort($withdrawal->created_at),
            'processed_at' => self::formatDateTime($withdrawal->processed_at),
            'processed_at_short' => self::formatDateTimeShort($withdrawal->processed_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromEarningsLedger(AgentEarningsLedger $entry): array
    {
        return [
            'kind' => 'earnings',
            'title' => __('Earnings transaction'),
            'transaction_id' => self::earningsId((int) $entry->id),
            'type' => $entry->type,
            'source' => $entry->source,
            'amount' => number_format((float) $entry->amount, 2),
            'balance_before' => number_format((float) $entry->balance_before, 2),
            'balance_after' => number_format((float) $entry->balance_after, 2),
            'reference' => $entry->reference,
            'note' => $entry->note,
            'occurred_at' => self::formatDateTime($entry->created_at),
            'occurred_at_short' => self::formatDateTimeShort($entry->created_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function ledgerDetails(WalletLedger $ledger, ?User $accountUser = null): array
    {
        $performer = self::resolvePerformedBy($ledger);

        return array_merge(self::fromWalletLedger($ledger, $accountUser?->username), [
            'direction' => match ($ledger->type) {
                'CREDIT', 'REFUND' => __('Money in'),
                'DEBIT' => __('Money out'),
                default => (string) $ledger->type,
            },
            'performed_by_role' => $performer['role'],
            'performed_by_name' => $performer['name'],
            'performed_by_label' => $performer['label'],
            'account_name' => $accountUser?->name,
            'account_username' => $accountUser?->username,
        ]);
    }

    /**
     * @return array{role: string, name: string, label: string}
     */
    private static function resolvePerformedBy(WalletLedger $ledger): array
    {
        $note = trim((string) ($ledger->note ?? ''));
        $source = (string) $ledger->source;
        $type = (string) $ledger->type;

        $role = match ($source) {
            'ADMIN_CREDIT', 'ADMIN_DEBIT' => __('Platform admin'),
            'AGENT_CREDIT' => __('Agent'),
            'PAYSTACK' => __('Paystack'),
            'ORDER' => __('Self (order checkout)'),
            'REFUND' => __('Order refund'),
            default => $source,
        };

        $name = match ($source) {
            'PAYSTACK' => 'Paystack',
            'ADMIN_CREDIT', 'ADMIN_DEBIT' => self::extractActorFromNote($note) ?? __('Admin'),
            'AGENT_CREDIT' => self::extractActorFromNote($note) ?? __('Agent'),
            default => $note !== '' ? $note : '—',
        };

        $label = match (true) {
            in_array($source, ['ADMIN_CREDIT', 'ADMIN_DEBIT'], true) && $type === 'DEBIT' => __('Debited by'),
            in_array($source, ['ADMIN_CREDIT', 'ADMIN_DEBIT', 'AGENT_CREDIT'], true) => __('Credited by'),
            $source === 'PAYSTACK' => __('Paid via'),
            $type === 'DEBIT' => __('Debited by'),
            default => __('From'),
        };

        return [
            'role' => $role,
            'name' => $name,
            'label' => $label,
        ];
    }

    private static function extractActorFromNote(string $note): ?string
    {
        if ($note === '') {
            return null;
        }

        if (preg_match('/\b(?:Admin|Agent)\s+([^\s—\-]+)/iu', $note, $matches)) {
            return $matches[1];
        }

        if (preg_match('/\badmin\s+([^\s—\-]+)/iu', $note, $matches)) {
            return $matches[1];
        }

        if (preg_match('/\bagent\s+([^\s—\-]+)/iu', $note, $matches)) {
            return $matches[1];
        }

        if (str_contains($note, '—')) {
            $head = trim(explode('—', $note, 2)[0]);
            if (preg_match('/(\S+)$/', $head, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private static function walletActionLabel(WalletLedger $ledger): string
    {
        return match ($ledger->type) {
            'CREDIT' => __('Wallet credit'),
            'DEBIT' => __('Wallet debit'),
            'REFUND' => __('Wallet refund'),
            default => __('Wallet transaction'),
        };
    }
}
