<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promo extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'starts_at',
        'ends_at',
        'image_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'is_active' => 'boolean',
            'notified' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereDate('starts_at', '<=', today())
            ->whereDate('ends_at', '>=', today());
    }

    public function scopeWithinValidity(Builder $query): Builder
    {
        return $query
            ->whereDate('starts_at', '<=', today())
            ->whereDate('ends_at', '>=', today());
    }

    public function isCurrentlyValid(): bool
    {
        return (bool) $this->is_active
            && today()->betweenIncluded($this->starts_at->copy()->startOfDay(), $this->ends_at->copy()->endOfDay());
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? asset('storage/'.$this->image_path) : null;
    }

    public function getValidityLabelAttribute(): string
    {
        return $this->starts_at->format('M j, Y').' – '.$this->ends_at->format('M j, Y');
    }
}
