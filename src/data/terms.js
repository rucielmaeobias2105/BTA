import { reactive, computed } from 'vue';
import { addDays, addWeeks, subMonths } from '@/lib/dates';
import { TermsCategory } from './enums';

/**
 * Terms & Conditions.
 *
 * Versioned content ported verbatim from `database/seeders/TermsSeeder.php`.
 * Each category keeps a v1 (superseded) and a v2 (published) revision, which is
 * what the admin editor's version list and the customer-facing pages read.
 */

const BOOKING_V1 = `<h2>Booking Terms &amp; Conditions</h2>
<p>These terms govern every appointment made through the Balai ti Arjud online booking system.</p>
<h3>1. Confirmation</h3>
<p>An appointment is <strong>not</strong> confirmed until you receive a confirmation notification from us. A request that is still marked <em>Pending</em> has not yet secured your slot.</p>
<h3>2. Down Payment</h3>
<p>A down payment is required to reserve your slot. Submit the GCash reference number on the booking form. Our team verifies the reference manually &mdash; the down payment shows as <em>Awaiting Verification</em> until then. Unverified down payments may be cancelled after 24 hours.</p>
<h3>3. Arrival</h3>
<p>Please arrive 10 minutes early. Arriving more than <strong>20 minutes late</strong> may shorten or forfeit your treatment.</p>
<h3>4. Allergies &amp; Health</h3>
<p>You are required to disclose allergies, sensitivities and current medications on the booking form. We cannot guarantee suitability of any treatment until we have this information.</p>
<h3>5. Pricing</h3>
<p>Prices shown are per service and may vary by hair length, thickness and variant selected. The final amount is confirmed on your booking summary before treatment begins.</p>`;

const CANCELLATION_V1 = `<h2>Cancellation Policy</h2>
<p>We understand that plans change. Please read this policy before cancelling.</p>
<h3>1. Free Cancellation</h3>
<p>You may cancel or reschedule free of charge up to <strong>24 hours</strong> before your appointment time.</p>
<h3>2. Late Cancellation</h3>
<p>Cancellations made within 24 hours of the appointment time may forfeit the down payment, because the slot and the products used are reserved specifically for you.</p>
<h3>3. No-Show</h3>
<p>Failure to arrive without notice is treated as a late cancellation and the down payment is forfeited.</p>
<h3>4. Cancellations by the Salon</h3>
<p>If we need to cancel &mdash; for example due to an emergency closure or stock unavailability &mdash; you will be notified immediately and your down payment is returned in full.</p>
<h3>5. How to Cancel</h3>
<p>Use the <em>Cancel Appointment</em> action on your appointment, provide a reason, and agree to this policy.</p>`;

const RESCHEDULING_V1 = `<h2>Rescheduling Policy</h2>
<p>Need a different time? Rescheduling is simple, subject to availability.</p>
<h3>1. Free Reschedule</h3>
<p>Reschedule free of charge up to <strong>24 hours</strong> before your appointment.</p>
<h3>2. Availability</h3>
<p>The new date and time are validated against our operating hours and any dates we have blocked. Only slots shown as available can be selected.</p>
<h3>3. Late Reschedule</h3>
<p>Rescheduling within 24 hours depends entirely on slot availability and may forfeit the down payment.</p>
<h3>4. Repeated Changes</h3>
<p>We may ask you to pay a new down payment if an appointment has been rescheduled more than twice.</p>`;

const UPDATE_NOTES = {
    [TermsCategory.Booking.value]: 'manual GCash down payment verification policy',
    [TermsCategory.Cancellation.value]: 'cancellation windows and no-show policy',
    [TermsCategory.Rescheduling.value]: 'availability rules for rescheduled slots',
};

let nextId = 1;

function makeRevision(category, version, content, { is_published, published_at }) {
    return reactive({
        id: nextId++,
        category,
        version,
        content,
        is_published,
        published_at,
        created_by: 1,
    });
}

export const termsDocuments = reactive([]);

for (const [category, base] of [
    [TermsCategory.Booking.value, BOOKING_V1],
    [TermsCategory.Cancellation.value, CANCELLATION_V1],
    [TermsCategory.Rescheduling.value, RESCHEDULING_V1],
]) {
    // v1 — the original, now superseded
    termsDocuments.push(
        makeRevision(category, 1, base, {
            is_published: false,
            published_at: subMonths(today(), 6),
        }),
    );

    // v2 — currently published to customers
    termsDocuments.push(
        makeRevision(
            category,
            2,
            `${base}<hr><p><em>Version 2 &mdash; updated to reflect the current ${UPDATE_NOTES[category]}.</em></p>`,
            {
                is_published: true,
                published_at: addWeeks(today(), -2),
            },
        ),
    );
}

export const TERMS_CATEGORY_VALUES = Object.keys(TermsCategory);

/** The published revision for a category — what customers see. */
export function publishedTerms(category) {
    return (
        termsDocuments.find((doc) => doc.category === category && doc.is_published)
        ?? termsDocuments.find((doc) => doc.category === category)
        ?? null
    );
}

/** Every revision of a category, newest version first. */
export function revisionsFor(category) {
    return termsDocuments
        .filter((doc) => doc.category === category)
        .sort((a, b) => b.version - a.version);
}

export function findTermsDocument(id) {
    return termsDocuments.find((doc) => String(doc.id) === String(id)) ?? null;
}

/** Category counts for the admin index table. */
export const termsSummary = computed(() =>
    TERMS_CATEGORY_VALUES.map((category) => {
        const revisions = revisionsFor(category);
        const published = publishedTerms(category);

        return {
            category,
            label: TermsCategory[category].label,
            published_version: published?.version ?? null,
            published_at: published?.published_at ?? null,
            revision_count: revisions.length,
        };
    }),
);

/** Kept for symmetry with the seeder's `addWeeks` usage. */
export function nextReviewDue() {
    return addWeeks(today(), 12);
}
