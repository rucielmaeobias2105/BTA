<?php

namespace App\Models;

use App\Enums\ItemTag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class InventoryItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The unit every item is counted in.
     *
     * The form used to offer a dropdown of eight units — pcs, ml, bottles, boxes,
     * sachets, grams, sets, kits. It was the one field on the form that was never
     * actually decided per item: the salon counts stock as whole pieces, so the
     * answer was `pcs` on every row and the other seven options existed only to
     * be mis-picked.
     *
     * It is now a constant rather than a list of choices. The column stays — the
     * stock CSV exports it, and existing rows keep whatever they were saved with
     * until they are next edited — so the write path sets this rather than
     * dropping the value.
     */
    public const DEFAULT_UNIT = 'pcs';

    /**
     * The category every new item is filed under.
     *
     * This was a dropdown of the categories the admin had created, which meant the
     * salon maintained a second, parallel set of category names for its stock —
     * one for the service catalogue, one for the shelf — and an admin had to pick
     * the matching label in two places for a report to group sensibly. Nothing
     * validated that the two agreed, so a stock report could quietly split the
     * same service across two groups.
     *
     * The column itself stays on the table but has been retired from the admin
     * screens: the form field, the list column, the sort key and the CSV export
     * column have all gone, because each one was a place to be asked for a second
     * set of names. Existing rows keep whatever they were saved with, so nothing
     * historical is rewritten or lost — it is simply no longer collected, shown or
     * exported, and no longer a sort target. `scopeSearch()` still matches it,
     * the same as it still matches the retired `supplier`, so an old name typed
     * into the search box still finds its rows.
     */
    public const DEFAULT_CATEGORY = 'General';

    protected $fillable = [
        'name',
        'sku',
        'category',
        'quantity',
        'unit',
        'date_in',
        'expiry_date',
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
            'date_in' => 'date',
            'expiry_date' => 'date',
            'is_active' => 'boolean',
            'status_tag' => ItemTag::class,
        ];
    }

    /**
     * Write a stock code the admin never has to invent.
     *
     * The item form has no SKU field, but `inventory_items.sku` is NOT NULL and
     * carries a unique index, and the list prints it under the item name. It is
     * derived once, on create, from the name — and never regenerated, because a
     * code the salon has already written on a stock sheet should survive a
     * rename.
     */
    protected static function booted(): void
    {
        static::creating(function (self $item) {
            if (blank($item->sku)) {
                $item->sku = static::uniqueSkuFor((string) $item->name);
            }
        });
    }

    /**
     * A stock code for the name that no item — including a soft-deleted one —
     * is already holding.
     *
     * `$exceptId` lets a caller ask for a code without colliding with the row it
     * would be replacing.
     */
    public static function uniqueSkuFor(string $name, ?int $exceptId = null): string
    {
        $base = Str::upper(Str::slug($name));
        $base = Str::limit($base !== '' ? $base : 'ITEM', 57, '');

        $sku = $base;
        $suffix = 1;

        while (static::withTrashed()
            ->where('sku', $sku)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists()
        ) {
            $sku = $base.'-'.(++$suffix);
        }

        return $sku;
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_inventory')
            ->withPivot('quantity_per_service')
            ->withTimestamps();
    }

    /**
     * quantity <= reorder threshold — the "auto-suggest Low Stock" rule.
     *
     * A null threshold means the salon never set one, and the item form no
     * longer offers it — so the item is not low on stock, it simply has no
     * threshold to be low against. Casting the null to 0.0 would say otherwise
     * and would disagree with `scopeLowStock()`, where a NULL column never
     * matches a `<=` comparison in SQL.
     */
    public function isLowOnStock(): bool
    {
        if ($this->reorder_threshold === null) {
            return false;
        }

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

        if ($this->isLowOnStock()) {
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
