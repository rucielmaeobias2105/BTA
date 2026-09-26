@props(['status' => 'pending', 'label' => null, 'dot' => true])

@php
    use App\Enums\AppointmentStatus;
    use App\Enums\ItemTag;

    $map = [
        // Appointment statuses
        'pending' => 'badge-pending',
        'confirmed' => 'badge-confirmed',
        'in_progress' => 'badge-progress',
        'completed' => 'badge-completed',
        'cancelled' => 'badge-cancelled',
        // Item / service tags
        'low_stock' => 'badge-lowstock',
        'best_seller' => 'badge-bestseller',
        'sold_out' => 'badge-soldout',
        'available' => 'badge-confirmed',
        'gold' => 'badge-gold',
    ];

    $class = $map[$status] ?? 'badge-gold';

    $label ??= match ($status) {
        'in_progress' => 'In Progress',
        'low_stock' => 'Low Stock',
        'best_seller' => 'Best Seller',
        'sold_out' => 'Sold Out',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
@endphp

<span {{ $attributes->merge(['class' => 'badge '.$class]) }}>
    @if ($dot)
        <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>
    @endif
    {{ $label }}
</span>
