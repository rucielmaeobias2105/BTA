<?php

namespace App\Models;

use App\Enums\InquiryTopic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    use HasFactory;

    /**
     * `is_read`, `admin_reply` and `replied_at` were missing here, so
     * ContactMessageController@update() mass-assigned nothing: it flashed
     * "Reply saved." while the reply, the read flag and the reply timestamp
     * were all silently discarded. Nothing about an enquiry could ever be
     * answered, and the unread count could never clear.
     */
    protected $fillable = [
        'name',
        'email',
        'topic',
        'message',
        'is_read',
        'admin_reply',
        'replied_at',
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
