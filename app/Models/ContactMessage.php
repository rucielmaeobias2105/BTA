<?php

namespace App\Models;

use App\Enums\InquiryTopic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'topic',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'topic' => InquiryTopic::class,
            'is_read' => 'boolean',
            'replied_at' => 'datetime',
        ];
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }
}
