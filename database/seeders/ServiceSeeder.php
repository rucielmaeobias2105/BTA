<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ServiceSeeder extends Seeder
{
    /**
     * Duration used when a catalogue entry does not state one.
     *
     * Matches the column's own declared default in the schema
     * (`unsignedSmallInteger('duration_minutes')->default(60)`), so it is the
     * system's documented fallback rather than a number invented here.
     *
     * It has to be applied in PHP rather than left to the column default: the
     * live development database carries `duration_minutes` as nullable with a
     * NULL default, while a fresh `migrate` creates it NOT NULL. Writing an
     * explicit null therefore succeeds locally and then fails on a clean
     * install — which is how a seeded deploy of this project would break.
     */
    public const DEFAULT_DURATION = 60;

    public function run(): void
    {
        foreach ($this->catalogue() as $entry) {
            $this->upsertEntry($entry);
        }
    }

    /**
     * Create or update one catalogue entry, and its variants.
     *
     * Public so a single service can be seeded without touching the rest of the
     * catalogue: `ServiceSeeder::upsertEntry()` writes exactly one row and
     * nothing else.
     *
     * @param  array<string, mixed>  $entry
     */
    public function upsertEntry(array $entry): Service
    {
        $values = [
            'name' => $entry['name'],
            'category' => $entry['category'],
            'price' => $entry['price'],
            'duration_minutes' => $entry['duration'] ?? self::DEFAULT_DURATION,
            'description' => $entry['description'],
            'photo_path' => $entry['photo'] ?? null,
            'is_active' => true,
            'is_featured' => $entry['featured'] ?? false,
        ];

        /*
         * `withTrashed()`, and a `restore()` when the row comes back trashed.
         *
         * A soft-deleted service still holds its slug, because `services.slug` is
         * a plain unique index and deleting a row does not free the value. An
         * ordinary `updateOrCreate()` cannot see that row — the SoftDeletes
         * global scope hides it — so it tries to INSERT a second row with the
         * slug the hidden row already owns and dies on
         * `services_slug_unique`. Seeding a service the salon previously listed
         * and then removed therefore crashes rather than restoring it.
         *
         * Restoring is also the right outcome rather than a workaround: the
         * service is genuinely back on the price list, and keeping its id means
         * any past appointment line pointing at it stays attached instead of
         * being orphaned by a replacement row.
         */
        $slug = $this->slugFor($entry);
        $existing = Service::withTrashed()->where('slug', $slug)->first();

        if ($existing) {
            $existing->fill($values);

            if ($existing->trashed()) {
                $existing->restore();
            }

            $existing->save();
            $service = $existing;
        } else {
            $service = Service::create(['slug' => $slug, ...$values]);
        }

        foreach ($entry['variants'] ?? [] as $variant) {
            $service->variants()->updateOrCreate(
                ['name' => $variant['name']],
                [
                    'price' => $variant['price'],
                    'duration_minutes' => $variant['duration'] ?? null,
                    'is_default' => $variant['default'] ?? false,
                ],
            );
        }

        return $service;
    }

    /**
     * A slug for this entry that cannot collide with a service in another category.
     *
     * `Str::slug($name)` alone is not enough, because `services.slug` is unique
     * across the whole table while the price list is not: it sells a *Hair Color*
     * in HAIR CARE SERVICES at one price and a different *Hair Color* in Hair
     * Glowout at another. Both slug to `hair-color`, so a second entry would
     * silently overwrite the first and the cheaper service would disappear from
     * the catalogue.
     *
     * So when the plain slug already belongs to a service in a *different*
     * category, the category is folded into the slug. Re-running the seeder is
     * then stable: the entry finds its own row, the category matches, and the
     * clash test is false — so it updates rather than accumulating `-2`
     * suffixes.
     *
     * @param  array<string, mixed>  $entry
     */
    protected function slugFor(array $entry): string
    {
        $slug = $entry['slug'] ?? Str::slug($entry['name']);
        $category = $entry['category'];

        $takenElsewhere = Service::withTrashed()
            ->where('slug', $slug)
            ->where('category', '!=', $category)
            ->exists();

        if (! $takenElsewhere) {
            return $slug;
        }

        $alternative = Str::slug($category.' '.$entry['name']);
        $candidate = $alternative;
        $suffix = 2;

        while (Service::withTrashed()->where('slug', $candidate)->where('category', '!=', $category)->exists()) {
            $candidate = $alternative.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function catalogue(): array
    {
        return [
            [
                'name' => 'Signature Blowout & Styling',
                'category' => 'Hair Styling',
                'price' => 450,
                'duration' => 60,
                'description' => 'A wash, blow-dry and finish tailored to your hair type and event.',
                'featured' => true,
                'photo' => 'photos/service-blowout.jpg',
                'variants' => [
                    ['name' => 'Short Hair', 'price' => 400, 'default' => true],
                    ['name' => 'Medium Hair', 'price' => 450],
                    ['name' => 'Long Hair', 'price' => 550, 'duration' => 90],
                ],
            ],
            [
                'name' => 'Rebonding / Straightening',
                'category' => 'Hair Care',
                'price' => 2800,
                'duration' => 180,
                'description' => 'Chemical straightening for a smooth, manageable finish. Includes post-treatment care.',
                'variants' => [
                    ['name' => 'Short Hair', 'price' => 2800, 'default' => true],
                    ['name' => 'Long Hair', 'price' => 3800, 'duration' => 240],
                ],
            ],
            [
                'name' => 'Color & Highlights',
                'category' => 'Hair Care',
                'price' => 2200,
                'duration' => 150,
                'description' => 'Full colour change or highlight placement with gloss and blow-dry.',
                'variants' => [
                    ['name' => 'Global Color', 'price' => 2200, 'default' => true],
                    ['name' => 'Highlights', 'price' => 3200, 'duration' => 210],
                    ['name' => 'Balayage', 'price' => 4200, 'duration' => 240],
                ],
            ],
            [
                'name' => 'Glow Manicure',
                'category' => 'Nail Care',
                'price' => 350,
                'duration' => 45,
                'description' => 'Cleanse, shape, cuticle care and a high-shine finish.',
                'featured' => true,
                'variants' => [
                    ['name' => 'Classic', 'price' => 350, 'default' => true],
                    ['name' => 'With Nail Art', 'price' => 450, 'duration' => 60],
                ],
            ],
            [
                'name' => 'Gelish Manicure',
                'category' => 'Nail Care',
                'price' => 600,
                'duration' => 75,
                'description' => 'Long-wear gel colour with a strengthening base and top coat.',
                'featured' => true,
                'photo' => 'photos/service-manicure.jpg',
                'variants' => [
                    ['name' => 'Gelish Manicure', 'price' => 600, 'default' => true],
                    ['name' => 'Gelish with Nail Art', 'price' => 750, 'duration' => 90],
                ],
            ],
            [
                'name' => 'Classic Pedicure',
                'category' => 'Nail Care',
                'price' => 400,
                'duration' => 60,
                'description' => 'Foot soak, exfoliation, nail shaping and polish.',
            ],
            [
                'name' => 'Spa Pedicure',
                'category' => 'Nail Care',
                'price' => 650,
                'duration' => 90,
                'description' => 'Aromatic soak, sugar scrub, mask, massage and polish.',
                'featured' => true,
                'variants' => [
                    ['name' => 'Spa Pedicure', 'price' => 650, 'default' => true],
                    ['name' => 'Spa Pedicure + Paraffin', 'price' => 850, 'duration' => 105],
                ],
            ],
            [
                'name' => 'Classic Eyelash Extensions',
                'category' => 'Lash & Brow',
                'price' => 1800,
                'duration' => 120,
                'description' => 'Hand-applied individual lashes for a natural, defined look.',
                'variants' => [
                    ['name' => 'Classic Set', 'price' => 1800, 'default' => true],
                    ['name' => 'Refill (2-3 weeks)', 'price' => 1200, 'duration' => 90],
                ],
            ],
            [
                'name' => 'Volume Lash Extensions',
                'category' => 'Lash & Brow',
                'price' => 2200,
                'duration' => 150,
                'description' => 'Denser, fuller lashes mapped to your eye shape.',
                'featured' => true,
                'variants' => [
                    ['name' => 'Volume Set', 'price' => 2200, 'default' => true],
                    ['name' => 'Volume Refill', 'price' => 1500, 'duration' => 105],
                ],
            ],
            [
                'name' => 'Brow Lamination & Tint',
                'category' => 'Lash & Brow',
                'price' => 950,
                'duration' => 75,
                'description' => 'Lifted, brushed-up brows with a tint to suit your hair colour.',
            ],
            [
                'name' => 'Hydrating Facial',
                'category' => 'Facial',
                'price' => 1100,
                'duration' => 75,
                'description' => 'Gentle cleanse, exfoliation, hydrating mask and massage.',
            ],
            [
                'name' => 'Brightening Facial',
                'category' => 'Facial',
                'price' => 1500,
                'duration' => 90,
                'description' => 'Targeted treatment for dull or uneven skin tone.',
                'featured' => true,
                'variants' => [
                    ['name' => 'Brightening Facial', 'price' => 1500, 'default' => true],
                    ['name' => 'Brightening + Peel', 'price' => 2000, 'duration' => 105],
                ],
            ],
            [
                'name' => 'Relaxing Massage (60 min)',
                'category' => 'Massage & Spa',
                'price' => 900,
                'duration' => 60,
                'description' => 'A soothing full-body massage to unwind tension.',
                'variants' => [
                    ['name' => '60 minutes', 'price' => 900, 'default' => true],
                    ['name' => '90 minutes', 'price' => 1250, 'duration' => 90],
                ],
            ],
            [
                'name' => 'Aromatherapy Massage (90 min)',
                'category' => 'Massage & Spa',
                'price' => 1500,
                'duration' => 90,
                'description' => 'Essential-oil massage tailored for deep relaxation.',
                'photo' => 'photos/service-massage.jpg',
            ],
            [
                'name' => 'Herbal Steam & Body Scrub',
                'category' => 'Massage & Spa',
                'price' => 1200,
                'duration' => 90,
                'description' => 'Warm herbal steam followed by a full-body exfoliation.',
            ],
            [
                'name' => 'Brazilian Waxing',
                'category' => 'Waxing & Threading',
                'price' => 650,
                'duration' => 45,
                'description' => 'Professional waxing with sensitive-skin formula.',
            ],
            [
                'name' => 'Threading',
                'category' => 'Waxing & Threading',
                'price' => 200,
                'duration' => 30,
                'description' => 'Precise eyebrow and facial threading.',
            ],
            [
                'name' => 'Occasion Makeup',
                'category' => 'Makeup',
                'price' => 1800,
                'duration' => 90,
                'description' => 'Full-face makeup for events, photos and celebrations.',
            ],
            [
                'name' => 'Bridal Glow Package',
                'category' => 'Packages',
                'price' => 12500,
                'duration' => 300,
                'description' => 'A full pre-wedding journey: facial, manicure, lashes, hair and makeup trial.',
                'featured' => true,
                'variants' => [
                    ['name' => 'Bridal Glow', 'price' => 12500, 'default' => true],
                    ['name' => 'Bridal Glow + Extensions', 'price' => 16800, 'duration' => 420],
                ],
            ],

            // ==============================================================
            // Price-list update — therapeutic and spot massage, hair care,
            // the Hair Glowout line, and kiddie services.
            //
            // `duration` is left null wherever the price list does not state
            // one. `duration_minutes` is nullable in this schema, but
            // `BookingService` casts it with `(int)`, so a null duration
            // becomes a zero-minute booking line rather than an error. Fill
            // these in through the admin Services screen before the salon
            // takes bookings against them.
            //
            // The two `Hair Color` entries are deliberately the same name in
            // two categories at two different prices, exactly as the price
            // list has them. `slugFor()` keeps their slugs apart.
            // ==============================================================

            // --- Therapeutic Massage ---
            [
                // This row already exists in the database as "Moving Ventosa
                // Massage (75mins)" with no duration recorded. It is matched
                // by its existing slug so the entry is corrected in place
                // rather than seeded as a near-duplicate beside it.
                'slug' => 'moving-ventosa-massage-75mins',
                'name' => 'Moving Ventosa Massage',
                'category' => 'Therapeutic Massage',
                'batch' => 'price-list-2026-10',
                'price' => 799,
                'duration' => 75,
                'description' => 'Ventosa therapy with cupping moved across the back for circulation and muscle release.',
            ],
            [
                'name' => 'Stationary Ventosa Massage',
                'category' => 'Therapeutic Massage',
                'batch' => 'price-list-2026-10',
                'price' => 799,
                'duration' => 75,
                'description' => 'Ventosa therapy with the cups held in place on the back for a deeper, targeted treatment.',
            ],
            [
                'name' => 'Aromatherapy Massage',
                'category' => 'Therapeutic Massage',
                'batch' => 'price-list-2026-10',
                'price' => 799,
                'duration' => 90,
                'description' => 'A full-length massage using essential oils chosen for relaxation.',
            ],
            [
                'name' => 'Hotstone Massage',
                'category' => 'Therapeutic Massage',
                'batch' => 'price-list-2026-10',
                'price' => 799,
                'duration' => 90,
                'description' => 'Warm basalt stones worked into the muscles to ease stiffness and tension.',
            ],

            // --- Spot Massage ---
            [
                'name' => 'Head + Shoulder',
                'category' => 'Spot Massage',
                'batch' => 'price-list-2026-10',
                'price' => 200,
                'duration' => 30,
                'description' => 'Focused relief for the head, neck and shoulders.',
            ],
            [
                'name' => 'Back',
                'category' => 'Spot Massage',
                'batch' => 'price-list-2026-10',
                'price' => 200,
                'duration' => 30,
                'description' => 'Focused relief for the back.',
            ],
            [
                'name' => 'Hands + Arms',
                'category' => 'Spot Massage',
                'batch' => 'price-list-2026-10',
                'price' => 200,
                'duration' => 30,
                'description' => 'Focused relief for the hands and arms.',
            ],
            [
                'name' => 'Feet + Legs',
                'category' => 'Spot Massage',
                'batch' => 'price-list-2026-10',
                'price' => 200,
                'duration' => 30,
                'description' => 'Focused relief for the feet and lower legs.',
            ],

            // --- Hair Care ---
            [
                'name' => 'Glow Haircut',
                'category' => 'Hair Care',
                'batch' => 'price-list-2026-10',
                'price' => 'starts @ 120',
                'duration' => null,
                'description' => 'Cut and finish tailored to your hair length. Comes with a complimentary shampoo, blow-dry and express massage.',
            ],
            [
                'name' => 'Hot Oil',
                'category' => 'Hair Care',
                'batch' => 'price-list-2026-10',
                'price' => 'starts @ 350',
                'duration' => null,
                'description' => 'Hot-oil treatment to nourish and add shine. Comes with a complimentary shampoo, blow-dry and express massage.',
            ],
            [
                'name' => 'Hair Color',
                'category' => 'Hair Care',
                'batch' => 'price-list-2026-10',
                'price' => 'starts @ 500',
                'duration' => null,
                'description' => 'Full colour service. Price varies with length and coverage. Comes with a complimentary shampoo, blow-dry and express massage.',
            ],
            [
                'name' => 'Hair Spa',
                'category' => 'Hair Care',
                'batch' => 'price-list-2026-10',
                'price' => 'starts @ 450',
                'duration' => null,
                'description' => 'Deep-conditioning treatment for softness and shine. Comes with a complimentary shampoo, blow-dry and express massage.',
            ],
            [
                'name' => 'Keratin',
                'category' => 'Hair Care',
                'batch' => 'price-list-2026-10',
                'price' => 'starts @ 800',
                'duration' => null,
                'description' => 'Keratin smoothing to reduce frizz and shorten styling time. Comes with a complimentary shampoo, blow-dry and express massage.',
            ],
            [
                'name' => 'Rebond',
                'category' => 'Hair Care',
                'batch' => 'price-list-2026-10',
                'price' => 'starts @ 1,500',
                'duration' => null,
                'description' => 'Chemical rebonding for a permanent straightened finish. Comes with a complimentary shampoo, blow-dry and express massage.',
            ],
            [
                'name' => 'Bleach',
                'category' => 'Hair Care',
                'batch' => 'price-list-2026-10',
                'price' => 'starts @ 500',
                'duration' => null,
                'description' => 'Lightening or pre-lightening treatment. Comes with a complimentary shampoo, blow-dry and express massage.',
            ],

            // --- Hair Glowout ---
            [
                'name' => 'Kerabond',
                'category' => 'Hair Glowout',
                'batch' => 'price-list-2026-10',
                'price' => 'starts @ 2,000',
                'duration' => null,
                'description' => 'Keratin straightening bonded with keratin treatment for a smooth, glossy finish.',
            ],
            [
                'name' => 'Hair Color + Rebond',
                'category' => 'Hair Glowout',
                'batch' => 'price-list-2026-10',
                'price' => 'starts @ 1,800',
                'duration' => null,
                'description' => 'Colour and rebonding in one appointment for a single transformation.',
            ],
            [
                'name' => 'Hair Color + Kerabond',
                'category' => 'Hair Glowout',
                'batch' => 'price-list-2026-10',
                'price' => 'starts @ 2,300',
                'duration' => null,
                'description' => 'Colour combined with keratin rebonding for the smoothest, glossiest result.',
            ],
            [
                // The same name as the HAIR CARE SERVICES entry above, at a
                // different price. Kept distinct by `slugFor()`.
                'name' => 'Hair Color',
                'category' => 'Hair Glowout',
                'batch' => 'price-list-2026-10',
                'price' => 'starts @ 850',
                'duration' => null,
                'description' => 'Colour as part of the Hair Glowout treatment range.',
            ],

            // --- Kiddie Services ---
            [
                'name' => 'Kiddie Mani',
                'category' => 'Kiddie Services',
                'batch' => 'price-list-2026-10',
                'price' => 69,
                'duration' => null,
                'description' => 'A gentle manicure sized and shaped for young hands.',
            ],
            [
                'name' => 'Kiddie Pedi',
                'category' => 'Kiddie Services',
                'batch' => 'price-list-2026-10',
                'price' => 89,
                'duration' => null,
                'description' => 'A gentle pedicure for young feet.',
            ],
            [
                'name' => 'Kiddie Hand Spa',
                'category' => 'Kiddie Services',
                'batch' => 'price-list-2026-10',
                'price' => 149,
                'duration' => null,
                'description' => 'A soak, scrub and massage for young hands.',
            ],
            [
                'name' => 'Kiddie Foot Spa',
                'category' => 'Kiddie Services',
                'batch' => 'price-list-2026-10',
                'price' => 199,
                'duration' => null,
                'description' => 'A soak, scrub and massage for young feet.',
            ],
        ];
    }
}
