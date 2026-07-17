<?php

namespace App\Support;

use App\Models\AgentEarningsLedger;
use App\Models\AgentWithdrawalRequest;
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
