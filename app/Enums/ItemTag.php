<?php

namespace App\Enums;

enum ItemTag: string
{
    case Available = 'available';
    case LowStock = 'low_stock';
    case BestSeller = 'best_seller';
    case SoldOut = 'sold_out';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::LowStock => 'Low Stock',
            self::BestSeller => 'Best Seller',
            self::SoldOut => 'Sold Out',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Available => 'available',
            self::LowStock => 'low_stock',
            self::BestSeller => 'best_seller',
            self::SoldOut => 'sold_out',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Manual-override tags an admin may assign. "available" is the reset. */
    public static function assignableOptions(): array
    {
        return [
            self::LowStock->value => 'Low Stock',
            self::SoldOut->value => 'Sold Out / Unavailable',
            self::BestSeller->value => 'Best Seller',
            self::Available->value => 'Available (clear tag)',
        ];
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
