<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\ProductEnricher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class EnrichProduct implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public Product $product) {}

    public function handle(ProductEnricher $enricher): void
    {
        $this->product->update(['status' => Product::PROCESSING]);

        try {
            $result = $enricher->enrich($this->product->raw);
        } catch (Throwable $e) {
            $this->product->update([
                'status' => Product::FAILED,
                'errors' => [$e->getMessage()],
            ]);

            return;
        }

        $this->product->update([
            'status' => $result['ai'] ? Product::REVIEW : Product::FAILED,
            'ai' => $result['ai'],
            'errors' => $result['errors'] ?: null,
            'attempts' => $this->product->attempts + $result['attempts'],
            'rejected_by_validator' => $this->product->rejected_by_validator + $result['rejected'],
            'input_tokens' => $this->product->input_tokens + $result['input_tokens'],
            'output_tokens' => $this->product->output_tokens + $result['output_tokens'],
        ]);
    }
}
