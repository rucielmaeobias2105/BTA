<?php

namespace App\Models;

use App\Enums\ItemTag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'category',
        'price',
        'duration_minutes',
        'description',
        'photo_path',
        'is_active',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function variants()
    {
        return $this->hasMany(ServiceVariant::class)->orderBy('price');
    }

    public function inventoryItems(): BelongsToMany
    {
        return $this->belongsToMany(InventoryItem::class, 'service_inventory')
            ->withPivot('quantity_per_service')
            ->withTimestamps();
    }

    public function appointments()
    {
        return $this->belongsToMany(Appointment::class, 'appointment_service')
            ->withPivot(['service_variant_id', 'service_name', 'price', 'duration_minutes', 'quantity'])
            ->withTimestamps();
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeCategory(Builder $query, ?string $category): Builder
    {
        return $query->when($category, fn (Builder $q) => $q->where('category', $category));
    }

    /** Free-text search across name + description. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, function (Builder $q) use ($term) {
            $like = '%'.str_replace('%', '\%', $term).'%';

            $q->where(fn (Builder $inner) => $inner
                ->where('name', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('category', 'like', $like));
        });
    }

    /** @return array<int, string> */
    public static function categories(): array
    {
        return static::query()
            ->where('is_active', true)
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? asset('storage/'.$this->photo_path) : null;
    }

    public function getDurationLabelAttribute(): string
    {
        $minutes = (int) $this->duration_minutes;

        if ($minutes < 60) {
            return $minutes.' min';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest === 0 ? "{$hours} hr" : "{$hours} hr {$rest} min";
    }

    /** True when every linked item is sold out. */
    public function isUnavailable(): bool
    {
        $items = $this->inventoryItems;

        return $items->isNotEmpty()
            && $items->every(fn (InventoryItem $item) => $item->status_tag === ItemTag::SoldOut || $item->quantity <= 0);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
