import { reactive, computed } from 'vue';
import { ItemTag } from './enums';

/**
 * Service catalogue.
 *
 * Ported from `database/seeders/ServiceSeeder.php`, keeping the field names the
 * Blade views read (`price`, `duration_minutes`, `is_featured`, …) so the
 * ported pages needed no renaming. The seeder pointed `photo_path` at
 * `storage/photos/*` paths that were never uploaded; the entries below use the
 * images that actually ship in `public/images/`.
 */

const CATALOGUE = [
    {
        name: 'Signature Blowout & Styling',
        slug: 'signature-blowout-styling',
        category: 'Hair Styling',
        price: 450,
        duration_minutes: 60,
        description: 'A wash, blow-dry and finish tailored to your hair type and event.',
        photo_path: '/images/service-1.jpg',
        is_featured: true,
        variants: [
            { name: 'Short Hair', price: 400, duration_minutes: null, is_default: true },
            { name: 'Medium Hair', price: 450, duration_minutes: null, is_default: false },
            { name: 'Long Hair', price: 550, duration_minutes: 90, is_default: false },
        ],
    },
    {
        name: 'Rebonding / Straightening',
        slug: 'rebonding-straightening',
        category: 'Hair Care',
        price: 2800,
        duration_minutes: 180,
        description: 'Chemical straightening for a smooth, manageable finish. Includes post-treatment care.',
        photo_path: null,
        is_featured: false,
        variants: [
            { name: 'Short Hair', price: 2800, duration_minutes: null, is_default: true },
            { name: 'Long Hair', price: 3800, duration_minutes: 240, is_default: false },
        ],
    },
    {
        name: 'Color & Highlights',
        slug: 'color-highlights',
        category: 'Hair Care',
        price: 2200,
        duration_minutes: 150,
        description: 'Full colour change or highlight placement with gloss and blow-dry.',
        photo_path: null,
        is_featured: false,
        variants: [
            { name: 'Global Color', price: 2200, duration_minutes: null, is_default: true },
            { name: 'Highlights', price: 3200, duration_minutes: 210, is_default: false },
            { name: 'Balayage', price: 4200, duration_minutes: 240, is_default: false },
        ],
    },
    {
        name: 'Glow Manicure',
        slug: 'glow-manicure',
        category: 'Nail Care',
        price: 350,
        duration_minutes: 45,
        description: 'Cleanse, shape, cuticle care and a high-shine finish.',
        photo_path: null,
        is_featured: true,
        variants: [
            { name: 'Classic', price: 350, duration_minutes: null, is_default: true },
            { name: 'With Nail Art', price: 450, duration_minutes: 60, is_default: false },
        ],
    },
    {
        name: 'Gelish Manicure',
        slug: 'gelish-manicure',
        category: 'Nail Care',
        price: 600,
        duration_minutes: 75,
        description: 'Long-wear gel colour with a strengthening base and top coat.',
        photo_path: '/images/1.jpg',
        is_featured: true,
        variants: [
            { name: 'Gelish Manicure', price: 600, duration_minutes: null, is_default: true },
            { name: 'Gelish with Nail Art', price: 750, duration_minutes: 90, is_default: false },
        ],
    },
    {
        name: 'Classic Pedicure',
        slug: 'classic-pedicure',
        category: 'Nail Care',
        price: 400,
        duration_minutes: 60,
        description: 'Foot soak, exfoliation, nail shaping and polish.',
        photo_path: null,
        is_featured: false,
        variants: [],
    },
    {
        name: 'Spa Pedicure',
        slug: 'spa-pedicure',
        category: 'Nail Care',
        price: 650,
        duration_minutes: 90,
        description: 'Aromatic soak, sugar scrub, mask, massage and polish.',
        photo_path: null,
        is_featured: true,
        variants: [
            { name: 'Spa Pedicure', price: 650, duration_minutes: null, is_default: true },
            { name: 'Spa Pedicure + Paraffin', price: 850, duration_minutes: 105, is_default: false },
        ],
    },
    {
        name: 'Classic Eyelash Extensions',
        slug: 'classic-eyelash-extensions',
        category: 'Lash & Brow',
        price: 1800,
        duration_minutes: 120,
        description: 'Hand-applied individual lashes for a natural, defined look.',
        photo_path: null,
        is_featured: false,
        variants: [
            { name: 'Classic Set', price: 1800, duration_minutes: null, is_default: true },
            { name: 'Refill (2-3 weeks)', price: 1200, duration_minutes: 90, is_default: false },
        ],
    },
    {
        name: 'Volume Lash Extensions',
        slug: 'volume-lash-extensions',
        category: 'Lash & Brow',
        price: 2200,
        duration_minutes: 150,
        description: 'Denser, fuller lashes mapped to your eye shape.',
        photo_path: '/images/9.jpg',
        is_featured: true,
        variants: [
            { name: 'Volume Set', price: 2200, duration_minutes: null, is_default: true },
            { name: 'Volume Refill', price: 1500, duration_minutes: 105, is_default: false },
        ],
    },
    {
        name: 'Brow Lamination & Tint',
        slug: 'brow-lamination-tint',
        category: 'Lash & Brow',
        price: 950,
        duration_minutes: 75,
        description: 'Lifted, brushed-up brows with a tint to suit your hair colour.',
        photo_path: null,
        is_featured: false,
        variants: [],
    },
    {
        name: 'Hydrating Facial',
        slug: 'hydrating-facial',
        category: 'Facial',
        price: 1100,
        duration_minutes: 75,
        description: 'Gentle cleanse, exfoliation, hydrating mask and massage.',
        photo_path: null,
        is_featured: false,
        variants: [],
    },
    {
        name: 'Brightening Facial',
        slug: 'brightening-facial',
        category: 'Facial',
        price: 1500,
        duration_minutes: 90,
        description: 'Targeted treatment for dull or uneven skin tone.',
        photo_path: '/images/10.jpg',
        is_featured: true,
        variants: [
            { name: 'Brightening Facial', price: 1500, duration_minutes: null, is_default: true },
            { name: 'Brightening + Peel', price: 2000, duration_minutes: 105, is_default: false },
        ],
    },
    {
        name: 'Relaxing Massage (60 min)',
        slug: 'relaxing-massage-60-min',
        category: 'Massage & Spa',
        price: 900,
        duration_minutes: 60,
        description: 'A soothing full-body massage to unwind tension.',
        photo_path: null,
        is_featured: false,
        variants: [
            { name: '60 minutes', price: 900, duration_minutes: null, is_default: true },
            { name: '90 minutes', price: 1250, duration_minutes: 90, is_default: false },
        ],
    },
    {
        name: 'Aromatherapy Massage (90 min)',
        slug: 'aromatherapy-massage-90-min',
        category: 'Massage & Spa',
        price: 1500,
        duration_minutes: 90,
        description: 'Essential-oil massage tailored for deep relaxation.',
        photo_path: '/images/here.jpg',
        is_featured: false,
        variants: [],
    },
    {
        name: 'Herbal Steam & Body Scrub',
        slug: 'herbal-steam-body-scrub',
        category: 'Massage & Spa',
        price: 1200,
        duration_minutes: 90,
        description: 'Warm herbal steam followed by a full-body exfoliation.',
        photo_path: null,
        is_featured: false,
        variants: [],
    },
    {
        name: 'Brazilian Waxing',
        slug: 'brazilian-waxing',
        category: 'Waxing & Threading',
        price: 650,
        duration_minutes: 45,
        description: 'Professional waxing with sensitive-skin formula.',
        photo_path: null,
        is_featured: false,
        variants: [],
    },
    {
        name: 'Threading',
        slug: 'threading',
        category: 'Waxing & Threading',
        price: 200,
        duration_minutes: 30,
        description: 'Precise eyebrow and facial threading.',
        photo_path: null,
        is_featured: false,
        variants: [],
    },
    {
        name: 'Occasion Makeup',
        slug: 'occasion-makeup',
        category: 'Makeup',
        price: 1800,
        duration_minutes: 90,
        description: 'Full-face makeup for events, photos and celebrations.',
        photo_path: null,
        is_featured: false,
        variants: [],
    },
    {
        name: 'Bridal Glow Package',
        slug: 'bridal-glow-package',
        category: 'Packages',
        price: 12500,
        duration_minutes: 300,
        description: 'A full pre-wedding journey: facial, manicure, lashes, hair and makeup trial.',
        photo_path: null,
        is_featured: true,
        variants: [
            { name: 'Bridal Glow', price: 12500, duration_minutes: null, is_default: true },
            { name: 'Bridal Glow + Extensions', price: 16800, duration_minutes: 420, is_default: false },
        ],
    },
];

function slugify(value) {
    return String(value)
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');
}

/** `"1 hr 30 min"` / `"45 min"` — the `duration_label` accessor. */
export function durationLabel(minutes) {
    const total = Number(minutes) || 0;

    if (total < 60) return `${total} min`;

    const hours = Math.floor(total / 60);
    const rest = total % 60;

    return rest === 0 ? `${hours} hr` : `${hours} hr ${rest} min`;
}

function hydrate(entry, id) {
    return reactive({
        id,
        name: entry.name,
        slug: entry.slug ?? slugify(entry.name),
        category: entry.category,
        price: entry.price,
        duration_minutes: entry.duration_minutes,
        description: entry.description,
        photo_path: entry.photo_path ?? null,
        is_active: true,
        is_featured: entry.is_featured ?? false,
        variants: (entry.variants ?? []).map((variant, index) => ({
            id: id * 100 + index + 1,
            service_id: id,
            name: variant.name,
            price: variant.price,
            duration_minutes: variant.duration_minutes,
            is_default: variant.is_default ?? false,
        })),
    });
}

export const services = reactive(CATALOGUE.map(hydrate));

/** The default variant, falling back to the cheapest, matching the seeder. */
export function defaultVariant(service) {
    return service?.variants?.find((variant) => variant.is_default) ?? service?.variants?.[0] ?? null;
}

/** `ServiceVariant::effectiveDuration()` — variant override, else the service. */
export function effectiveDuration(service, variant) {
    return variant?.duration_minutes ?? service?.duration_minutes ?? 0;
}

export const activeServices = computed(() => services.filter((service) => service.is_active));
export const featuredServices = computed(() => activeServices.value.filter((service) => service.is_featured));
export const serviceCategories = computed(() => [...new Set(activeServices.value.map((s) => s.category))].sort());

/** Route key is the slug, so `/services/glow-manicure` resolves through this. */
export function findService(slugOrId) {
    return (
        services.find((service) => service.slug === slugOrId)
        ?? services.find((service) => String(service.id) === String(slugOrId))
        ?? null
    );
}

export function servicesByCategory(category) {
    if (!category) return activeServices.value;

    return activeServices.value.filter((service) => service.category === category);
}

/** Free-text search across name, description and category. */
export function searchServices(term) {
    const needle = String(term ?? '').trim().toLowerCase();

    if (!needle) return activeServices.value;

    return activeServices.value.filter((service) =>
        [service.name, service.description, service.category].some((field) =>
            String(field ?? '').toLowerCase().includes(needle),
        ),
    );
}

/** True when every linked item is sold out — the `is_unavailable` accessor. */
export function isServiceUnavailable(service, inventoryItems) {
    const linked = inventoryUsage(service, inventoryItems);

    return linked.length > 0 && linked.every((item) => item.status_tag === ItemTag.SoldOut.value || item.quantity <= 0);
}

/** Resolves a service's linked inventory, from the map in `inventory.js`. */
export function inventoryUsage(service, usageMap) {
    return Object.entries(usageMap?.[service.name] ?? {}).map(([sku, quantityPerService]) => ({ sku, quantity_per_service: quantityPerService }));
}

export function serviceByName(name) {
    return services.find((service) => service.name === name) ?? null;
}
