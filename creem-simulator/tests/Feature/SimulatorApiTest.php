<?php

namespace Tests\Feature;

use App\Services\SimulatorScenarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulatorApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SimulatorScenarioService::class)->seedDemo('default', [
            'reset' => true,
            'products' => 4,
            'customers' => 10,
            'subscriptions' => 6,
            'transactions' => 20,
            'days' => 20,
        ]);
    }

    public function test_products_search_requires_api_key_and_returns_paginated_items(): void
    {
        $this->getJson('/api/v1/products/search')->assertStatus(401);

        $response = $this->withHeaders([
            'x-api-key' => config('simulator.stores.default.api_key'),
        ])->getJson('/api/v1/products/search?page_number=1&page_size=2');

        $response->assertOk()
            ->assertJsonPath('pagination.current_page', 1)
            ->assertJsonPath('pagination.total_records', 4)
            ->assertJsonCount(2, 'items');
    }

    public function test_subscriptions_can_be_filtered_by_status(): void
    {
        $response = $this->withHeaders([
            'x-api-key' => config('simulator.stores.default.api_key'),
        ])->getJson('/api/v1/subscriptions/search?status=past_due&page_size=100');

        $response->assertOk()
            ->assertJsonStructure([
                'items',
                'pagination' => ['total_records', 'total_pages', 'current_page', 'next_page', 'prev_page'],
            ]);

        foreach ($response->json('items') as $item) {
            $this->assertSame('past_due', $item['status']);
        }
    }

    public function test_transactions_search_returns_creem_pagination_and_supports_filters(): void
    {
        $customerId = (string) $this->withHeaders([
            'x-api-key' => config('simulator.stores.default.api_key'),
        ])->getJson('/api/v1/transactions/search?page_number=1&page_size=1')
            ->json('items.0.customer_id');

        $response = $this->withHeaders([
            'x-api-key' => config('simulator.stores.default.api_key'),
        ])->getJson('/api/v1/transactions/search?customer_id='.$customerId.'&status=paid&page_number=1&page_size=20');

        $response->assertOk()
            ->assertJsonStructure([
                'items',
                'pagination' => ['total_records', 'total_pages', 'current_page', 'next_page', 'prev_page'],
            ]);

        foreach ($response->json('items') as $item) {
            $this->assertSame($customerId, $item['customer_id']);
            $this->assertSame('paid', $item['status']);
        }
    }
}
