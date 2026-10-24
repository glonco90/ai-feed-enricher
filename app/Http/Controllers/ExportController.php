<?php

namespace App\Http\Controllers;

use App\Models\Feed;
use App\Models\Product;
use App\Services\WooCommerceClient;
use App\Services\WooCsvExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ExportController extends Controller
{
    public function csv(Feed $feed, WooCsvExporter $exporter): Response
    {
        $products = $feed->products()->whereIn('status', [Product::APPROVED, Product::EXPORTED])->orderBy('id')->get();

        abort_if($products->isEmpty(), 422, 'Nie je schválený žiadny produkt.');

        return response($exporter->export($products), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="woocommerce-'.Str::slug($feed->name).'.csv"',
        ]);
    }

    public function push(Feed $feed, WooCommerceClient $client): RedirectResponse
    {
        if (! $client->isConfigured()) {
            return back()->withErrors(['woo' => 'WooCommerce nie je nastavený (WOO_URL, WOO_USER, WOO_APP_PASSWORD v .env).']);
        }

        $products = $feed->products()->where('status', Product::APPROVED)->orderBy('id')->get();
        $errors = [];

        foreach ($products as $product) {
            try {
                $product->update([
                    'woo_id' => $client->push($product),
                    'status' => Product::EXPORTED,
                ]);
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        $sent = $products->count() - count($errors);
        $redirect = back()->with('status', "Do WooCommerce odoslaných {$sent} produktov (ako koncepty).");

        return $errors ? $redirect->withErrors(['woo' => $errors]) : $redirect;
    }
}
