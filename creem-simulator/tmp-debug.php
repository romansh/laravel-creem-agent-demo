<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(["simulator.agent.webhook_url" => "https://agent.test/creem/webhook", "simulator.agent.webhook_secret" => null, "simulator.agent.auto_send_webhooks" => false]);
$service = app(App\Services\SimulatorScenarioService::class);
$service->seedDemo("default", ['reset' => true, 'products' => 4, 'customers' => 8, 'subscriptions' => 6, 'transactions' => 10, 'days' => 12]);
$service->seedDemo("secondary", ['reset' => true, 'products' => 3, 'customers' => 6, 'subscriptions' => 4, 'transactions' => 7, 'days' => 12]);
$res = $service->advance("secondary", ['sales' => 2, 'new_customers' => 1, 'past_due' => 1, 'cancellations' => 1, 'send_webhooks' => true]);
echo "Events:\n";
print_r($res['events']);
echo "\nDeliveries count: ".count($res['deliveries'])."\n";
foreach ($res['deliveries'] as $i => $d) {
    echo "Delivery #".($i+1)." => ";
    print_r($d);
}
