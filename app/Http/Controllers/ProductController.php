<?php

namespace App\Http\Controllers;

use App\Jobs\EnrichProduct;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function show(Product $product): View
    {
        return view('products.show', [
            'product' => $product,
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'short_description' => ['required', 'string', 'max:300'],
            'description' => ['required', 'string', 'max:2000'],
            'category' => ['required', Rule::in(array_keys(config('enricher.categories')))],
            'gender' => ['required', Rule::in(config('enricher.genders'))],
            'seo_title' => ['required', 'string', 'max:60'],
            'seo_description' => ['required', 'string', 'max:160'],
            'attributes' => ['array'],
            'attributes.*.name' => ['nullable', 'string', 'max:60'],
            'attributes.*.value' => ['nullable', 'string', 'max:120'],
        ]);

        $data['attributes'] = collect($data['attributes'] ?? [])
            ->filter(fn (array $attribute): bool => filled($attribute['name'] ?? null) && filled($attribute['value'] ?? null))
            ->values()
            ->all();
        $data['warnings'] = $product->ai['warnings'] ?? [];

        $product->update(['ai' => $data]);

        if ($request->boolean('approve')) {
            return $this->approve($product);
        }

        return back()->with('status', 'Zmeny sú uložené.');
    }

    public function approve(Product $product): RedirectResponse
    {
        abort_unless($product->ai, 422, 'Produkt ešte nemá AI návrh.');

        $product->update(['status' => Product::APPROVED]);

        return $this->toNext($product, 'Produkt je schválený.');
    }

    public function reject(Product $product): RedirectResponse
    {
        $product->update(['status' => Product::REJECTED]);

        return $this->toNext($product, 'Produkt je zamietnutý.');
    }

    public function retry(Product $product): RedirectResponse
    {
        $product->update(['status' => Product::PENDING]);
        EnrichProduct::dispatch($product);

        return back()->with('status', 'Produkt sa spracuje znova.');
    }

    private function toNext(Product $product, string $message): RedirectResponse
    {
        $next = $product->feed->products()
            ->where('status', Product::REVIEW)
            ->orderBy('id')
            ->first();

        return $next
            ? to_route('products.show', $next)->with('status', $message)
            : to_route('feeds.show', $product->feed)->with('status', $message.' Všetko je skontrolované.');
    }
}
