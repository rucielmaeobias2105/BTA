<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\ChangedBy;
use App\Enums\DownPaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'reference_number',
        'user_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'preferred_date',
        'preferred_time',
        'allergies',
        'last_services_availed',
        'preferred_stylist_id',
        'special_request',
        'down_payment_reference',
        'down_payment_amount',
        'down_payment_status',
        'total_amount',
        'status',
        'source',
        'admin_notes',
        'cancellation_reason',
        'cancelled_at',
        'reschedule_reason',
        'confirmed_at',
        'completed_at',
        'started_at',
    ];

    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'down_payment_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'status' => AppointmentStatus::class,
            'down_payment_status' => DownPaymentStatus::class,
            'cancelled_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'started_at' => 'datetime',
        ];
    }

    /* ----------------------------------------------------------------- */
    /* Relations                                                          */
    /* ----------------------------------------------------------------- */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function preferredStylist(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'preferred_stylist_id');
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'appointment_service')
            ->withPivot(['service_variant_id', 'service_name', 'price', 'duration_minutes', 'quantity'])
            ->withTimestamps();
    }

    /** Ordered pivot rows — used to render the booking summary exactly as entered. */
    public function serviceLines()
    {
        return $this->hasMany(AppointmentService::class)->orderBy('id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(AppointmentStatusHistory::class)->latest();
    }

    public function review(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /* ----------------------------------------------------------------- */
    /* Scopes                                                            */
    /* ----------------------------------------------------------------- */

    public function scopeForUser(Builder $query, ?int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if (! $status || $status === 'all') {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query
            ->whereDate('preferred_date', '>=', today())
            ->whereIn('status', [
                AppointmentStatus::Pending->value,
                AppointmentStatus::Confirmed->value,
            ]);
    }

    public function scopeBetweenDates(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q) => $q->whereDate('preferred_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('preferred_date', '<=', $to));
    }

    /* ----------------------------------------------------------------- */
    /* Presentation helpers                                              */
    /* ----------------------------------------------------------------- */

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    public function getStatusBadgeAttribute(): string
    {
        return $this->status->badge();
    }

    public function getDateTimeLabelAttribute(): string
    {
        return $this->preferred_date->format('M j, Y').' at '.$this->getTimeLabelAttribute();
    }

    public function getTimeLabelAttribute(): string
    {
        $time = \Illuminate\Support\Carbon::parse($this->preferred_time);

        return $time->format('g:i A');
    }

    public function getServiceNamesAttribute(): string
    {
        $lines = $this->serviceLines;

        if ($lines->isEmpty()) {
            return '—';
        }

        return $lines
            ->map(fn (AppointmentService $line) => $line->display_name)
            ->join(', ');
    }

    public function getTotalDurationAttribute(): int
    {
        return (int) $this->serviceLines->sum(
            fn (AppointmentService $line) => $line->duration_minutes * $line->quantity
        );
    }

    public function canBeCancelled(): bool
    {
        return $this->status->isCustomerActionable();
    }

    public function canBeRescheduled(): bool
    {
        return $this->status->isCustomerActionable();
    }

    public function canBeRated(): bool
    {
        return $this->status === AppointmentStatus::Completed && ! $this->review()->exists();
    }

    /** Human label for who moved the appointment into its current state. */
    public function recordStatusChange(
        AppointmentStatus $to,
        ChangedBy $by,
        ?int $actorId = null,
        ?string $actorName = null,
        ?string $note = null,
    ): void {
        $this->statusHistory()->create([
            'from_status' => $this->status,
            'to_status' => $to,
            'changed_by' => $by,
            'changed_by_id' => $actorId,
            'changed_by_name' => $actorName,
            'note' => $note,
        ]);
    }

    /** Static reference generator, e.g. BTA-20260926-4F2A. */
    public static function generateReferenceNumber(): string
    {
        do {
            $reference = 'BTA-'.now()->format('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        } while (static::where('reference_number', $reference)->exists());

        return $reference;
    }
}
