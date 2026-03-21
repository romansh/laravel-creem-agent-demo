<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Transaction;
use App\Services\SimulatorScenarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulatorCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SimulatorScenarioService::class)->seedDemo('default', [
            'reset' => true,
            'products' => 4,
            'customers' => 8,
            'subscriptions' => 5,
            'transactions' => 15,
            'days' => 10,
        ]);
    }

    public function test_advance_command_generates_new_entities(): void
    {
        $initialCustomers = Customer::query()->where('store', 'default')->count();
        $initialTransactions = Transaction::query()->where('store', 'default')->count();

        $this->artisan('simulator:advance', [
            '--store' => 'default',
            '--sales' => 2,
            '--new-customers' => 2,
            '--past-due' => 1,
        ])->assertSuccessful();

        $this->assertSame($initialCustomers + 2, Customer::query()->where('store', 'default')->count());
        $this->assertSame($initialTransactions + 2, Transaction::query()->where('store', 'default')->count());
    }
}
