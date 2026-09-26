<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'start_date',
        'end_date',
        'service_id',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date:Y-m-d',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function getEndDateAttribute($value): ?string
    {
        return $value;
    }

    public function isSingleDay(): bool
    {
        return $this->start_date->isSameDay($this->end_date);
    }

    public function getRangeLabelAttribute(): string
    {
        return $this->isSingleDay()
            ? $this->start_date->format('M j, Y')
            : $this->start_date->format('M j, Y').' – '.$this->end_date->format('M j, Y');
    }

    public function getScopeLabelAttribute(): string
    {
        return $this->service?->name ?? 'All services';
    }

    /** Any appointment on these dates that is still active. */
    public function appointments()
    {
        return Appointment::query()
            ->whereDate('preferred_date', '>=', $this->start_date)
            ->whereDate('preferred_date', '<=', $this->end_date)
            ->whereIn('status', ['pending', 'confirmed']);
    }

    public function scopeOverlapping(Builder $query, string $start, string $end): Builder
    {
        return $query
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start);
    }
}
