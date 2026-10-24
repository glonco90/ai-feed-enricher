<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * CSV vo formáte vstavaného importéra WooCommerce (Produkty → Importovať).
 */
class WooCsvExporter
{
    /**
     * @param  Collection<int, Product>  $products
     */
    public function export(Collection $products): string
    {
        $maxAttributes = (int) $products->max(fn (Product $product): int => count($product->ai['attributes'] ?? []));

        $header = ['Type', 'SKU', 'Name', 'Published', 'Short description', 'Description', 'Regular price', 'Categories', 'Meta: seo_title', 'Meta: seo_description', 'Meta: ean'];

        for ($i = 1; $i <= $maxAttributes; $i++) {
            array_push($header, "Attribute {$i} name", "Attribute {$i} value(s)", "Attribute {$i} visible", "Attribute {$i} global");
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\u{FEFF}");
        fputcsv($handle, $header, ',', '"', '');

        foreach ($products as $product) {
            $ai = $product->ai;
            $row = [
                'simple',
                $product->sku,
                $ai['name'],
                0,
                $ai['short_description'],
                $ai['description'],
                $product->raw['price'] ?? '',
                str_replace(' › ', ' > ', config('enricher.categories')[$ai['category']] ?? $ai['category']),
                $ai['seo_title'],
                $ai['seo_description'],
                $product->raw['ean'] ?? '',
            ];

            for ($i = 0; $i < $maxAttributes; $i++) {
                $attribute = $ai['attributes'][$i] ?? null;
                array_push($row, $attribute['name'] ?? '', $attribute['value'] ?? '', $attribute ? 1 : '', $attribute ? 0 : '');
            }

            fputcsv($handle, $row, ',', '"', '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
