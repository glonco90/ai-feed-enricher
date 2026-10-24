<?php

namespace Tests\Feature;

use App\Jobs\EnrichProduct;
use App\Models\Feed;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FeedWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_feed_is_imported(): void
    {
        $this->post(route('feeds.store'), ['sample' => 1])->assertRedirect();

        $feed = Feed::sole();
        $this->assertSame(14, $feed->products()->count());
        $this->get(route('feeds.show', $feed))->assertOk()->assertSee('STRIDEX TRAIL RUNNER X2');
    }

    public function test_cost_is_shown_only_when_prices_are_configured(): void
    {
        $feed = Feed::factory()->create();
        Product::factory()->for($feed)->enriched()->create();

        config(['enricher.anthropic.price_input' => '', 'enricher.anthropic.price_output' => '']);
        $this->assertNull($feed->stats()['cost']);
        $this->get(route('feeds.show', $feed))->assertOk()->assertSee('Tokeny');

        config(['enricher.anthropic.price_input' => '1', 'enricher.anthropic.price_output' => '5']);
        $this->assertSame(0.0032, $feed->stats()['cost']);
    }

    public function test_uploaded_csv_is_imported_and_invalid_file_is_rejected(): void
    {
        $csv = UploadedFile::fake()->createWithContent('dodavatel.csv', "sku,name,price\nA-1,Shirt,10\n");
        $this->post(route('feeds.store'), ['feed' => $csv])->assertRedirect();
        $this->assertSame(1, Product::count());

        $pdf = UploadedFile::fake()->create('feed.pdf', 10);
        $this->post(route('feeds.store'), ['feed' => $pdf])->assertSessionHasErrors('feed');
    }

    public function test_process_queues_pending_and_failed_products(): void
    {
        Queue::fake();
        $feed = Feed::factory()->create();
        Product::factory()->for($feed)->count(2)->create();
        Product::factory()->for($feed)->create(['status' => Product::FAILED]);
        Product::factory()->for($feed)->enriched()->create();

        $this->post(route('feeds.process', $feed))->assertRedirect();

        Queue::assertPushed(EnrichProduct::class, 3);
    }

    public function test_job_stores_ai_output_and_token_usage(): void
    {
        config(['enricher.anthropic.key' => 'test-key']);
        $product = Product::factory()->create();
        $ai = Product::factory()->enriched()->make()->ai;

        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'tool_use', 'id' => 't1', 'name' => 'save_product', 'input' => $ai]],
            'usage' => ['input_tokens' => 900, 'output_tokens' => 250],
        ])]);

        EnrichProduct::dispatchSync($product);

        $product->refresh();
        $this->assertSame(Product::REVIEW, $product->status);
        $this->assertSame('beh/obuv', $product->ai['category']);
        $this->assertSame(900, $product->input_tokens);
    }

    public function test_job_marks_product_failed_when_api_errors(): void
    {
        config(['enricher.anthropic.key' => 'test-key']);
        $product = Product::factory()->create();
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'invalid x-api-key']], 401)]);

        EnrichProduct::dispatchSync($product);

        $product->refresh();
        $this->assertSame(Product::FAILED, $product->status);
        $this->assertStringContainsString('401', $product->errors[0]);
    }

    public function test_reviewer_can_edit_and_approve(): void
    {
        $product = Product::factory()->enriched()->create();
        $data = array_merge($product->ai, ['name' => 'Upravený názov']);

        $this->put(route('products.update', $product), [...$data, 'approve' => 1])->assertRedirect();

        $product->refresh();
        $this->assertSame(Product::APPROVED, $product->status);
        $this->assertSame('Upravený názov', $product->ai['name']);
    }

    public function test_reviewer_cannot_save_category_outside_the_tree(): void
    {
        $product = Product::factory()->enriched()->create();

        $this->put(route('products.update', $product), array_merge($product->ai, ['category' => 'zle']))
            ->assertSessionHasErrors('category');
    }

    public function test_csv_export_contains_only_approved_products(): void
    {
        $feed = Feed::factory()->create();
        Product::factory()->for($feed)->approved()->create(['sku' => 'OK-1']);
        Product::factory()->for($feed)->enriched()->create(['sku' => 'WAIT-1']);

        $csv = $this->get(route('feeds.export.csv', $feed))->assertOk()->getContent();

        $this->assertStringContainsString('Attribute 1 name', $csv);
        $this->assertStringContainsString('OK-1', $csv);
        $this->assertStringContainsString('Beh > Obuv', $csv);
        $this->assertStringNotContainsString('WAIT-1', $csv);
    }
}
