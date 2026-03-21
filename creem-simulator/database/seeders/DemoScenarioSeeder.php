<?php

namespace Database\Seeders;

use App\Services\SimulatorScenarioService;
use Illuminate\Database\Seeder;

class DemoScenarioSeeder extends Seeder
{
    public function run(): void
    {
        /** @var SimulatorScenarioService $service */
        $service = app(SimulatorScenarioService::class);

        foreach (array_keys(config('simulator.stores', [])) as $store) {
            $service->seedDemo($store, [
                'reset' => true,
                'days' => 45,
                'products' => 6,
                'customers' => 40,
                'subscriptions' => 24,
                'transactions' => 120,
            ]);
        }
    }
}
