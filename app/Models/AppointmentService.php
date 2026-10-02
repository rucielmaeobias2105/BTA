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
        'display_price',
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

    /**
     * The price as the customer was shown it, "249/499" and all.
     *
     * `price` on this table stays a DECIMAL because it is the figure the total
     * is multiplied from; the advertised string is snapshotted alongside it in
     * `display_price`, and only the strings before that column existed fall
     * back to the number.
     *
     * Takes the raw value as a parameter rather than reading `$this->…` back,
     * which would ask for this very accessor again.
     */
    public function getDisplayPriceAttribute(mixed $value): string
    {
        $shown = trim((string) $value);

        if ($shown === '') {
            $shown = number_format((float) $this->price, 2, '.', '');
        }

        return $shown;
    }

    public function getLineTotalAttribute(): float
    {
        return (float) $this->price * (int) $this->quantity;
    }
}
