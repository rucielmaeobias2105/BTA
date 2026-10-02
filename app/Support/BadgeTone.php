<?php

namespace App\Support;

/**
 * Resolves a status tone to the CSS class that paints it.
 *
 * The map lived inside `x-ui.badge`, which is fine for a server-rendered badge
 * but useless the moment a badge has to be painted from JavaScript — the
 * Appointment Details dialog binds its status and payment tones client-side
 * from the payload, and a class name built there would be a second copy of this
 * table that nothing keeps in step.
 *
 * Both callers now go through here: the component for a rendered badge, the
 * dialog payload builder for the tone a row's badge will take once Alpine has
 * it. An unknown tone falls back to gold rather than emitting an unstyled span.
 */
class BadgeTone
{
    /**
     * Tone key → badge class.
     *
     * @var array<string, string>
     */
    public const MAP = [
        // Appointment statuses
        'pending' => 'badge-pending',
        'confirmed' => 'badge-confirmed',
        'in_progress' => 'badge-progress',
        'completed' => 'badge-completed',
        'cancelled' => 'badge-cancelled',
        // Down-payment states, which reuse the status tones.
        'verified' => 'badge-confirmed',
        'unverified' => 'badge-pending',
        'rejected' => 'badge-cancelled',
        'not_required' => 'badge-gold',
        // Item / service tags
        'low_stock' => 'badge-lowstock',
        'best_seller' => 'badge-bestseller',
        'sold_out' => 'badge-soldout',
        'available' => 'badge-confirmed',
        'gold' => 'badge-gold',
    ];

    /**
     * A human label for a tone key, used when the caller has no enum to ask.
     *
     * @var array<string, string>
     */
    public const LABELS = [
        'in_progress' => 'In Progress',
        'low_stock' => 'Low Stock',
        'best_seller' => 'Best Seller',
        'sold_out' => 'Sold Out',
    ];

    public static function classFor(?string $tone): string
    {
        return self::MAP[$tone ?? ''] ?? self::MAP['gold'];
    }

    public static function labelFor(string $tone): string
    {
        return self::LABELS[$tone] ?? ucfirst(str_replace('_', ' ', $tone));
    }
}
