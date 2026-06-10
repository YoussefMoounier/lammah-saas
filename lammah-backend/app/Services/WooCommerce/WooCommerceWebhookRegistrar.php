<?php

namespace App\Services\WooCommerce;

use App\Models\WooCommerceStore;
use App\Services\WooCommerce\Contracts\WooCommerceClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class WooCommerceWebhookRegistrar
{
    private const TOPICS = [
        'order.created',
        'order.updated',
        'order.deleted',
        'product.created',
        'product.updated',
        'product.deleted',
        'customer.created',
        'customer.updated',
        'customer.deleted',
    ];

    public function __construct(private readonly WooCommerceClient $client)
    {
    }

    public function ensureDefaultWebhooks(WooCommerceStore $store, ?string $deliveryUrl = null): array
    {
        $deliveryUrl ??= $this->deliveryUrl($store);

        if ($store->webhookSecret() === null) {
            $store->forceFill(['webhook_secret_encrypted' => Str::random(48)])->save();
            $store->refresh();
        }

        $existing = collect($this->client->get($store, 'webhooks', ['per_page' => 100]));
        $registered = [];

        foreach (self::TOPICS as $topic) {
            $webhook = $existing->first(fn (array $candidate): bool => (
                Arr::get($candidate, 'topic') === $topic
                && Arr::get($candidate, 'delivery_url') === $deliveryUrl
            ));

            if ($webhook === null) {
                $webhook = $this->client->post($store, 'webhooks', [
                    'name' => "Lammah SaaS {$topic}",
                    'topic' => $topic,
                    'delivery_url' => $deliveryUrl,
                    'secret' => $store->webhookSecret(),
                    'status' => 'active',
                ]);
            }

            $registered[$topic] = [
                'id' => Arr::get($webhook, 'id'),
                'delivery_url' => $deliveryUrl,
                'status' => Arr::get($webhook, 'status', 'active'),
            ];
        }

        $metadata = $store->metadata ?? [];
        $metadata['webhooks'] = $registered;
        $metadata['webhooks_registered_at'] = now()->toIso8601String();

        $store->forceFill(['metadata' => $metadata])->save();

        return $registered;
    }

    private function deliveryUrl(WooCommerceStore $store): string
    {
        return rtrim((string) config('app.url'), '/') . "/api/webhooks/woocommerce/{$store->id}";
    }
}
