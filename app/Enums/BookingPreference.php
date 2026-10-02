<?php

namespace App\Enums;

/**
 * What a customer tells the salon about their sensitivities and preferences.
 *
 * Step 4 of the booking form used to be a grid of checkboxes — Latex, Nickel,
 * Fragrance and five others — plus a free-text box beside them. Two things were
 * wrong with it. The grid was long enough that most customers skimmed it rather
 * than read it, and a checkbox can only ever answer "yes" to a fixed list, so
 * anything the salon had not thought of had to go in the free-text box that sat
 * next to it looking like an afterthought.
 *
 * A single select is the better shape for the same information: it is one
 * decision instead of eight, it reads in order from "nothing to declare" through
 * the allergies to the handling notes, and it is one `<select>` the server can
 * validate against a closed set. The free-text box stays alongside it, but only
 * for the detail that does not fit a label.
 *
 * The value is stored in the existing `appointments.allergies` column, joined to
 * the free-text note exactly as the checkbox list used to be. No column was added
 * and nothing that reads that column changed shape: the admin's booking screens
 * and the customer's own appointment dialog render it as a comma-joined list of
 * words, which is still what this produces.
 */
enum BookingPreference: string
{
    case None = 'none';
    case LatexAllergy = 'latex_allergy';
    case NickelAllergy = 'nickel_allergy';
    case FragranceSensitivity = 'fragrance_sensitivity';
    case ShellfishAllergy = 'shellfish_allergy';
    case ChemicalAllergy = 'chemical_allergy';
    case AdhesiveSensitivity = 'adhesive_sensitivity';
    case SensitiveScalp = 'sensitive_scalp';
    case WeakOrBrittleNails = 'weak_or_brittle_nails';
    case ThickNails = 'thick_nails';
    case ShortNailBed = 'short_nail_bed';
    case GentleProducts = 'gentle_products';
    case FragranceFree = 'fragrance_free';
    case PainSensitive = 'pain_sensitive';
    case Other = 'other';

    /**
     * The sentence stored on the booking, in place of the enum's value.
     *
     * The label, not the backing value: `appointments.allergies` is read by
     * people — the admin verifying a booking, the customer looking at their own
     * history — and `weak_or_brittle_nails` means nothing to either of them.
     */
    public function label(): string
    {
        return match ($this) {
            self::None => 'No known allergies or sensitivities',
            self::LatexAllergy => 'Allergy to latex',
            self::NickelAllergy => 'Allergy to nickel',
            self::FragranceSensitivity => 'Sensitive to fragrance',
            self::ShellfishAllergy => 'Allergy to shellfish / marine products',
            self::ChemicalAllergy => 'Allergy to hair dye, bleach or ammonia',
            self::AdhesiveSensitivity => 'Sensitive to nail adhesive or glue',
            self::SensitiveScalp => 'Sensitive scalp',
            self::WeakOrBrittleNails => 'Weak or brittle nails',
            self::ThickNails => 'Thick nails',
            self::ShortNailBed => 'Short nail bed',
            self::GentleProducts => 'Prefers gentle products',
            self::FragranceFree => 'Prefers fragrance-free products',
            self::PainSensitive => 'Pain sensitive — please go easy',
            self::Other => 'Something else (please tell us below)',
        };
    }

    /**
     * Whether this answer means "there is something to act on".
     *
     * `None` and `Other` are the two answers that carry no health information of
     * their own — `Other` says there is something, but only the free-text box
     * says what — so neither is stored on its own.
     */
    public function isInformative(): bool
    {
        return $this !== self::None && $this !== self::Other;
    }

    /**
     * The dropdown, blank option first.
     *
     * The blank entry is what an untouched select shows, and it is deliberately
     * a real choice rather than a pre-selected `None`: a customer who never
     * looked at this step should leave nothing on the booking, which is a
     * different statement from declaring that they have no allergies. It is not
     * `required` — nothing here is worth refusing a booking over, and forcing a
     * health declaration to buy a manicure is not the salon's call to make.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return ['' => 'Select a preference or sensitivity…'] + self::storedOptions();
    }

    /**
     * The same list without the blank entry, for places that must pick one.
     *
     * @return array<string, string>
     */
    public static function storedOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }

    /**
     * The case a stored value names, or null when it names nothing we know.
     *
     * Null rather than a fallback case, so a value written by an older version of
     * this list is simply not re-offered as if it were a current answer. The raw
     * text stays on the booking either way.
     */
    public static function tryFromLabel(?string $value): ?self
    {
        return self::tryFrom(trim((string) $value));
    }
}
