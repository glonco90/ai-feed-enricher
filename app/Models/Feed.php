<?php

namespace App\Models;

use Database\Factories\FeedFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Feed extends Model
{
    /** @use HasFactory<FeedFactory> */
    use HasFactory;

    protected $fillable = ['name', 'source_filename'];

    /**
     * @param  list<array<string, mixed>>  $rawProducts
     */
    public static function import(string $name, string $sourceFilename, array $rawProducts): self
    {
        return DB::transaction(function () use ($name, $sourceFilename, $rawProducts): self {
            $feed = self::create(['name' => $name, 'source_filename' => $sourceFilename]);

            foreach ($rawProducts as $raw) {
                $feed->products()->create(['sku' => $raw['sku'], 'raw' => $raw]);
            }

            return $feed;
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function isProcessing(): bool
    {
        return $this->products()->where('status', Product::PROCESSING)->exists()
            || ($this->products()->where('status', Product::PENDING)->exists() && DB::table('jobs')->exists());
    }

    /**
     * @return array<string, int|float|null>
     */
    public function stats(): array
    {
        $counts = $this->products()
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $sums = $this->products()
            ->selectRaw('sum(input_tokens) as input, sum(output_tokens) as output, sum(rejected_by_validator) as caught')
            ->first();

        $priceIn = config('enricher.anthropic.price_input');
        $priceOut = config('enricher.anthropic.price_output');

        return [
            'total' => (int) $counts->sum(),
            'pending' => (int) ($counts[Product::PENDING] ?? 0) + (int) ($counts[Product::PROCESSING] ?? 0),
            'review' => (int) ($counts[Product::REVIEW] ?? 0),
            'failed' => (int) ($counts[Product::FAILED] ?? 0),
            'approved' => (int) ($counts[Product::APPROVED] ?? 0),
            'rejected' => (int) ($counts[Product::REJECTED] ?? 0),
            'exported' => (int) ($counts[Product::EXPORTED] ?? 0),
            'input_tokens' => (int) $sums->input,
            'output_tokens' => (int) $sums->output,
            'caught' => (int) $sums->caught,
            'cost' => (is_numeric($priceIn) && is_numeric($priceOut))
                ? round(((int) $sums->input * (float) $priceIn + (int) $sums->output * (float) $priceOut) / 1_000_000, 4)
                : null,
        ];
    }
}
