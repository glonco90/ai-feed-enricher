<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    public const PENDING = 'pending';

    public const PROCESSING = 'processing';

    public const REVIEW = 'review';

    public const FAILED = 'failed';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const EXPORTED = 'exported';

    public const LABELS = [
        self::PENDING => 'Čaká',
        self::PROCESSING => 'Spracúva sa',
        self::REVIEW => 'Na kontrolu',
        self::FAILED => 'Chyba',
        self::APPROVED => 'Schválené',
        self::REJECTED => 'Zamietnuté',
        self::EXPORTED => 'V e-shope',
    ];

    protected $fillable = [
        'feed_id', 'sku', 'raw', 'ai', 'status', 'errors', 'attempts',
        'rejected_by_validator', 'input_tokens', 'output_tokens', 'woo_id',
    ];

    protected function casts(): array
    {
        return [
            'raw' => 'array',
            'ai' => 'array',
            'errors' => 'array',
        ];
    }

    public function feed(): BelongsTo
    {
        return $this->belongsTo(Feed::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->status] ?? $this->status;
    }

    public function categoryLabel(): ?string
    {
        $key = $this->ai['category'] ?? null;

        return $key ? (config('enricher.categories')[$key] ?? $key) : null;
    }
}
