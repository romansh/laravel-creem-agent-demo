<?php

namespace App\Services;

use App\Models\Checkout;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Transaction;
use Illuminate\Support\Str;

class SimulatorPayloadFactory
{
    public function makeId(string $prefix): string
    {
        return $prefix.'_'.Str::lower(Str::random(12));
    }

    public function product(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price' => $product->price,
            'currency' => $product->currency,
            'billing_type' => $product->billing_type,
            'status' => $product->status,
            'metadata' => $product->metadata ?? [],
            'created_at' => optional($product->created_at)?->toIso8601String(),
        ];
    }

    public function customer(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'email' => $customer->email,
            'name' => $customer->name,
            'country' => $customer->country,
            'status' => $customer->status,
            'metadata' => $customer->metadata ?? [],
            'created_at' => optional($customer->created_at)?->toIso8601String(),
        ];
    }

    public function subscription(Subscription $subscription): array
    {
        $subscription->loadMissing(['customer', 'product']);

        return [
            'id' => $subscription->id,
            'status' => $subscription->status,
            'customer_id' => $subscription->customer_id,
            'product_id' => $subscription->product_id,
            'customer' => $subscription->customer ? $this->customer($subscription->customer) : null,
            'product' => $subscription->product ? $this->product($subscription->product) : null,
            'current_period_end' => optional($subscription->current_period_end)?->toIso8601String(),
            'trial_ends_at' => optional($subscription->trial_ends_at)?->toIso8601String(),
            'canceled_at' => optional($subscription->canceled_at)?->toIso8601String(),
            'paused_at' => optional($subscription->paused_at)?->toIso8601String(),
            'metadata' => $subscription->metadata ?? [],
            'created_at' => optional($subscription->created_at)?->toIso8601String(),
        ];
    }

    public function transaction(Transaction $transaction): array
    {
        $transaction->loadMissing(['customer', 'product', 'subscription']);

        return [
            'id' => $transaction->id,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'status' => $transaction->status,
            'type' => $transaction->type,
            'customer_id' => $transaction->customer_id,
            'product_id' => $transaction->product_id,
            'subscription_id' => $transaction->subscription_id,
            'customer_email' => $transaction->customer?->email,
            'customer' => $transaction->customer ? $this->customer($transaction->customer) : null,
            'product' => $transaction->product ? $this->product($transaction->product) : null,
            'subscription' => $transaction->subscription ? $this->subscription($transaction->subscription) : null,
            'metadata' => $transaction->metadata ?? [],
            'created_at' => optional($transaction->occurred_at)?->toIso8601String(),
        ];
    }

    public function checkout(Checkout $checkout): array
    {
        $checkout->loadMissing(['customer', 'product']);

        return [
            'id' => $checkout->id,
            'status' => $checkout->status,
            'product_id' => $checkout->product_id,
            'customer_id' => $checkout->customer_id,
            'checkout_url' => $checkout->checkout_url,
            'customer' => $checkout->customer ? $this->customer($checkout->customer) : null,
            'product' => $checkout->product ? $this->product($checkout->product) : null,
            'completed_at' => optional($checkout->completed_at)?->toIso8601String(),
            'metadata' => $checkout->metadata ?? [],
            'created_at' => optional($checkout->created_at)?->toIso8601String(),
        ];
    }

    public function eventPayload(string $eventType, array $object): array
    {
        return [
            'id' => $this->makeId('evt'),
            'eventType' => $eventType,
            'created_at' => now()->getTimestampMs(),
            'data' => $object,
        ];
    }
}
