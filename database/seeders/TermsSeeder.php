<?php

namespace Database\Seeders;

use App\Enums\TermsCategory;
use App\Models\Admin;
use App\Models\TermsAndCondition;
use Illuminate\Database\Seeder;

/**
 * Versioned T&C content. The booking / cancellation / reschedule checkboxes on
 * the customer side render whatever is published here (Admin Flow 10).
 */
class TermsSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Admin::where('role', 'super_admin')->first();

        $drafts = [];

        $drafts[TermsCategory::Booking->value] = <<<'HTML'
        <h2>Booking Terms &amp; Conditions</h2>
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
        <p>Prices shown are per service and may vary by hair length, thickness and variant selected. The final amount is confirmed on your booking summary before treatment begins.</p>
        HTML;

        $drafts[TermsCategory::Cancellation->value] = <<<'HTML'
        <h2>Cancellation Policy</h2>
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
        <p>Use the <em>Cancel Appointment</em> action on your appointment, provide a reason, and agree to this policy.</p>
        HTML;

        $drafts[TermsCategory::Rescheduling->value] = <<<'HTML'
        <h2>Rescheduling Policy</h2>
        <p>Need a different time? Rescheduling is simple, subject to availability.</p>
        <h3>1. Free Reschedule</h3>
        <p>Reschedule free of charge up to <strong>24 hours</strong> before your appointment.</p>
        <h3>2. Availability</h3>
        <p>The new date and time are validated against our operating hours and any dates we have blocked. Only slots shown as available can be selected.</p>
        <h3>3. Late Reschedule</h3>
        <p>Rescheduling within 24 hours depends entirely on slot availability and may forfeit the down payment.</p>
        <h3>4. Repeated Changes</h3>
        <p>We may ask you to pay a new down payment if an appointment has been rescheduled more than twice.</p>
        HTML;

        foreach ($drafts as $categoryValue => $content) {
            $category = TermsCategory::from($categoryValue);

            // v1 — the original, now superseded
            TermsAndCondition::updateOrCreate(
                ['category' => $categoryValue, 'version' => 1],
                [
                    'content' => $content,
                    'is_published' => false,
                    'published_at' => now()->subMonths(6),
                    'created_by' => $admin?->id,
                ],
            );

            // v2 — currently published to customers
            TermsAndCondition::updateOrCreate(
                ['category' => $categoryValue, 'version' => 2],
                [
                    'content' => $content
                        .'<hr><p><em>Version 2 &mdash; updated to reflect the current '
                        .$this->updateNote($category).'</em></p>',
                    'is_published' => true,
                    'published_at' => now()->subWeeks(2),
                    'created_by' => $admin?->id,
                ],
            );
        }
    }

    protected function updateNote(TermsCategory $category): string
    {
        return match ($category) {
            TermsCategory::Booking => 'manual GCash down payment verification policy',
            TermsCategory::Cancellation => 'cancellation windows and no-show policy',
            TermsCategory::Rescheduling => 'availability rules for rescheduled slots',
        };
    }
}
