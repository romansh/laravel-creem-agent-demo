<?php

namespace Tests\Feature;

use App\Services\SimulatorScenarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulatorApiErrorsTest extends TestCase
{
    use RefreshDatabase;

    private string $apiKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiKey = config('simulator.stores.default.api_key');

        app(SimulatorScenarioService::class)->seedDemo('default', [
            'reset' => true,
            'products' => 2,
            'customers' => 3,
            'subscriptions' => 2,
            'transactions' => 5,
            'days' => 10,
        ]);
    }

    private function apiHeaders(): array
    {
        return ['x-api-key' => $this->apiKey];
    }

    public function test_products_search_returns_401_without_api_key(): void
    {
        $this->getJson('/api/v1/products/search')->assertStatus(401);
    }

    public function test_products_search_returns_401_with_wrong_api_key(): void
    {
        $this->withHeaders(['x-api-key' => 'wrong_key'])
            ->getJson('/api/v1/products/search')
            ->assertStatus(401);
    }

    public function test_show_product_returns_404_for_nonexistent_id(): void
    {
        $this->withHeaders($this->apiHeaders())
            ->getJson('/api/v1/products?product_id=nonexistent_prod_999')
            ->assertStatus(404);
    }

    public function test_show_customer_returns_404_for_nonexistent_id(): void
    {
        $this->withHeaders($this->apiHeaders())
            ->getJson('/api/v1/customers?customer_id=nonexistent_cust_999')
            ->assertStatus(404);
    }

    public function test_show_transaction_returns_404_for_nonexistent_id(): void
    {
        $this->withHeaders($this->apiHeaders())
            ->getJson('/api/v1/transactions?transaction_id=nonexistent_txn_999')
            ->assertStatus(404);
    }

    public function test_pagination_beyond_last_page_returns_empty_items(): void
    {
        $response = $this->withHeaders($this->apiHeaders())
            ->getJson('/api/v1/products/search?page_number=999&page_size=10');

        $response->assertOk()
            ->assertJsonPath('items', [])
            ->assertJsonPath('pagination.current_page', 999);
    }

    public function test_cancel_nonexistent_subscription_returns_404(): void
    {
        $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/subscriptions/sub_nonexistent/cancel')
            ->assertStatus(404);
    }
}
