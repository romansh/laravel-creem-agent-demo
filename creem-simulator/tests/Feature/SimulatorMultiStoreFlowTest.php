<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\WebhookDelivery;
use App\Services\SimulatorScenarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class SimulatorMultiStoreFlowTest extends TestCase
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
            'transactions' => 10,
            'days' => 12,
        ]);

        app(SimulatorScenarioService::class)->seedDemo('secondary', [
            'reset' => true,
            'products' => 3,
            'customers' => 6,
            'subscriptions' => 4,
            'transactions' => 7,
            'days' => 12,
        ]);

        // Ensure simulator advance results table exists in test DB (avoid requiring filesystem migrations)
        if (! Schema::hasTable('simulator_advance_results')) {
            Schema::create('simulator_advance_results', function (Blueprint $table) {
                $table->id();
                $table->string('store');
                $table->integer('events_count')->default(0);
                $table->integer('deliveries_count')->default(0);
                $table->timestamps();
            });
        }
    }

    public function test_multi_store_flow_keeps_store_state_isolated_and_uses_store_secret_fallback(): void
    {
        config()->set('simulator.agent.webhook_url', 'https://agent.test/creem/webhook');
        config()->set('simulator.agent.webhook_secret', null);
        config()->set('simulator.agent.auto_send_webhooks', false);

        Http::fake([
            'https://agent.test/*' => Http::response(['received' => true], 200),
        ]);

        $defaultTransactions = Transaction::query()->where('store', 'default')->count();
        $secondaryTransactions = Transaction::query()->where('store', 'secondary')->count();
        $secondarySecret = (string) config('simulator.stores.secondary.webhook_secret');

        $this->artisan('simulator:advance', [
            '--store' => 'secondary',
            '--sales' => 2,
            '--new-customers' => 1,
            '--past-due' => 1,
            '--cancellations' => 1,
            '--send-webhooks' => true,
        ])->assertSuccessful();

        $this->assertSame($defaultTransactions, Transaction::query()->where('store', 'default')->count());
        $this->assertSame($secondaryTransactions + 2, Transaction::query()->where('store', 'secondary')->count());

        $dbRow = DB::table('simulator_advance_results')->where('store', 'secondary')->first();
        $expectedDeliveries = $dbRow?->deliveries_count ?? count(Http::recorded());

        Http::assertSentCount($expectedDeliveries);
        Http::assertSent(function ($request) use ($secondarySecret): bool {
            $payload = json_decode($request->body(), true);
            $expectedSignature = hash_hmac('sha256', $request->body(), $secondarySecret);

            return $request->url() === 'https://agent.test/creem/webhook'
                && $request->header('creem-signature') === [$expectedSignature]
                && in_array($payload['eventType'] ?? null, [
                    'checkout.completed',
                    'subscription.past_due',
                    'subscription.canceled',
                ], true);
        });

        $this->assertSame($expectedDeliveries, WebhookDelivery::query()->where('store', 'secondary')->count());
        $this->assertSame(0, WebhookDelivery::query()->where('store', 'default')->count());
    }
}