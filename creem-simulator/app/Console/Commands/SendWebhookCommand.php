<?php

namespace App\Console\Commands;

use App\Services\SimulatorScenarioService;
use Illuminate\Console\Command;

class SendWebhookCommand extends Command
{
    protected $signature = 'simulator:send-webhook
        {event : Creem event type, for example payment.failed or subscription.canceled}
        {--store=default : Store name from config/simulator.php}
        {--subscription= : Specific subscription id}
        {--transaction= : Specific transaction id}
        {--checkout= : Specific checkout id}';

    protected $description = 'Send a signed webhook from the simulator to the configured agent app';

    public function handle(SimulatorScenarioService $service): int
    {
        $result = $service->sendWebhook((string) $this->argument('event'), (string) $this->option('store'), [
            'subscription' => $this->option('subscription'),
            'transaction' => $this->option('transaction'),
            'checkout' => $this->option('checkout'),
        ]);

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return ($result['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
