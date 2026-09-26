<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentService extends Model
{
    use HasFactory;

    protected $table = 'appointment_service';

    protected $fillable = [
        'appointment_id',
        'service_id',
        'service_variant_id',
        'service_name',
        'variant_name',
        'price',
        'duration_minutes',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration_minutes' => 'integer',
            'quantity' => 'integer',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ServiceVariant::class, 'service_variant_id');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->variant_name
            ? "{$this->service_name} ({$this->variant_name})"
            : $this->service_name;
    }

    public function getLineTotalAttribute(): float
    {
        return (float) $this->price * (int) $this->quantity;
    }
}
