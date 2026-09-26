<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Six-digit password reset code. Only the hash is persisted — the plaintext
 * code exists solely inside the verification email.
 */
class PasswordResetCode extends Model
{
    protected $fillable = [
        'email',
        'code_hash',
        'expires_at',
        'used_at',
        'attempts',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && ! $this->isExpired();
    }

    public function scopeUsable(Builder $query, string $email): Builder
    {
        return $query
            ->where('email', $email)
            ->whereNull('used_at')
            ->where('expires_at', '>', now());
    }
}
