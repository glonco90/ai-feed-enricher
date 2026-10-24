<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Pošle surový produkt do Claude API a vráti štruktúrovaný slovenský obsah.
 *
 * Výstup je vynútený cez tool use (pevná JSON schéma). Ak validátor výstup odmietne,
 * chyby sa vrátia modelu ako tool_result a model dostane ďalší pokus.
 */
class ProductEnricher
{
    private const TOOL = 'save_product';

    public function __construct(private ProductValidator $validator) {}

    /**
     * @param  array<string, mixed>  $raw
     * @return array{ai: array<string, mixed>|null, errors: list<string>, attempts: int, rejected: int, input_tokens: int, output_tokens: int}
     */
    public function enrich(array $raw): array
    {
        $result = ['ai' => null, 'errors' => [], 'attempts' => 0, 'rejected' => 0, 'input_tokens' => 0, 'output_tokens' => 0];

        $messages = [[
            'role' => 'user',
            'content' => "Spracuj tento produkt od dodávateľa:\n\n".json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ]];

        $maxAttempts = (int) config('enricher.max_attempts');

        while ($result['attempts'] < $maxAttempts) {
            $result['attempts']++;

            $response = $this->call($messages);
            $result['input_tokens'] += (int) ($response['usage']['input_tokens'] ?? 0);
            $result['output_tokens'] += (int) ($response['usage']['output_tokens'] ?? 0);

            $toolUse = collect($response['content'] ?? [])->firstWhere('type', 'tool_use');

            if ($toolUse === null) {
                $result['errors'] = ['Model nevrátil štruktúrovaný výstup.'];

                return $result;
            }

            $errors = $this->validator->validate($toolUse['input'], $raw);

            if ($errors === []) {
                $result['ai'] = $toolUse['input'];
                $result['errors'] = [];

                return $result;
            }

            $result['rejected']++;
            $result['errors'] = $errors;

            $messages[] = ['role' => 'assistant', 'content' => $response['content']];
            $messages[] = ['role' => 'user', 'content' => [[
                'type' => 'tool_result',
                'tool_use_id' => $toolUse['id'],
                'is_error' => true,
                'content' => "Výstup neprešiel kontrolou. Oprav tieto chyby a zavolaj nástroj znova:\n- ".implode("\n- ", $errors),
            ]]];
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @return array<string, mixed>
     */
    private function call(array $messages): array
    {
        $config = config('enricher.anthropic');

        if (blank($config['key'])) {
            throw new RuntimeException('Chýba ANTHROPIC_API_KEY v .env.');
        }

        try {
            return Http::withHeaders([
                'x-api-key' => $config['key'],
                'anthropic-version' => $config['version'],
            ])
                ->timeout(60)
                ->retry(3, 2000, fn (Throwable $e): bool => $e instanceof ConnectionException
                    || ($e instanceof RequestException && in_array($e->response->status(), [429, 500, 502, 503, 529], true)))
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $config['model'],
                    'max_tokens' => $config['max_tokens'],
                    'system' => $this->systemPrompt(),
                    'tools' => [$this->tool()],
                    'tool_choice' => ['type' => 'tool', 'name' => self::TOOL],
                    'messages' => $messages,
                ])
                ->throw()
                ->json();
        } catch (RequestException $e) {
            throw new RuntimeException('Claude API vrátilo chybu '.$e->response->status().': '.$e->response->json('error.message', ''), previous: $e);
        }
    }

    private function systemPrompt(): string
    {
        $categories = collect(config('enricher.categories'))
            ->map(fn (string $label, string $key): string => "- {$key}: {$label}")
            ->implode("\n");

        return <<<PROMPT
        Si editor produktového obsahu slovenského športového e-shopu.
        Dostaneš surové dáta od dodávateľa (často po anglicky alebo česky, neúplné, s kódmi namiesto slov).
        Vytvor z nich obsah pripravený na e-shop a ulož ho nástrojom save_product.

        Pravidlá:
        - Píš spisovnou slovenčinou, vecne, bez marketingových superlatívov.
        - Používaj LEN fakty z dát dodávateľa. Nevymýšľaj čísla (hmotnosť, rozmery, objem), materiály ani technológie.
        - Ak niečo dôležité v dátach chýba alebo je nejasné, napíš to do warnings, nedopĺňaj to.
        - Názov: značka + model + typ produktu po slovensky, bez ceny a bez veľkosti.
        - Kategóriu vyber len z tohto zoznamu (použi kľúč pred dvojbodkou). Ak žiadna nesedí, použi „ine“:
        {$categories}
        - Parametre (attributes) pomenuj po slovensky (Materiál, Hmotnosť, Veľkosť, Farba…) a hodnoty prelož, čísla a jednotky zachovaj.
        - SEO titulok do 60 znakov, SEO popis do 160 znakov.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function tool(): array
    {
        return [
            'name' => self::TOOL,
            'description' => 'Uloží spracovaný produkt do e-shopu.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Názov produktu, max 80 znakov.'],
                    'short_description' => ['type' => 'string', 'description' => 'Krátky popis, 1–2 vety, max 300 znakov.'],
                    'description' => ['type' => 'string', 'description' => 'Dlhý popis, 2–4 odseky oddelené prázdnym riadkom, max 2000 znakov.'],
                    'category' => ['type' => 'string', 'enum' => array_keys(config('enricher.categories'))],
                    'gender' => ['type' => 'string', 'enum' => config('enricher.genders')],
                    'attributes' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'value' => ['type' => 'string'],
                            ],
                            'required' => ['name', 'value'],
                        ],
                    ],
                    'seo_title' => ['type' => 'string', 'description' => 'Max 60 znakov.'],
                    'seo_description' => ['type' => 'string', 'description' => 'Max 160 znakov.'],
                    'warnings' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Čo v dátach chýba alebo je nejasné a mal by to skontrolovať človek.',
                    ],
                ],
                'required' => ['name', 'short_description', 'description', 'category', 'gender', 'attributes', 'seo_title', 'seo_description', 'warnings'],
            ],
        ];
    }
}
