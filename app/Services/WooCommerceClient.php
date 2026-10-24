<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Zápis schválených produktov do WooCommerce cez REST API (wc/v3).
 *
 * Autentifikácia cez WordPress Application Password, kategórie sa podľa názvu
 * dohľadajú alebo založia (vrátane nadradenej kategórie).
 */
class WooCommerceClient
{
    /** @var array<string, int> */
    private array $categoryIds = [];

    public function isConfigured(): bool
    {
        $config = config('enricher.woocommerce');

        return filled($config['url']) && filled($config['user']) && filled($config['app_password']);
    }

    public function push(Product $product): int
    {
        $ai = $product->ai;
        $raw = $product->raw;

        $payload = [
            'name' => $ai['name'],
            'type' => 'simple',
            'status' => 'draft',
            'sku' => $product->sku,
            'regular_price' => (string) $raw['price'],
            'short_description' => $ai['short_description'],
            'description' => $this->paragraphs($ai['description']),
            'categories' => [['id' => $this->categoryId($ai['category'])]],
            'attributes' => collect($ai['attributes'] ?? [])
                ->map(fn (array $attribute): array => [
                    'name' => $attribute['name'],
                    'options' => [$attribute['value']],
                    'visible' => true,
                ])
                ->values()
                ->all(),
            'meta_data' => [
                ['key' => 'seo_title', 'value' => $ai['seo_title']],
                ['key' => 'seo_description', 'value' => $ai['seo_description']],
                ['key' => 'ean', 'value' => $raw['ean'] ?? ''],
            ],
        ];

        $response = $product->woo_id
            ? $this->request()->put("products/{$product->woo_id}", $payload)
            : $this->request()->post('products', $payload);

        if ($response->failed()) {
            throw new RuntimeException('WooCommerce odmietol produkt '.$product->sku.': '.$response->json('message', $response->status()));
        }

        return (int) $response->json('id');
    }

    private function categoryId(string $key): int
    {
        if (isset($this->categoryIds[$key])) {
            return $this->categoryIds[$key];
        }

        $parentId = 0;

        foreach (explode(' › ', config('enricher.categories')[$key] ?? $key) as $name) {
            $existing = collect($this->request()->get('products/categories', ['search' => $name, 'per_page' => 100])->json())
                ->first(fn (array $category): bool => html_entity_decode($category['name']) === $name && $category['parent'] === $parentId);

            $parentId = $existing['id'] ?? (int) $this->request()
                ->post('products/categories', ['name' => $name, 'parent' => $parentId])
                ->throw()
                ->json('id');
        }

        return $this->categoryIds[$key] = $parentId;
    }

    private function paragraphs(string $text): string
    {
        return collect(preg_split('/\n\s*\n/', trim($text)))
            ->map(fn (string $paragraph): string => '<p>'.e(trim($paragraph)).'</p>')
            ->implode("\n");
    }

    private function request(): PendingRequest
    {
        $config = config('enricher.woocommerce');

        return Http::baseUrl(rtrim($config['url'], '/').'/wp-json/wc/v3/')
            ->withBasicAuth($config['user'], $config['app_password'])
            ->acceptJson()
            ->timeout(30);
    }
}
