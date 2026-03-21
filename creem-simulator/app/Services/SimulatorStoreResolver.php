<?php

namespace App\Services;

class SimulatorStoreResolver
{
    public function resolveFromApiKey(?string $apiKey): ?string
    {
        if (!$apiKey) {
            return null;
        }

        foreach (config('simulator.stores', []) as $store => $config) {
            if (($config['api_key'] ?? null) === $apiKey) {
                return $store;
            }
        }

        return null;
    }

    public function webhookSecretForStore(string $store): ?string
    {
        return config("simulator.stores.{$store}.webhook_secret");
    }

    public function portalBaseUrlForStore(string $store): string
    {
        return config("simulator.stores.{$store}.portal_base_url", config('app.url'));
    }
}
