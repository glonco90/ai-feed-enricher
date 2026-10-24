<?php

namespace App\Services;

/**
 * Kontrola výstupu AI pred tým, než sa dostane k človeku na schválenie.
 *
 * Dôležitá je kontrola halucinácií: každé číslo, ktoré AI uvedie v názve alebo parametroch,
 * musí byť v pôvodných dátach od dodávateľa. AI nesmie vymyslieť hmotnosť ani rozmer.
 */
class ProductValidator
{
    private const LIMITS = [
        'name' => 80,
        'short_description' => 300,
        'description' => 2000,
        'seo_title' => 60,
        'seo_description' => 160,
    ];

    /**
     * @param  array<string, mixed>  $ai
     * @param  array<string, mixed>  $raw
     * @return list<string>
     */
    public function validate(array $ai, array $raw): array
    {
        $errors = [];

        foreach ([...array_keys(self::LIMITS), 'category', 'gender'] as $field) {
            if (! is_string($ai[$field] ?? null) || trim($ai[$field]) === '') {
                $errors[] = "Chýba pole „{$field}“.";
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        foreach (self::LIMITS as $field => $max) {
            $length = mb_strlen($ai[$field]);

            if ($length > $max) {
                $errors[] = "Pole „{$field}“ má {$length} znakov, maximum je {$max}.";
            }
        }

        if (! array_key_exists($ai['category'], config('enricher.categories'))) {
            $errors[] = "Kategória „{$ai['category']}“ nie je v strome kategórií e-shopu.";
        }

        if (! in_array($ai['gender'], config('enricher.genders'), true)) {
            $errors[] = "Neplatná hodnota pohlavia „{$ai['gender']}“.";
        }

        $sourceNumbers = $this->numbers($this->sourceText($raw));

        foreach ([$ai['name'], ...array_column($ai['attributes'] ?? [], 'value')] as $text) {
            foreach ($this->numbers((string) $text) as $number) {
                if (! in_array($number, $sourceNumbers, true)) {
                    $errors[] = "„{$text}“ obsahuje číslo {$number}, ktoré v dátach dodávateľa nie je (možná halucinácia).";
                }
            }
        }

        if (preg_match('/\d+([.,]\d+)?\s*(€|eur)/iu', $ai['name'].' '.$ai['short_description'])) {
            $errors[] = 'Cena nepatrí do názvu ani krátkeho popisu.';
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function sourceText(array $raw): string
    {
        $parts = [$raw['name'] ?? '', $raw['description'] ?? '', $raw['brand'] ?? '', $raw['category_hint'] ?? ''];

        foreach ($raw['params'] ?? [] as $param) {
            $parts[] = $param['name'].' '.$param['value'];
        }

        return implode(' ', $parts);
    }

    /**
     * Čísla z textu v jednotnom tvare: „1,50“ aj „1.5“ → „1.5“.
     *
     * @return list<string>
     */
    private function numbers(string $text): array
    {
        preg_match_all('/\d+(?:[.,]\d+)?/u', $text, $matches);

        return array_values(array_unique(array_map(
            fn (string $number): string => (string) (float) str_replace(',', '.', $number),
            $matches[0],
        )));
    }
}
