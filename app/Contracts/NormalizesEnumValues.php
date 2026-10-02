<?php

namespace App\Contracts;

/**
 * An enum that can say what a stored value means, including nothing.
 *
 * Exists for `App\Casts\TolerantEnum`, which reads a column through an enum and
 * needs the enum — not the cast — to decide what an unrecognised value means.
 * Keeping that decision in the enum is the point: the list of spellings a column
 * has held over the years belongs next to the cases it has now, so there is one
 * place to look and one place to extend.
 *
 * Returning null is part of the contract and is not a failure. "I do not know
 * what this value means" is a real answer, and it is the honest one for a string
 * nobody has ever defined — a caller can render that as a question for a human,
 * which it cannot do for a value quietly guessed into a case that would be a lie.
 */
interface NormalizesEnumValues
{
    /**
     * The case a stored value means, or null when it means nothing known.
     */
    public static function normalize(?string $value): ?static;
}
