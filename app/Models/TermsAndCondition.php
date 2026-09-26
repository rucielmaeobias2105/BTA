<?php

namespace App\Models;

use App\Enums\TermsCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TermsAndCondition extends Model
{
    use HasFactory;

    protected $table = 'terms_and_conditions';

    protected $fillable = [
        'category',
        'version',
        'content',
        'is_published',
        'published_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => TermsCategory::class,
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /** The version currently shown to customers for a category. */
    public static function publishedFor(TermsCategory|string $category): ?self
    {
        $value = $category instanceof TermsCategory ? $category->value : $category;

        return static::query()
            ->where('category', $value)
            ->where('is_published', true)
            ->orderByDesc('version')
            ->first();
    }

    public static function nextVersionFor(TermsCategory|string $category): int
    {
        $value = $category instanceof TermsCategory ? $category->value : $category;

        return (int) static::where('category', $value)->max('version') + 1;
    }

    public function getDisplayTitleAttribute(): string
    {
        return "{$this->category->label()} Terms (v{$this->version})";
    }
}
