<?php

namespace App\Services;

use InvalidArgumentException;
use SimpleXMLElement;

/**
 * Načíta feed dodávateľa (Heureka-like XML alebo CSV) do jednotnej štruktúry.
 *
 * @phpstan-type RawProduct array{sku: string, name: string, description: string, brand: string, ean: string, price: string, category_hint: string, params: list<array{name: string, value: string}>}
 */
class FeedParser
{
    /**
     * @return list<RawProduct>
     */
    public function parse(string $path, ?string $extension = null): array
    {
        $extension = strtolower($extension ?? pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'xml' => $this->parseXml((string) file_get_contents($path)),
            'csv', 'txt' => $this->parseCsv($path),
            default => throw new InvalidArgumentException("Nepodporovaný formát feedu: {$extension}"),
        };
    }

    /**
     * @return list<RawProduct>
     */
    public function parseXml(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $root = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
        libxml_use_internal_errors($previous);

        if ($root === false) {
            throw new InvalidArgumentException('Feed nie je platné XML.');
        }

        $products = [];

        foreach ($root->SHOPITEM as $item) {
            $params = [];

            foreach ($item->PARAM as $param) {
                $params[] = [
                    'name' => trim((string) $param->PARAM_NAME),
                    'value' => trim((string) $param->VAL),
                ];
            }

            $products[] = $this->normalize([
                'sku' => (string) $item->ITEM_ID,
                'name' => (string) $item->PRODUCTNAME,
                'description' => (string) $item->DESCRIPTION,
                'brand' => (string) $item->MANUFACTURER,
                'ean' => (string) $item->EAN,
                'price' => (string) $item->PRICE_VAT,
                'category_hint' => (string) $item->CATEGORYTEXT,
                'params' => $params,
            ]);
        }

        return $products;
    }

    /**
     * CSV s hlavičkou. Stĺpce mimo známych polí sa berú ako parametre produktu.
     *
     * @return list<RawProduct>
     */
    public function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new InvalidArgumentException('CSV sa nedá otvoriť.');
        }

        $firstLine = (string) fgets($handle);
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        $header = array_map(
            fn (string $column): string => strtolower(trim($column, " \t\n\r\0\x0B\u{FEFF}")),
            str_getcsv($firstLine, $delimiter, '"', ''),
        );

        $known = ['sku', 'name', 'description', 'brand', 'ean', 'price', 'category'];
        $products = [];

        while (($row = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            if ($row === [null]) {
                continue;
            }

            $data = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), ''));
            $params = [];

            foreach ($data as $column => $value) {
                if (! in_array($column, $known, true) && trim((string) $value) !== '') {
                    $params[] = ['name' => $column, 'value' => trim((string) $value)];
                }
            }

            $products[] = $this->normalize([
                'sku' => $data['sku'] ?? '',
                'name' => $data['name'] ?? '',
                'description' => $data['description'] ?? '',
                'brand' => $data['brand'] ?? '',
                'ean' => $data['ean'] ?? '',
                'price' => $data['price'] ?? '',
                'category_hint' => $data['category'] ?? '',
                'params' => $params,
            ]);
        }

        fclose($handle);

        return $products;
    }

    /**
     * @param  array<string, mixed>  $product
     * @return RawProduct
     */
    private function normalize(array $product): array
    {
        foreach (['sku', 'name', 'description', 'brand', 'ean', 'price', 'category_hint'] as $field) {
            $product[$field] = trim(preg_replace('/\s+/u', ' ', (string) ($product[$field] ?? '')));
        }

        if ($product['sku'] === '' || $product['name'] === '') {
            throw new InvalidArgumentException('Každý produkt vo feede musí mať SKU a názov.');
        }

        return $product;
    }
}
