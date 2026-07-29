<?php

namespace App\Enums;

enum IntegrationProvider: string
{
    case Shopify = 'shopify';

    public function label(): string
    {
        return match ($this) {
            self::Shopify => 'Shopify',
        };
    }

    /**
     * The credential fields this provider requires when connecting.
     *
     * @return array<string>
     */
    public function credentialFields(): array
    {
        return match ($this) {
            // api_secret signs webhook payloads (the custom app's API secret key).
            self::Shopify => ['shop_domain', 'access_token', 'api_secret'],
        };
    }
}
