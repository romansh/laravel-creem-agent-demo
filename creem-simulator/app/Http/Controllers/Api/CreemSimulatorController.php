<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Checkout;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Services\SimulatorPayloadFactory;
use App\Services\SimulatorStoreResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreemSimulatorController extends Controller
{
    public function __construct(
        private SimulatorStoreResolver $stores,
        private SimulatorPayloadFactory $payloads,
    ) {}

    public function showProduct(Request $request): JsonResponse
    {
        $store = $this->resolveStore($request);
        if ($store instanceof JsonResponse) {
            return $store;
        }

        $product = Product::query()
            ->where('store', $store)
            ->where('id', (string) $request->query('product_id'))
            ->first();

        return $product
            ? response()->json($this->payloads->product($product))
            : response()->json(['message' => 'Product not found'], 404);
    }

    public function listProducts(Request $request): JsonResponse
    {
        $store = $this->resolveStore($request);
        if ($store instanceof JsonResponse) {
            return $store;
        }

        $page = max(1, (int) $request->query('page_number', 1));
        $size = max(1, min(100, (int) $request->query('page_size', 20)));

        $query = Product::query()->where('store', $store)->orderBy('created_at', 'desc');

        return $this->paginatedResponse($query, $page, $size, fn (Product $product) => $this->payloads->product($product));
    }

    public function createProduct(Request $request): JsonResponse
    {
        $store = $this->resolveStore($request);
        if ($store instanceof JsonResponse) {
            return $store;
        }

        $product = Product::create([
            'id' => $request->input('id', $this->payloads->makeId('prod')),
            'store' => $store,
            'name' => $request->input('name', 'Generated Product'),
            'description' => $request->input('description'),
            'price' => (int) $request->input('price', 2900),
            'currency' => $request->input('currency', 'USD'),
            'billing_type' => $request->input('billing_type', 'one_time'),
            'status' => $request->input('status', 'active'),
            'metadata' => $request->input('metadata', []),
        ]);

        return response()->json($this->payloads->product($product), 201);
    }

    public function showCustomer(Request $request): JsonResponse
    {
        $store = $this->resolveStore($request);
        if ($store instanceof JsonResponse) {
            return $store;
        }

        $query = Customer::query()->where('store', $store);

        if ($request->filled('customer_id')) {
            $query->where('id', (string) $request->query('customer_id'));
        } elseif ($request->filled('email')) {
            $query->where('email', (string) $request->query('email'));
        }

        $customer = $query->first();

        return $customer
            ? response()->json($this->payloads->customer($customer))
            : response()->json(['message' => 'Customer not found'], 404);
    }

    public function listCustomers(Request $request): JsonResponse
    {
        $store = $this->resolveStore($request);
        if ($store instanceof JsonResponse) {
            return $store;
        }

        $page = max(1, (int) $request->query('page_number', 1));
        $size = max(1, min(100, (int) $request->query('page_size', 20)));

        $query = Customer::query()->where('store', $store)->orderBy('created_at', 'desc');

        return $this->paginatedResponse($query, $page, $size, fn (Customer $customer) => $this->payloads->customer($customer));
    }

    public function createCustomerBillingLink(Request $request): JsonResponse
    {
        $store = $this->resolveStore($request);
        if ($store instanceof JsonResponse) {
            return $store;
        }

        $customer = Customer::query()->where('store', $store)->where('id', (string) $request->input('customer_id'))->first();
        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        return response()->json([
            'customer_portal_link' => rtrim($this->stores->portalBaseUrlForStore($store), '/').'/'.$customer->id,
        ]);
    }

    public function subscriptions(Request $request): JsonResponse
    {
        $store = $this->resolveStore($request);
        if ($store instanceof JsonResponse) {
            return $store;
        }

        if ($request->filled('subscription_id')) {
            $subscription = Subscription::query()->where('store', $store)->where('id', (string) $request->query('subscription_id'))->first();

            return $subscription
                ? response()->json($this->payloads->subscription($subscription))
                : response()->json(['message' => 'Subscription not found'], 404);
        }

        $page = max(1, (int) $request->query('page_number', $request->query('page', 1)));
        $size = max(1, min(100, (int) $request->query('page_size', $request->query('limit', 20))));
        $query = Subscription::query()->where('store', $store)->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', (string) $request->query('customer_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', (string) $request->query('product_id'));
        }

        return $this->paginatedResponse($query, $page, $size, fn (Subscription $subscription) => $this->payloads->subscription($subscription));
    }

    public function cancelSubscription(Request $request, string $subscription): JsonResponse
    {
        return $this->mutateSubscription($request, $subscription, function (Subscription $model): void {
            if ($this->booleanInput($request = request(), 'at_period_end', true)) {
                $model->update(['status' => 'scheduled_cancel', 'canceled_at' => now()]);
                return;
            }

            $model->update(['status' => 'canceled', 'canceled_at' => now()]);
        });
    }

    public function pauseSubscription(Request $request, string $subscription): JsonResponse
    {
        return $this->mutateSubscription($request, $subscription, fn (Subscription $model) => $model->update(['status' => 'paused', 'paused_at' => now()]));
    }

    public function resumeSubscription(Request $request, string $subscription): JsonResponse
    {
        return $this->mutateSubscription($request, $subscription, fn (Subscription $model) => $model->update(['status' => 'active', 'paused_at' => null]));
    }

    public function upgradeSubscription(Request $request, string $subscription): JsonResponse
    {
        return $this->mutateSubscription($request, $subscription, function (Subscription $model) use ($request): void {
            if ($request->filled('product_id')) {
                $model->update(['product_id' => (string) $request->input('product_id')]);
            }
        });
    }

    public function updateSubscription(Request $request, string $subscription): JsonResponse
    {
        return $this->mutateSubscription($request, $subscription, function (Subscription $model) use ($request): void {
            $attributes = array_filter([
                'status' => $request->input('status'),
                'metadata' => $request->input('metadata'),
            ], static fn ($value) => $value !== null);

            if ($attributes !== []) {
                $model->update($attributes);
            }
        });
    }

    public function showTransaction(Request $request): JsonResponse
    {
        $store = $this->resolveStore($request);
        if ($store instanceof JsonResponse) {
            return $store;
        }

        $transaction = Transaction::query()
            ->where('store', $store)
            ->where('id', (string) $request->query('transaction_id'))
            ->first();

        return $transaction
            ? response()->json($this->payloads->transaction($transaction))
            : response()->json(['message' => 'Transaction not found'], 404);
    }

    public function listTransactions(Request $request): JsonResponse
    {
        $store = $this->resolveStore($request);
        if ($store instanceof JsonResponse) {
            return $store;
        }

        $page = max(1, (int) $request->query('page_number', 1));
        $size = max(1, min(100, (int) $request->query('page_size', 20)));
        $query = Transaction::query()->where('store', $store)->orderByDesc('occurred_at');

        if ($request->filled('customer_id')) {
            $query->where('customer_id', (string) $request->query('customer_id'));
        }
        if ($request->filled('product_id')) {
            $query->where('product_id', (string) $request->query('product_id'));
        }
        if ($request->filled('order_id')) {
            $query->where('metadata->order_id', (string) $request->query('order_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        return $this->paginatedResponse($query, $page, $size, fn (Transaction $transaction) => $this->payloads->transaction($transaction));
    }

    public function createCheckout(Request $request): JsonResponse
    {
        $store = $this->resolveStore($request);
        if ($store instanceof JsonResponse) {
            return $store;
        }

        $product = Product::query()->where('store', $store)->where('id', (string) $request->input('product_id'))->first();
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $checkout = Checkout::create([
            'id' => $this->payloads->makeId('chk'),
            'store' => $store,
            'product_id' => $product->id,
            'customer_id' => $request->input('customer_id'),
            'status' => 'open',
            'checkout_url' => rtrim(config('app.url'), '/').'/checkout/'.$this->payloads->makeId('session'),
            'metadata' => $request->input('metadata', []),
        ]);

        return response()->json($this->payloads->checkout($checkout), 201);
    }

    public function showCheckout(Request $request): JsonResponse
    {
        $store = $this->resolveStore($request);
        if ($store instanceof JsonResponse) {
            return $store;
        }

        $checkout = Checkout::query()->where('store', $store)->where('id', (string) $request->query('checkout_id'))->first();

        return $checkout
            ? response()->json($this->payloads->checkout($checkout))
            : response()->json(['message' => 'Checkout not found'], 404);
    }

    private function mutateSubscription(Request $request, string $subscriptionId, callable $callback): JsonResponse
    {
        $store = $this->resolveStore($request);
        if ($store instanceof JsonResponse) {
            return $store;
        }

        $subscription = Subscription::query()->where('store', $store)->where('id', $subscriptionId)->first();
        if (!$subscription) {
            return response()->json(['message' => 'Subscription not found'], 404);
        }

        $callback($subscription);

        return response()->json($this->payloads->subscription($subscription->fresh()));
    }

    private function resolveStore(Request $request): string|JsonResponse
    {
        $store = $this->stores->resolveFromApiKey($request->header('x-api-key'));

        return $store ?: response()->json(['message' => 'Invalid API key'], 401);
    }

    private function paginatedResponse($query, int $page, int $size, callable $mapper): JsonResponse
    {
        $total = (clone $query)->count();
        $items = $query->forPage($page, $size)->get()->map($mapper)->values()->all();
        $totalPages = $size > 0 ? (int) ceil($total / $size) : 0;

        return response()->json([
            'items' => $items,
            'total' => $total,
            'pagination' => [
                'total_records' => $total,
                'total_pages' => $totalPages,
                'current_page' => $page,
                'next_page' => $page < $totalPages ? $page + 1 : null,
                'prev_page' => $page > 1 ? $page - 1 : null,
            ],
        ]);
    }

    private function booleanInput(Request $request, string $key, bool $default = false): bool
    {
        if (!$request->has($key)) {
            return $default;
        }

        return filter_var($request->input($key), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
