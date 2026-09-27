import { reactive, computed } from 'vue';
import { addDays, isWithin, today } from '@/lib/dates';
import { formatDate } from '@/lib/format';

/**
 * Promotions.
 *
 * Ported from the promo rows in `database/seeders/ContentSeeder.php`, keeping
 * the `validity_label` accessor from `App\Models\Promo` as a plain function.
 */

let nextId = 1;

function makePromo({ title, description, starts_at, ends_at, is_active, image_path = null }) {
    return reactive({
        id: nextId++,
        title,
        description,
        starts_at,
        ends_at,
        image_path,
        is_active,
        notified: false,
    });
}

export const promos = reactive([
    makePromo({
        title: 'Glow Package Discount',
        description:
            'Book any two signature services this month and enjoy 15% off your total. '
            + 'Perfect for pre-wedding prep or a mid-year reset.',
        starts_at: addDays(today(), -5),
        ends_at: addDays(today(), 25),
        is_active: true,
    }),
    makePromo({
        title: 'Loyalty Week: Free Manicure Upgrade',
        description:
            'Book a Gelish Manicure during Loyalty Week and upgrade to Spa Pedicure pricing at no extra cost.',
        starts_at: addDays(today(), 10),
        ends_at: addDays(today(), 17),
        is_active: true,
    }),
    makePromo({
        title: 'Mother’s Day Special',
        description:
            'A relaxing 60-minute massage plus a hydrating facial at a special Mother’s Day rate.',
        starts_at: addDays(today(), -60),
        ends_at: addDays(today(), -53),
        is_active: false,
    }),
]);

/** `"Apr 18, 2026 – May 13, 2026"` — the `validity_label` accessor. */
export function validityLabel(promo) {
    return `${formatDate(promo.starts_at, 'M j, Y')} – ${formatDate(promo.ends_at, 'M j, Y')}`;
}

/** `isCurrentlyValid()` — active AND inside its window. */
export function isCurrentlyValid(promo) {
    return Boolean(promo.is_active) && isWithin(today(), promo.starts_at, promo.ends_at);
}

export const activePromos = computed(() => promos.filter(isCurrentlyValid));

/**
 * The site-wide banner promo.
 *
 * The PHP cached the most recently started active promo for five minutes; the
 * same pick is made here, just without the cache.
 */
export const currentPromo = computed(
    () =>
        [...activePromos.value].sort((a, b) => new Date(b.starts_at) - new Date(a.starts_at))[0] ?? null,
);

/** Upcoming promos, for the admin list's "scheduled" grouping. */
export const upcomingPromos = computed(() =>
    promos.filter((promo) => promo.is_active && new Date(promo.starts_at) > today()),
);

export function findPromo(id) {
    return promos.find((promo) => String(promo.id) === String(id)) ?? null;
}
