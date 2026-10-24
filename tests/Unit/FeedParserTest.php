<?php

namespace Tests\Unit;

use App\Services\FeedParser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FeedParserTest extends TestCase
{
    public function test_it_parses_the_sample_heureka_xml_feed(): void
    {
        $products = (new FeedParser)->parse(__DIR__.'/../../resources/samples/supplier-feed.xml');

        $this->assertCount(14, $products);
        $this->assertSame('STX-TR2-42', $products[0]['sku']);
        $this->assertSame('Stridex', $products[0]['brand']);
        $this->assertSame('119.90', $products[0]['price']);
        $this->assertSame(['name' => 'size', 'value' => '42'], $products[0]['params'][0]);
        $this->assertCount(3, $products[0]['params']);
    }

    public function test_it_parses_csv_with_semicolons_and_treats_extra_columns_as_params(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'feed');
        file_put_contents($path, "\u{FEFF}SKU;Name;Price;Color;Size\nA-1;Run shirt;19.90;red;M\nA-2;Run shorts;24.90;;L\n");

        $products = (new FeedParser)->parse($path, 'csv');
        unlink($path);

        $this->assertCount(2, $products);
        $this->assertSame('A-1', $products[0]['sku']);
        $this->assertSame([['name' => 'color', 'value' => 'red'], ['name' => 'size', 'value' => 'M']], $products[0]['params']);
        $this->assertSame([['name' => 'size', 'value' => 'L']], $products[1]['params']);
    }

    public function test_it_rejects_invalid_xml(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new FeedParser)->parseXml('<SHOP><SHOPITEM>');
    }

    public function test_it_requires_sku_and_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new FeedParser)->parseXml('<SHOP><SHOPITEM><ITEM_ID>X</ITEM_ID></SHOPITEM></SHOP>');
    }
}
