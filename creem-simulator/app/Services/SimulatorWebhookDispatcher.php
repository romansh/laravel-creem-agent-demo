<?php

namespace App\Services;

use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;

class SimulatorWebhookDispatcher
{
    public function __construct(private SimulatorStoreResolver $stores) {}

    public function dispatch(string $store, string $eventType, array $payload): array
    {
        $targetUrl = config('simulator.agent.webhook_url');
        $secret = config('simulator.agent.webhook_secret') ?: $this->stores->webhookSecretForStore($store);

        if (!$targetUrl || !$secret) {
            $delivery = WebhookDelivery::create([
                'store' => $store,
                'event_type' => $eventType,
                'target_url' => $targetUrl,
                'status_code' => null,
                'successful' => false,
                'payload' => $payload,
                'response_body' => 'Webhook target URL or secret is not configured.',
            ]);

            return ['ok' => false, 'delivery_id' => $delivery->id, 'message' => 'Webhook target URL or secret is not configured.'];
        }

        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $raw, $secret);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'creem-signature' => $signature,
            ])->post($targetUrl, $payload);

            $delivery = WebhookDelivery::create([
                'store' => $store,
                'event_type' => $eventType,
                'target_url' => $targetUrl,
                'status_code' => $response->status(),
                'successful' => $response->successful(),
                'payload' => $payload,
                'response_body' => $response->body(),
            ]);

            return [
                'ok' => $response->successful(),
                'delivery_id' => $delivery->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ];
        } catch (\Throwable $exception) {
            $delivery = WebhookDelivery::create([
                'store' => $store,
                'event_type' => $eventType,
                'target_url' => $targetUrl,
                'status_code' => null,
                'successful' => false,
                'payload' => $payload,
                'response_body' => $exception->getMessage(),
            ]);

            return ['ok' => false, 'delivery_id' => $delivery->id, 'message' => $exception->getMessage()];
        }
    }
}
