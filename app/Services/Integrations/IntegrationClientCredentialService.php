<?php

namespace App\Services\Integrations;

use App\Models\IntegrationClientCredential;

class IntegrationClientCredentialService
{
    public function resolve(string $sourceModule, string $clientKey, string $clientSecret): ?IntegrationClientCredential
    {
        $credential = IntegrationClientCredential::query()
            ->where('source_module', $sourceModule)
            ->where('client_key', $clientKey)
            ->where('is_active', true)
            ->first();

        if (! $credential) {
            return null;
        }

        if (! hash_equals((string) $credential->client_secret_hash, hash('sha256', $clientSecret))) {
            return null;
        }

        $credential->update(['last_used_at' => now()]);

        return $credential;
    }
}
