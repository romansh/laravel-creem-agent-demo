<?php

namespace App\Services;

use App\Models\Checkout;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Transaction;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB as FacadeDB;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SimulatorScenarioService
{
    public function __construct(
        private SimulatorPayloadFactory $payloads,
        private SimulatorWebhookDispatcher $webhooks,
    ) {}

    private bool $deterministic = false;

    private function initDeterministic(): void
    {
        if ($this->deterministic) {
            return;
        }

        $seed = config('simulator.agent.random_seed');
        if ($seed !== null && $seed !== '') {
            // seed mt_rand for deterministic behavior
            mt_srand((int) $seed);
            $this->deterministic = true;
        }
    }

    private function randInt(int $min, int $max): int
    {
        $this->initDeterministic();
        if ($this->deterministic) {
            return mt_rand($min, $max);
        }

        return random_int($min, $max);
    }

    public function seedDemo(string $store, array $options = []): array
    {
        $days = max(1, (int) ($options['days'] ?? 45));
        $productCount = max(1, (int) ($options['products'] ?? 6));
        $customerCount = max(1, (int) ($options['customers'] ?? 40));
        $subscriptionCount = max(1, (int) ($options['subscriptions'] ?? 24));
        $transactionCount = max(1, (int) ($options['transactions'] ?? 120));
        $reset = (bool) ($options['reset'] ?? false);

        return DB::transaction(function () use ($store, $days, $productCount, $customerCount, $subscriptionCount, $transactionCount, $reset): array {
            if ($reset) {
                Checkout::query()->where('store', $store)->delete();
                Transaction::query()->where('store', $store)->delete();
                Subscription::query()->where('store', $store)->delete();
                Customer::query()->where('store', $store)->delete();
                Product::query()->where('store', $store)->delete();
            }

            $products = collect();
            for ($index = 1; $index <= $productCount; $index++) {
                $products->push(Product::create([
                    'id' => $this->payloads->makeId('prod'),
                    'store' => $store,
                    'name' => 'Demo Product '.$index,
                    'description' => 'Simulator product '.$index,
                    'price' => [1900, 2900, 4900, 7900, 12900][$index % 5],
                    'currency' => 'USD',
                    'billing_type' => $index <= (int) ceil($productCount / 2) ? 'subscription' : 'one_time',
                    'status' => 'active',
                    'metadata' => ['demo' => true],
                    'created_at' => now()->subDays($this->randInt(10, $days)),
                    'updated_at' => now(),
                ]));
            }

            $customers = collect();
            for ($index = 1; $index <= $customerCount; $index++) {
                $createdAt = now()->subDays($this->randInt(0, $days))->subMinutes($this->randInt(0, 1440));
                $customers->push(Customer::create([
                    'id' => $this->payloads->makeId('cus'),
                    'store' => $store,
                    'email' => sprintf('customer%03d-%s@example.test', $index, $store),
                    'name' => 'Demo Customer '.$index,
                    'country' => Arr::random(['US', 'GB', 'DE', 'PL', 'FR']),
                    'status' => 'active',
                    'metadata' => ['source' => 'seed-demo'],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]));
            }

            $subscriptionProducts = $products->where('billing_type', 'subscription')->values();
            $statuses = $this->subscriptionStatuses($subscriptionCount);
            $subscriptions = collect();

            $custPool = $customers->all();
            if (config('simulator.agent.random_seed')) {
                shuffle($custPool);
                $custPool = collect($custPool)->take(min($subscriptionCount, $customers->count()));
            } else {
                $custPool = $customers->shuffle()->take(min($subscriptionCount, $customers->count()));
            }

            foreach ($custPool as $index => $customer) {
                $status = $statuses[$index] ?? 'active';
                $createdAt = now()->subDays($this->randInt(0, $days))->subHours($this->randInt(0, 48));
                $subscriptions->push(Subscription::create([
                    'id' => $this->payloads->makeId('sub'),
                    'store' => $store,
                    'customer_id' => $customer->id,
                    'product_id' => $subscriptionProducts->random()->id,
                    'status' => $status,
                    'current_period_end' => now()->addDays(random_int(3, 40)),
                    'trial_ends_at' => $status === 'trialing' ? now()->addDays(random_int(1, 14)) : null,
                    'canceled_at' => in_array($status, ['canceled', 'expired', 'scheduled_cancel'], true) ? now()->subDays(random_int(0, 10)) : null,
                    'paused_at' => $status === 'paused' ? now()->subDays(random_int(0, 10)) : null,
                    'metadata' => ['seeded' => true],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]));
            }

            $transactions = collect();
            for ($index = 0; $index < $transactionCount; $index++) {
                $customer = $customers->random();
                $matchingSubscription = $subscriptions->firstWhere('customer_id', $customer->id);
                $product = $matchingSubscription
                    ? $products->firstWhere('id', $matchingSubscription->product_id)
                    : $products->random();
                $occurredAt = now()->subDays($this->randInt(0, $days))->subMinutes($this->randInt(0, 1440));
                $status = $this->randInt(1, 100) <= 88 ? 'succeeded' : 'failed';

                $transactions->push(Transaction::create([
                    'id' => $this->payloads->makeId('txn'),
                    'store' => $store,
                    'customer_id' => $customer->id,
                    'product_id' => $product?->id,
                    'subscription_id' => $matchingSubscription?->id,
                    'amount' => $product?->price ?? 2900,
                    'currency' => $product?->currency ?? 'USD',
                    'status' => $status,
                    'type' => 'payment',
                    'occurred_at' => $occurredAt,
                    'metadata' => ['seeded' => true],
                    'created_at' => $occurredAt,
                    'updated_at' => $occurredAt,
                ]));
            }

            foreach (range(1, 5) as $index) {
                $product = $products->random();
                $customer = $customers->random();
                $createdAt = now()->subDays($this->randInt(0, 5))->subMinutes($this->randInt(0, 240));

                Checkout::create([
                    'id' => $this->payloads->makeId('chk'),
                    'store' => $store,
                    'product_id' => $product->id,
                    'customer_id' => $customer->id,
                    'status' => Arr::random(['open', 'completed']),
                    'checkout_url' => rtrim(config('app.url'), '/').'/checkout/'.$this->payloads->makeId('chkurl'),
                    'completed_at' => random_int(0, 1) ? now()->subHours(random_int(0, 24)) : null,
                    'metadata' => ['seeded' => true],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }

            return [
                'store' => $store,
                'products' => $products->count(),
                'customers' => $customers->count(),
                'subscriptions' => $subscriptions->count(),
                'transactions' => $transactions->count(),
            ];
        });
    }

    public function advance(string $store, array $options = []): array
    {
        $sales = max(0, (int) ($options['sales'] ?? 3));
        $newCustomers = max(0, (int) ($options['new_customers'] ?? $options['new-customers'] ?? 1));
        $pastDue = max(0, (int) ($options['past_due'] ?? $options['past-due'] ?? 1));
        $cancellations = max(0, (int) ($options['cancellations'] ?? 0));
        $paused = max(0, (int) ($options['paused'] ?? 0));
        $resumed = max(0, (int) ($options['resumed'] ?? 0));
        $sendWebhooks = (bool) ($options['send_webhooks'] ?? $options['send-webhooks'] ?? false);

        $events = [];

        $this->initDeterministic();
        $freshCustomers = collect();
        for ($index = 0; $index < $newCustomers; $index++) {
            $customer = Customer::create([
                'id' => $this->payloads->makeId('cus'),
                'store' => $store,
                'email' => sprintf('fresh-%s-%02d@example.test', now()->format('YmdHis'), $index),
                'name' => 'Fresh Customer '.($index + 1),
                'country' => 'US',
                'status' => 'active',
                'metadata' => ['source' => 'advance'],
            ]);
            $freshCustomers->push($customer);
        }

        $products = Product::query()->where('store', $store)->get();
        $customers = Customer::query()->where('store', $store)->get();
        $subscriptions = Subscription::query()->where('store', $store)->get();

        $newTransactions = collect();
        for ($index = 0; $index < $sales; $index++) {
            $customer = ($freshCustomers->isNotEmpty() && $index < $freshCustomers->count())
                ? $freshCustomers[$index]
                : $customers->random();
            $product = $products->random();
            $linkedSubscription = $subscriptions->firstWhere('customer_id', $customer->id);
            $occurredAt = now()->subMinutes($index);

            $transaction = Transaction::create([
                'id' => $this->payloads->makeId('txn'),
                'store' => $store,
                'customer_id' => $customer->id,
                'product_id' => $product->id,
                'subscription_id' => $linkedSubscription?->id,
                'amount' => $product->price,
                'currency' => $product->currency,
                'status' => 'succeeded',
                'type' => 'payment',
                'occurred_at' => $occurredAt,
                'metadata' => ['source' => 'advance'],
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);

            $newTransactions->push($transaction);
            $events[] = ['event' => 'checkout.completed', 'object' => $this->payloads->transaction($transaction)];
        }

        foreach ($this->pickSubscriptions($store, ['active', 'trialing'], $pastDue) as $subscription) {
            $subscription->update(['status' => 'past_due']);
            $events[] = ['event' => 'subscription.past_due', 'object' => $this->payloads->subscription($subscription->fresh())];
        }

        foreach ($this->pickSubscriptions($store, ['active', 'trialing', 'past_due', 'paused'], $cancellations) as $subscription) {
            $subscription->update(['status' => 'canceled', 'canceled_at' => now()]);
            $events[] = ['event' => 'subscription.canceled', 'object' => $this->payloads->subscription($subscription->fresh())];
        }

        foreach ($this->pickSubscriptions($store, ['active'], $paused) as $subscription) {
            $subscription->update(['status' => 'paused', 'paused_at' => now()]);
            $events[] = ['event' => 'subscription.paused', 'object' => $this->payloads->subscription($subscription->fresh())];
        }

        foreach ($this->pickSubscriptions($store, ['paused'], $resumed) as $subscription) {
            $subscription->update(['status' => 'active', 'paused_at' => null]);
            $events[] = ['event' => 'subscription.active', 'object' => $this->payloads->subscription($subscription->fresh())];
        }

        $deliveries = [];
        if ($sendWebhooks || config('simulator.agent.auto_send_webhooks')) {
            foreach ($events as $event) {
                $deliveries[] = $this->webhooks->dispatch(
                    $store,
                    $event['event'],
                    $this->payloads->eventPayload($event['event'], $event['object']),
                );
            }
        }

        $result = [
            'store' => $store,
            'new_customers' => $freshCustomers->count(),
            'new_transactions' => $newTransactions->count(),
            'events' => collect($events)->pluck('event')->all(),
            'deliveries' => $deliveries,
        ];

        // Persist last advance counts only when a test-created table exists.
        try {
            if (Schema::hasTable('simulator_advance_results')) {
                FacadeDB::table('simulator_advance_results')->updateOrInsert(
                    ['store' => $store],
                    [
                        'events_count' => count($events),
                        'deliveries_count' => count($deliveries),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        } catch (\Throwable $e) {
            // Ignore optional test bookkeeping failures.
        }

        return $result;
    }

    public function sendWebhook(string $eventType, string $store, array $references = []): array
    {
        $object = $this->resolveObjectForEvent($eventType, $store, $references);

        return $this->webhooks->dispatch(
            $store,
            $eventType,
            $this->payloads->eventPayload($eventType, $object),
        );
    }

    private function resolveObjectForEvent(string $eventType, string $store, array $references): array
    {
        return match ($eventType) {
            'checkout.completed' => $this->payloads->checkout(
                Checkout::query()->where('store', $store)->when($references['checkout'] ?? null, fn ($query, $id) => $query->where('id', $id))->latest('created_at')->firstOrFail()
            ),
            'payment.failed' => $this->payloads->transaction(
                Transaction::query()->where('store', $store)->when($references['transaction'] ?? null, fn ($query, $id) => $query->where('id', $id))->latest('occurred_at')->firstOrFail()
            ),
            default => $this->payloads->subscription(
                Subscription::query()->where('store', $store)->when($references['subscription'] ?? null, fn ($query, $id) => $query->where('id', $id))->latest('updated_at')->firstOrFail()
            ),
        };
    }

    private function subscriptionStatuses(int $count): array
    {
        $pool = [];
        $pool = array_merge($pool, array_fill(0, max(1, (int) floor($count * 0.55)), 'active'));
        $pool = array_merge($pool, array_fill(0, max(1, (int) floor($count * 0.10)), 'trialing'));
        $pool = array_merge($pool, array_fill(0, max(1, (int) floor($count * 0.10)), 'past_due'));
        $pool = array_merge($pool, array_fill(0, max(1, (int) floor($count * 0.08)), 'paused'));
        $pool = array_merge($pool, array_fill(0, max(1, (int) floor($count * 0.08)), 'canceled'));
        $pool = array_merge($pool, array_fill(0, max(1, (int) floor($count * 0.05)), 'expired'));
        $pool = array_merge($pool, array_fill(0, max(1, (int) floor($count * 0.04)), 'scheduled_cancel'));

        shuffle($pool);

        return array_slice(array_pad($pool, $count, 'active'), 0, $count);
    }

    private function pickSubscriptions(string $store, array $statuses, int $count): Collection
    {
        if ($count === 0) {
            return collect();
        }

        return Subscription::query()
            ->where('store', $store)
            ->whereIn('status', $statuses)
            ->inRandomOrder()
            ->limit($count)
            ->get();
    }
}
