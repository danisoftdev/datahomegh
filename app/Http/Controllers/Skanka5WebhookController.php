<?php

namespace App\Http\Controllers;

use App\Services\Fulfillment\Skanka5WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class Skanka5WebhookController extends Controller
{
    public function __construct(
        private readonly Skanka5WebhookService $skanka5WebhookService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $raw = $request->getContent();
        $signature = $request->header('X-Skanka5-Signature');

        if (! $this->skanka5WebhookService->verifySignature($raw, is_string($signature) ? $signature : null)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            Log::warning('skanka5_webhook_invalid_json');

            return response()->json(['error' => 'Bad Request'], 400);
        }

        try {
            $this->skanka5WebhookService->handle($payload);
        } catch (\Throwable $e) {
            Log::error('skanka5_webhook_handler_failed', [
                'message' => $e->getMessage(),
            ]);
        }

        return response()->json(['received' => true], 200);
    }
}
