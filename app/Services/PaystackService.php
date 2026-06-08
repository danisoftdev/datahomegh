<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackService
{
    public function __construct(
        private string $secretKey = '',
        private string $baseUrl = '',
    ) {
        $this->secretKey = (string) config('paystack.secret_key');
        $this->baseUrl = (string) config('paystack.base_url');
    }

    /**
     * @param  array<string, mixed>  $options
     *                                         callback_url?: string,
     *                                         metadata?: array<string, mixed> (merged over defaults; overrides type)
     * @return array{authorization_url: string, reference: string}
     */
    public function initializePayment(User $user, float|string $amount, array $options = []): array
    {
        if ($this->secretKey === '') {
            throw new RuntimeException('Paystack secret key is not configured.');
        }

        $reference = uniqid('dhgh_');
        $amountPesewas = (int) round(((float) $amount) * 100);

        $email = $user->email ?? "{$user->username}@users.datahomegh.local";

        $callbackUrl = (string) ($options['callback_url'] ?? route('wallet.topup.callback', [], true));
        $metadata = array_merge(
            [
                'user_id' => $user->id,
                'type' => 'wallet_topup',
            ],
            $options['metadata'] ?? [],
        );

        $response = Http::timeout(30)
            ->connectTimeout(10)
            ->withToken($this->secretKey)
            ->acceptJson()
            ->asJson()
            ->post("{$this->baseUrl}/transaction/initialize", [
                'email' => $email,
                'amount' => $amountPesewas,
                'currency' => 'GHS',
                'reference' => $reference,
                'callback_url' => $callbackUrl,
                'metadata' => $metadata,
            ]);

        $json = $response->json() ?? [];

        if (! ($json['status'] ?? false)) {
            throw new RuntimeException((string) ($json['message'] ?? 'Paystack initialize failed.'));
        }

        $data = $json['data'] ?? [];

        return [
            'authorization_url' => (string) ($data['authorization_url'] ?? ''),
            'reference' => (string) ($data['reference'] ?? $reference),
        ];
    }

    /**
     * @return array<string, mixed> Paystack transaction data payload
     */
    public function verifyPayment(string $reference): array
    {
        if ($this->secretKey === '') {
            throw new RuntimeException('Paystack secret key is not configured.');
        }

        $response = Http::withToken($this->secretKey)
            ->acceptJson()
            ->get("{$this->baseUrl}/transaction/verify/".rawurlencode($reference));

        $json = $response->json() ?? [];

        if (! ($json['status'] ?? false)) {
            throw new RuntimeException((string) ($json['message'] ?? 'Paystack verify failed.'));
        }

        return $json['data'] ?? [];
    }

    public function validateWebhook(string $payload, string $signature): bool
    {
        if ($this->secretKey === '') {
            return false;
        }

        $computed = hash_hmac('sha512', $payload, $this->secretKey);

        return hash_equals($computed, $signature);
    }
}
