<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\ChangedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'from_status',
        'to_status',
        'changed_by',
        'changed_by_id',
        'changed_by_name',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => AppointmentStatus::class,
            'to_status' => AppointmentStatus::class,
            'changed_by' => ChangedBy::class,
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function getArrowAttribute(): string
    {
        $from = $this->from_status?->label() ?? 'Created';

        return "{$from} → {$this->to_status->label()}";
    }
}
