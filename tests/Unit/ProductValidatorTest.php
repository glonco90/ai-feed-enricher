<?php

namespace Tests\Unit;

use App\Services\ProductValidator;
use Tests\TestCase;

class ProductValidatorTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function raw(): array
    {
        return [
            'name' => 'Alpenwerk Hiker 30 batoh',
            'description' => 'Turistický batoh s objemem 30 l. Hmotnost 1,15 kg.',
            'brand' => 'Alpenwerk',
            'category_hint' => 'Outdoor > Batohy',
            'params' => [['name' => 'objem', 'value' => '30 l']],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function ai(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Alpenwerk Hiker 30 turistický batoh',
            'short_description' => 'Turistický batoh s objemom 30 l.',
            'description' => 'Batoh na celodenné túry.',
            'category' => 'turistika/batohy',
            'gender' => 'unisex',
            'attributes' => [
                ['name' => 'Objem', 'value' => '30 l'],
                ['name' => 'Hmotnosť', 'value' => '1.15 kg'],
            ],
            'seo_title' => 'Alpenwerk Hiker 30 turistický batoh',
            'seo_description' => 'Turistický batoh Alpenwerk Hiker 30 s objemom 30 l.',
            'warnings' => [],
        ], $overrides);
    }

    public function test_valid_output_passes_and_decimal_comma_matches_dot(): void
    {
        $this->assertSame([], (new ProductValidator)->validate($this->ai(), $this->raw()));
    }

    public function test_it_catches_a_hallucinated_number(): void
    {
        $errors = (new ProductValidator)->validate($this->ai([
            'attributes' => [['name' => 'Hmotnosť', 'value' => '980 g']],
        ]), $this->raw());

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('980', $errors[0]);
        $this->assertStringContainsString('halucinácia', $errors[0]);
    }

    public function test_it_rejects_unknown_category_long_fields_and_price_in_name(): void
    {
        $errors = (new ProductValidator)->validate($this->ai([
            'category' => 'outdoor/batohy',
            'seo_title' => str_repeat('a', 61),
            'short_description' => 'Teraz len za 30 €.',
        ]), $this->raw());

        $this->assertCount(3, $errors);
    }

    public function test_missing_fields_are_reported_first(): void
    {
        $errors = (new ProductValidator)->validate(['name' => 'X'], $this->raw());

        $this->assertContains('Chýba pole „category“.', $errors);
    }
}
