<?php

namespace App\Console\Commands;

use App\Services\SimulatorScenarioService;
use Illuminate\Console\Command;

class AdvanceScenarioCommand extends Command
{
    protected $signature = 'simulator:advance
        {--store=default : Store name from config/simulator.php}
        {--sales=3 : Number of new successful transactions}
        {--new-customers=1 : Number of new customers}
        {--past-due=1 : Number of subscriptions to move to past_due}
        {--cancellations=0 : Number of subscriptions to cancel}
        {--paused=0 : Number of subscriptions to pause}
        {--resumed=0 : Number of subscriptions to resume}
        {--send-webhooks : Immediately send generated events to the configured agent webhook URL}';

    protected $description = 'Advance simulator state so the next heartbeat has fresh changes to detect';

    public function handle(SimulatorScenarioService $service): int
    {
        $result = $service->advance((string) $this->option('store'), [
            'sales' => (int) $this->option('sales'),
            'new-customers' => (int) $this->option('new-customers'),
            'past-due' => (int) $this->option('past-due'),
            'cancellations' => (int) $this->option('cancellations'),
            'paused' => (int) $this->option('paused'),
            'resumed' => (int) $this->option('resumed'),
            'send-webhooks' => (bool) $this->option('send-webhooks'),
        ]);

        $this->table(['Store', 'New customers', 'New transactions'], [[
            $result['store'],
            $result['new_customers'],
            $result['new_transactions'],
        ]]);

        if ($result['events'] !== []) {
            $this->line('Generated events: '.implode(', ', $result['events']));
        }

        if ($result['deliveries'] !== []) {
            $this->line('Webhook deliveries:');
            foreach ($result['deliveries'] as $delivery) {
                $this->line(' - '.json_encode($delivery, JSON_UNESCAPED_SLASHES));
            }
        }

        return self::SUCCESS;
    }
}
