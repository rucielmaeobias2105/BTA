<?php

namespace App\Casts;

use App\Contracts\NormalizesEnumValues;
use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A backed-enum cast that reads an unrecognised value as null instead of throwing.
 *
 * The plain cast is strict on purpose — it should be — but strictness on *read*
 * turns one bad row into a whole dead page. `appointments.down_payment_status`
 * proved it: the column's default in the shipped schema was `'pending'`, a value
 * `DownPaymentStatus` has never had, so a booking that inherited it threw
 * `"pending" is not a valid backing value` the moment anything read the column.
 * `/appointments` reads it for every row it lists, so one such row took down the
 * customer's entire list rather than showing as one bad cell.
 *
 * Reading tolerantly and *writing* strictly keeps both halves of that. A value
 * this cannot parse becomes null, which the model renders as a label asking for a
 * look; anything written still has to come from a real case, because `set` does
 * no guessing.
 *
 * The enum must expose `normalize(?string): ?static`. That is the contract this
 * cast leans on, and it is where a legacy spelling gets mapped rather than here —
 * so the list of spellings an enum accepts lives with the enum.
 */
class TolerantEnum implements CastsAttributes
{
    /** @param  class-string<BackedEnum&NormalizesEnumValues>  $enum */
    public function __construct(protected string $enum) {}

    /**
     * @return ($value is null ? null : ?BackedEnum)
     */
    public function get(Model $model, string $key, $value, array $attributes): ?BackedEnum
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Already cast — a re-read of a model that was hydrated elsewhere.
        if ($value instanceof BackedEnum) {
            return $value;
        }

        return $this->enum::normalize((string) $value);
    }

    /**
     * Written exactly as given, minus the enum wrapper.
     *
     * No normalisation here: this is the gate. If something tries to store a
     * value the enum does not define, it should fail at the point it is written
     * rather than be quietly rewritten into something valid-looking.
     *
     * @return array<string, mixed>
     */
    public function set(Model $model, string $key, $value, array $attributes): array
    {
        return [$key => $value instanceof BackedEnum ? $value->value : $value];
    }
}
