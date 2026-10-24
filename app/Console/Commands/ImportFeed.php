<?php

namespace App\Console\Commands;

use App\Jobs\EnrichProduct;
use App\Models\Feed;
use App\Services\FeedParser;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('feed:import {path : Cesta k XML alebo CSV feedu} {--process : Hneď spracovať cez AI (synchrónne)}')]
#[Description('Načíta feed dodávateľa a voliteľne ho spracuje cez Claude API')]
class ImportFeed extends Command
{
    public function handle(FeedParser $parser): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("Súbor {$path} neexistuje.");

            return self::FAILURE;
        }

        $feed = Feed::import(pathinfo($path, PATHINFO_FILENAME), basename($path), $parser->parse($path));
        $this->info("Feed #{$feed->id}: načítaných {$feed->products()->count()} produktov.");

        if (! $this->option('process')) {
            return self::SUCCESS;
        }

        $this->withProgressBar($feed->products()->orderBy('id')->get(), function ($product): void {
            EnrichProduct::dispatchSync($product);
        });
        $this->newLine(2);

        $stats = $feed->stats();
        $this->table(
            ['Na kontrolu', 'Chyba', 'Zachytené validátorom', 'Vstupné tokeny', 'Výstupné tokeny', 'Cena (USD)'],
            [[$stats['review'], $stats['failed'], $stats['caught'], $stats['input_tokens'], $stats['output_tokens'], $stats['cost'] ?? '—']],
        );

        return self::SUCCESS;
    }
}
