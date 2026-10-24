<?php

namespace Tests\Feature;

use App\Services\ProductEnricher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ProductEnricherTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function raw(): array
    {
        return [
            'sku' => 'KRF-KB-16',
            'name' => 'Kraftfeld kettlebell 16kg',
            'description' => 'Cast iron kettlebell with powder coat finish.',
            'brand' => 'Kraftfeld',
            'ean' => '8590000000109',
            'price' => '54.00',
            'category_hint' => 'Fitness',
            'params' => [['name' => 'weight', 'value' => '16 kg']],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function toolResponse(array $input, string $id = 'toolu_1'): array
    {
        return [
            'content' => [['type' => 'tool_use', 'id' => $id, 'name' => 'save_product', 'input' => array_merge([
                'name' => 'Kraftfeld kettlebell 16 kg',
                'short_description' => 'Liatinový kettlebell s práškovou povrchovou úpravou.',
                'description' => 'Kettlebell z liatiny.',
                'category' => 'fitness/naradie',
                'gender' => 'unisex',
                'attributes' => [['name' => 'Hmotnosť', 'value' => '16 kg']],
                'seo_title' => 'Kraftfeld kettlebell 16 kg',
                'seo_description' => 'Liatinový kettlebell Kraftfeld s hmotnosťou 16 kg.',
                'warnings' => [],
            ], $input)]],
            'usage' => ['input_tokens' => 1000, 'output_tokens' => 300],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['enricher.anthropic.key' => 'test-key']);
    }

    public function test_valid_output_is_returned_after_one_call(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->toolResponse([]))]);

        $result = app(ProductEnricher::class)->enrich($this->raw());

        $this->assertSame('fitness/naradie', $result['ai']['category']);
        $this->assertSame(1, $result['attempts']);
        $this->assertSame(0, $result['rejected']);

        Http::assertSent(function (Request $request): bool {
            return $request->hasHeader('x-api-key', 'test-key')
                && $request['tool_choice'] === ['type' => 'tool', 'name' => 'save_product']
                && in_array('fitness/naradie', $request['tools'][0]['input_schema']['properties']['category']['enum'], true);
        });
    }

    public function test_hallucination_is_sent_back_to_the_model_and_corrected(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->push($this->toolResponse(['attributes' => [['name' => 'Priemer rukoväte', 'value' => '35 mm']]], 'toolu_bad'))
            ->push($this->toolResponse([], 'toolu_good')),
        ]);

        $result = app(ProductEnricher::class)->enrich($this->raw());

        $this->assertNotNull($result['ai']);
        $this->assertSame(2, $result['attempts']);
        $this->assertSame(1, $result['rejected']);
        $this->assertSame(2000, $result['input_tokens']);

        Http::assertSentCount(2);
        Http::assertSent(function (Request $request): bool {
            $messages = $request['messages'];
            $last = end($messages);

            return ($last['content'][0]['type'] ?? null) === 'tool_result'
                && $last['content'][0]['tool_use_id'] === 'toolu_bad'
                && $last['content'][0]['is_error'] === true
                && str_contains($last['content'][0]['content'], '35');
        });
    }

    public function test_it_gives_up_after_max_attempts(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->toolResponse(['category' => 'neexistuje']))]);

        $result = app(ProductEnricher::class)->enrich($this->raw());

        $this->assertNull($result['ai']);
        $this->assertSame(2, $result['attempts']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_missing_api_key_fails_loudly(): void
    {
        config(['enricher.anthropic.key' => null]);
        Http::fake();

        $this->expectException(RuntimeException::class);

        app(ProductEnricher::class)->enrich($this->raw());
    }
}
