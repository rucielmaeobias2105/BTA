<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'username',
        'contact_number',
        'password',
        'profile_photo_path',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getInitialsAttribute(): string
    {
        return Str::upper(Str::substr((string) $this->first_name, 0, 1).Str::substr((string) $this->last_name, 0, 1));
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profile_photo_path ? asset('storage/'.$this->profile_photo_path) : null;
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class)->latest();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)->latest();
    }

    /** Completed appointments that have not yet been rated. */
    public function reviewableAppointments()
    {
        return $this->appointments()
            ->where('status', \App\Enums\AppointmentStatus::Completed->value)
            ->whereDoesntHave('review');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Derive a unique, URL-safe username from an email address.
     * Used at registration so customers can sign in with "Username or Email".
     */
    public static function deriveUsername(string $email): string
    {
        $base = Str::of(Str::before($email, '@'))
            ->lower()
            ->replaceMatches('/[^a-z0-9._-]+/', '')
            ->substr(0, 40)
            ->toString();

        if ($base === '') {
            $base = 'guest';
        }

        $candidate = $base;
        $suffix = 1;

        while (static::where('username', $candidate)->exists()) {
            $candidate = $base.'-'.(++$suffix);
        }

        return $candidate;
    }

    /** Route model binding uses the id; this keeps display helpers tidy. */
    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
