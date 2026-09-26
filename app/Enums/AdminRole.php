<?php

namespace App\Enums;

enum AdminRole: string
{
    case SuperAdmin = 'super_admin';
    case Manager = 'manager';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Manager => 'Manager',
            self::Staff => 'Staff',
        };
    }

    /**
     * Every gated ability in the admin panel.
     *
     * The list is the single source of truth: AppServiceProvider turns each
     * entry into an `admin.<ability>` Gate, which the routes enforce through
     * the `can:` middleware and the views use through `@can`. A read-only
     * `*.view` twin exists wherever a role could otherwise see a screen but
     * not change it.
     *
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return [
            'dashboard.view',
            'appointments.manage',
            'calendar.view',
            'calendar.manage',
            'catalog.view',
            'catalog.manage',
            'inventory.view',
            'inventory.manage',
            'tags.manage',
            'users.view',
            'users.manage',
            'users.delete',
            'terms.view',
            'terms.manage',
            'reviews.view',
            'reviews.manage',
            'reports.view',
            'promos.manage',
            'messages.manage',
        ];
    }

    /**
     * Abilities granted to a role. Super Admin implicitly holds them all, so
     * new abilities only ever need wiring up once.
     *
     * @return array<int, string>
     */
    public static function abilitiesFor(self $role): array
    {
        return match ($role) {
            self::SuperAdmin => self::abilities(),

            // Runs the salon day to day, but may not edit legal text or
            // destroy customer records.
            self::Manager => [
                'dashboard.view',
                'appointments.manage',
                'calendar.view',
                'calendar.manage',
                'catalog.view',
                'catalog.manage',
                'inventory.view',
                'inventory.manage',
                'tags.manage',
                'users.view',
                'users.manage',
                'terms.view',
                'reviews.view',
                'reviews.manage',
                'reports.view',
                'promos.manage',
                'messages.manage',
            ],

            // Therapists: work the appointment queue, everything else is
            // read-only reference.
            self::Staff => [
                'dashboard.view',
                'appointments.manage',
                'calendar.view',
                'catalog.view',
                'inventory.view',
                'users.view',
                'reviews.view',
            ],
        };
    }

    public function can(string $ability): bool
    {
        return in_array($ability, self::abilitiesFor($this), true);
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
