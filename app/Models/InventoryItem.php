<?php

namespace App\Models;

use App\Enums\ItemTag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** Units offered to admins in the item form. */
    public const UNITS = ['pcs', 'ml', 'bottles', 'boxes', 'sachets', 'grams', 'sets', 'kits'];

    public const CATEGORIES = [
        'Hair Care', 'Styling', 'Nail Care', 'Lash & Brow', 'Skincare',
        'Massage & Spa', 'Disinfectants', 'Consumables', 'Retail Products',
    ];

    protected $fillable = [
        'name',
        'sku',
        'category',
        'quantity',
        'unit',
        'reorder_threshold',
        'supplier',
        'status_tag',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'reorder_threshold' => 'decimal:2',
            'is_active' => 'boolean',
            'status_tag' => ItemTag::class,
        ];
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_inventory')
            ->withPivot('quantity_per_service')
            ->withTimestamps();
    }

    /**
     * quantity <= reorder threshold — the "auto-suggest Low Stock" rule.
     */
    public function isLowOnStock(): bool
    {
        return (float) $this->quantity <= (float) $this->reorder_threshold;
    }

    public function isSoldOut(): bool
    {
        return $this->status_tag === ItemTag::SoldOut || (float) $this->quantity <= 0;
    }

    /**
     * The tag implied purely by the stock level.
     *
     * Deliberately does NOT consult `status_tag`, otherwise a manual
     * Sold Out tag would make itself its own "suggestion" and an override
     * could never be detected.
     */
    public function suggestedTag(): ItemTag
    {
        $quantity = (float) $this->quantity;

        if ($quantity <= 0) {
            return ItemTag::SoldOut;
        }

        if ($quantity <= (float) $this->reorder_threshold) {
            return ItemTag::LowStock;
        }

        return $this->status_tag === ItemTag::BestSeller
            ? ItemTag::BestSeller
            : ItemTag::Available;
    }

    /**
     * True when the stored tag no longer matches the quantity-derived tag,
     * i.e. the admin applied a manual override we should surface in the UI.
     */
    public function hasManualOverride(): bool
    {
        return $this->status_tag !== $this->suggestedTag();
    }

    public function getStockLabelAttribute(): string
    {
        return rtrim(rtrim(number_format((float) $this->quantity, 2), '0'), '.').' '.$this->unit;
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('quantity', '<=', 'reorder_threshold');
    }

    public function scopeTagged(Builder $query, ?string $tag): Builder
    {
        return $query->when($tag, fn (Builder $q) => $q->where('status_tag', $tag));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, function (Builder $q) use ($term) {
            $like = '%'.str_replace('%', '\%', $term).'%';

            $q->where(fn (Builder $inner) => $inner
                ->where('name', 'like', $like)
                ->orWhere('sku', 'like', $like)
                ->orWhere('supplier', 'like', $like)
                ->orWhere('category', 'like', $like));
        });
    }

    public function scopeCategory(Builder $query, ?string $category): Builder
    {
        return $query->when($category, fn (Builder $q) => $q->where('category', $category));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Re-derive the status tag after a quantity change, but never clobber a
     * Best Seller tag (that is a deliberate marketing choice, not a stock state).
     */
    public function syncStatusTag(): void
    {
        if ($this->status_tag === ItemTag::BestSeller && ! $this->isSoldOut()) {
            return;
        }

        $this->status_tag = $this->suggestedTag();

        if ($this->isDirty()) {
            $this->save();
        }
    }
}
