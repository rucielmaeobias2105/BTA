<?php

namespace App\Enums;

use App\Contracts\NormalizesEnumValues;

/**
 * Whether a deposit was paid, and whether the salon has checked it.
 *
 * Manual GCash reference verification — there is no payment gateway. A customer
 * who typed a reference has it checked by hand from the admin's appointment
 * screen, and this is the column that records the outcome.
 *
 * The salon takes no deposit for a web booking, so most rows are `NotRequired`.
 * The enum still has to be exact about the rest, because an admin reading a
 * badge is deciding whether money has arrived.
 *
 * Implements `NormalizesEnumValues` so `appointments.down_payment_status` can be
 * read through `App\Casts\TolerantEnum`. The column spent years accepting a
 * default this enum has no case for, and one such row was enough to take down the
 * whole customer appointments page; see `normalize()` for what that was.
 */
enum DownPaymentStatus: string implements NormalizesEnumValues
{
    case Unverified = 'unverified';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case NotRequired = 'not_required';

    public function label(): string
    {
        return match ($this) {
            self::Unverified => 'Awaiting Verification',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
            self::NotRequired => 'Not Required',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Unverified => 'pending',
            self::Verified => 'confirmed',
            self::Rejected => 'cancelled',
            self::NotRequired => 'gold',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }

    /** Every backing value the column is allowed to hold. */
    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Strings this column held before the enum was written, and what they meant.
     *
     * The schema in `balai_ti_arjud.sql` was `varchar(255) DEFAULT 'pending'`,
     * while the enum has always spoken `unverified` and `not_required`. So a
     * booking inserted without naming a status — by a raw query, an import, or
     * simply by taking the column's own default — came out as `pending`, which is
     * not a backing value of this enum. Reading that row cast it and threw
     * `"pending" is not a valid backing value`, which took down the whole
     * customer appointments page for every appointment on it, not just that row.
     *
     * Both legacy spellings mean the same thing, and `BookingService` already
     * writes the mapping for us: a booking with no GCash reference has no deposit
     * to verify, so it is stored as `NotRequired`. These rows carry no reference
     * and no amount, which is exactly that booking.
     *
     * @var array<string, self>
     */
    private const LEGACY = [
        'pending' => self::NotRequired,
    ];

    /**
     * The case a stored value means, whatever spelling it arrived as — or null
     * when it means nothing this enum can stand behind.
     *
     * Null rather than a fallback case, deliberately. A legacy `'pending'` has a
     * real meaning and is mapped above; a value nobody has ever defined does not,
     * and quietly reading it as `NotRequired` would tell an admin verifying a GCash
     * deposit that there was no deposit to verify. Null says "I do not know", and
     * the model turns that into a label asking for a human to look.
     *
     * Read through `App\Casts\TolerantEnum`, which is why this exists: a plain
     * enum cast throws on anything it cannot parse, and one unparseable row was
     * enough to take down the whole customer appointments page.
     */
    public static function normalize(?string $value): ?static
    {
        $value = trim((string) $value);

        return self::tryFrom($value)
            ?? self::LEGACY[strtolower($value)]
            ?? null;
    }
}
