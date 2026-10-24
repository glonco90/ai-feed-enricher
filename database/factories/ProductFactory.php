<?php

namespace Database\Factories;

use App\Models\Feed;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sku = strtoupper(fake()->bothify('SKU-####'));

        return [
            'feed_id' => Feed::factory(),
            'sku' => $sku,
            'raw' => [
                'sku' => $sku,
                'name' => 'Trail Runner X2 mens shoe',
                'description' => 'Lightweight trail running shoe, weight 280 g, drop 8 mm.',
                'brand' => 'Stridex',
                'ean' => fake()->ean13(),
                'price' => '119.90',
                'category_hint' => 'Running > Shoes',
                'params' => [
                    ['name' => 'Size', 'value' => '42'],
                    ['name' => 'Material', 'value' => 'mesh'],
                ],
            ],
        ];
    }

    public function enriched(): static
    {
        return $this->state(fn (): array => [
            'status' => Product::REVIEW,
            'ai' => [
                'name' => 'Stridex Trail Runner X2 pánska trailová obuv',
                'short_description' => 'Ľahká pánska bežecká obuv do terénu.',
                'description' => "Ľahká trailová obuv s hmotnosťou 280 g.\n\nDrop 8 mm.",
                'category' => 'beh/obuv',
                'gender' => 'panske',
                'attributes' => [
                    ['name' => 'Hmotnosť', 'value' => '280 g'],
                    ['name' => 'Materiál', 'value' => 'sieťovina'],
                ],
                'seo_title' => 'Stridex Trail Runner X2 pánska trailová obuv',
                'seo_description' => 'Ľahká pánska trailová obuv Stridex Trail Runner X2 s hmotnosťou 280 g.',
                'warnings' => [],
            ],
            'input_tokens' => 1200,
            'output_tokens' => 400,
        ]);
    }

    public function approved(): static
    {
        return $this->enriched()->state(fn (): array => ['status' => Product::APPROVED]);
    }
}
