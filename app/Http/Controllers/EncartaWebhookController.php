<?php

namespace App\Http\Controllers;

use App\Services\Fulfillment\EncartaWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

final class EncartaWebhookController extends Controller
{
    public function __construct(
        private readonly EncartaWebhookService $encartaWebhookService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $raw = $request->getContent();
        $signature = $request->header('X-Webhook-Signature');

        if (! $this->encartaWebhookService->verifySignature($raw, is_string($signature) ? $signature : null)) {
            return response('Unauthorized', 401);
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            Log::warning('encarta_webhook_invalid_json');

            return response('Bad Request', 400);
        }

        try {
            $eventHeader = $request->header('X-Webhook-Event');
            $this->encartaWebhookService->handle(
                $payload,
                is_string($eventHeader) ? $eventHeader : null,
            );
        } catch (\Throwable $e) {
            Log::error('encarta_webhook_handler_failed', [
                'message' => $e->getMessage(),
            ]);
        }

        return response('ok', 200);
    }
}
