<?php

namespace App\Console\Commands;

use App\Services\SimulatorScenarioService;
use Illuminate\Console\Command;

class SeedDemoCommand extends Command
{
    protected $signature = 'simulator:seed-demo
        {--store=default : Store name from config/simulator.php}
        {--days=45 : How many historical days to generate}
        {--products=6 : Number of products}
        {--customers=40 : Number of customers}
        {--subscriptions=24 : Number of subscriptions}
        {--transactions=120 : Number of transactions}
        {--reset : Reset simulator data for the selected store before seeding}';

    protected $description = 'Seed the Creem simulator with realistic demo data';

    public function handle(SimulatorScenarioService $service): int
    {
        $result = $service->seedDemo((string) $this->option('store'), [
            'days' => (int) $this->option('days'),
            'products' => (int) $this->option('products'),
            'customers' => (int) $this->option('customers'),
            'subscriptions' => (int) $this->option('subscriptions'),
            'transactions' => (int) $this->option('transactions'),
            'reset' => (bool) $this->option('reset'),
        ]);

        $this->table(['Store', 'Products', 'Customers', 'Subscriptions', 'Transactions'], [[
            $result['store'],
            $result['products'],
            $result['customers'],
            $result['subscriptions'],
            $result['transactions'],
        ]]);

        return self::SUCCESS;
    }
}
