<?php

namespace App\Models;

use App\Support\PriceFormatter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'name',
        'price',
        'base_price',
        'duration_minutes',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'string',
            'base_price' => 'decimal:2',
            'is_default' => 'boolean',
        ];
    }

    /**
     * Keep `base_price` in step with the advertised `price`.
     *
     * The form has one price field, so there is nothing to keep in step by
     * hand. The first figure is what the variant starts at, which is the
     * amount a booking is calculated from.
     */
    protected static function booted(): void
    {
        static::saving(function (self $variant): void {
            if ($variant->isDirty('price') || $variant->isDirty('base_price')) {
                $variant->base_price = PriceFormatter::firstFigure($variant->price);
            }
        });
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** Falls back to the parent service duration when not overridden. */
    public function effectiveDuration(): int
    {
        return (int) ($this->duration_minutes ?: $this->service?->duration_minutes);
    }
}
