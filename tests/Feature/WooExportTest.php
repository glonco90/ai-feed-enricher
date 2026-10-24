<?php

namespace Tests\Feature;

use App\Models\Feed;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WooExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['enricher.woocommerce' => [
            'url' => 'https://shop.test',
            'user' => 'admin',
            'app_password' => 'xxxx',
        ]]);
    }

    public function test_approved_products_are_pushed_as_drafts_with_category_tree(): void
    {
        $feed = Feed::factory()->create();
        $product = Product::factory()->for($feed)->approved()->create();
        Product::factory()->for($feed)->enriched()->create();

        $nextCategoryId = 10;

        Http::fake(function (Request $request) use (&$nextCategoryId) {
            if ($request->method() === 'GET' && str_contains($request->url(), 'products/categories')) {
                return Http::response([]);
            }

            if ($request->method() === 'POST' && str_contains($request->url(), 'products/categories')) {
                return Http::response(['id' => $nextCategoryId++]);
            }

            return Http::response(['id' => 501], 201);
        });

        $this->post(route('feeds.export.woo', $feed))->assertRedirect()->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame(Product::EXPORTED, $product->status);
        $this->assertSame(501, $product->woo_id);

        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), 'products/categories') && $r['name'] === 'Beh' && $r['parent'] === 0);
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), 'products/categories') && $r['name'] === 'Obuv' && $r['parent'] === 10);
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), 'wc/v3/products')
            && $r['status'] === 'draft'
            && $r['categories'] === [['id' => 11]]
            && $r['regular_price'] === '119.90'
            && $r->hasHeader('Authorization'));
        Http::assertSentCount(5);
    }

    public function test_push_is_refused_without_configuration(): void
    {
        config(['enricher.woocommerce.url' => null]);
        Http::fake();
        $feed = Feed::factory()->create();
        Product::factory()->for($feed)->approved()->create();

        $this->post(route('feeds.export.woo', $feed))->assertSessionHasErrors('woo');

        Http::assertNothingSent();
    }

    public function test_failed_product_is_reported_and_stays_approved(): void
    {
        $feed = Feed::factory()->create();
        $product = Product::factory()->for($feed)->approved()->create();

        Http::fake([
            '*/products/categories*' => Http::response(['id' => 7]),
            '*/products' => Http::response(['message' => 'Neplatné SKU.'], 400),
        ]);

        $this->post(route('feeds.export.woo', $feed))->assertSessionHasErrors('woo');

        $this->assertSame(Product::APPROVED, $product->refresh()->status);
    }
}
