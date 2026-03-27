<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\WebhookDelivery;
use App\Services\SimulatorScenarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SimulatorFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SimulatorScenarioService::class)->seedDemo('default', [
            'reset' => true,
            'products' => 4,
            'customers' => 8,
            'subscriptions' => 6,
            'transactions' => 12,
            'days' => 14,
        ]);
    }

    public function test_demo_flow_with_simulator_auto_delivery_sends_signed_webhooks(): void
    {
        config()->set('simulator.agent.webhook_url', 'https://agent.test/creem/webhook');
        config()->set('simulator.agent.webhook_secret', 'whsec_test');
        config()->set('simulator.agent.auto_send_webhooks', true);

        Http::fake([
            'https://agent.test/*' => Http::response(['received' => true], 202),
        ]);

        $this->artisan('simulator:advance', [
            '--store' => 'default',
            '--sales' => 2,
            '--new-customers' => 1,
            '--past-due' => 1,
            '--cancellations' => 1,
        ])->assertSuccessful();

        Http::assertSentCount(4);
        Http::assertSent(function ($request): bool {
            $payload = json_decode($request->body(), true);

            return $request->url() === 'https://agent.test/creem/webhook'
                && $request->hasHeader('creem-signature')
                && in_array($payload['eventType'] ?? null, [
                    'checkout.completed',
                    'subscription.past_due',
                    'subscription.canceled',
                ], true);
        });

        $this->assertSame(4, WebhookDelivery::query()->count());
        $this->assertSame(4, WebhookDelivery::query()->where('successful', true)->count());
    }

    public function test_demo_flow_without_auto_delivery_keeps_changes_local_until_manual_send(): void
    {
        config()->set('simulator.agent.webhook_url', 'https://agent.test/creem/webhook');
        config()->set('simulator.agent.webhook_secret', 'whsec_test');
        config()->set('simulator.agent.auto_send_webhooks', false);

        Http::fake([
            'https://agent.test/*' => Http::response(['received' => true], 200),
        ]);

        $subscription = Subscription::query()->where('store', 'default')->latest('updated_at')->firstOrFail();

        $this->artisan('simulator:advance', [
            '--store' => 'default',
            '--sales' => 1,
            '--new-customers' => 1,
            '--past-due' => 1,
        ])->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(0, WebhookDelivery::query()->count());

        $this->artisan('simulator:send-webhook', [
            'event' => 'subscription.canceled',
            '--store' => 'default',
            '--subscription' => $subscription->id,
        ])->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertSame(1, WebhookDelivery::query()->count());
        $this->assertTrue(WebhookDelivery::query()->firstOrFail()->successful);
    }

    public function test_demo_flow_without_webhook_target_fails_gracefully(): void
    {
        config()->set('simulator.agent.webhook_url', null);
        config()->set('simulator.agent.webhook_secret', null);
        config()->set('simulator.agent.auto_send_webhooks', false);

        $this->artisan('simulator:send-webhook', [
            'event' => 'payment.failed',
            '--store' => 'default',
        ])->assertFailed();

        $delivery = WebhookDelivery::query()->firstOrFail();

        $this->assertFalse($delivery->successful);
        $this->assertNull($delivery->status_code);
        $this->assertSame('payment.failed', $delivery->event_type);
        $this->assertSame('Webhook target URL or secret is not configured.', $delivery->response_body);
    }
}