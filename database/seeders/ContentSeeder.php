<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\BlockedDate;
use App\Models\ContactMessage;
use App\Models\Promo;
use App\Models\Service;
use App\Models\SalonSetting;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedBlockedDates();
        $this->seedPromos();
        $this->seedMessages();
    }

    /**
     * Admin Flow 8 — demo closures that the booking form must reject.
     */
    protected function seedBlockedDates(): void
    {
        $admin = Admin::where('role', 'super_admin')->first();
        $settings = SalonSetting::current();

        $blocks = [
            ['offset' => 9, 'service' => null, 'reason' => 'Provincial holiday — salon closed'],
            ['offset' => 23, 'service' => null, 'reason' => 'Team training day'],
            ['offset' => 30, 'service' => 'Volume Lash Extensions', 'reason' => 'Lash stock delivery delayed'],
        ];

        foreach ($blocks as $block) {
            $date = today()->addDays($block['offset']);

            // Never block a day we are already closed on — pick the next open day.
            while (! $settings->isOpenOn($date)) {
                $date->addDay();
            }

            $service = $block['service'] ? Service::where('name', $block['service'])->first() : null;

            BlockedDate::updateOrCreate(
                ['start_date' => $date->toDateString(), 'service_id' => $service?->id],
                [
                    'end_date' => $date->toDateString(),
                    'reason' => $block['reason'],
                    'created_by' => $admin?->id,
                ],
            );
        }

        // A three-day block to exercise the range form.
        $rangeStart = today()->addDays(45);

        while (! $settings->isOpenOn($rangeStart)) {
            $rangeStart->addDay();
        }

        BlockedDate::updateOrCreate(
            ['start_date' => $rangeStart->toDateString(), 'service_id' => null],
            [
                'end_date' => $rangeStart->copy()->addDays(2)->toDateString(),
                'reason' => 'Inventory audit and deep clean',
                'created_by' => $admin?->id,
            ],
        );
    }

    /**
     * Admin Flow 13 — promos. One active, one upcoming, one expired.
     */
    protected function seedPromos(): void
    {
        $promos = [
            [
                'title' => 'Glow Package Discount',
                'description' => 'Book any two signature services this month and enjoy 15% off your total. '
                    .'Perfect for pre-wedding prep or a mid-year reset.',
                'starts_at' => today()->subDays(5),
                'ends_at' => today()->addDays(25),
                'is_active' => true,
            ],
            [
                'title' => 'Loyalty Week: Free Manicure Upgrade',
                'description' => 'Book a Gelish Manicure during Loyalty Week and upgrade to Spa Pedicure pricing at no extra cost.',
                'starts_at' => today()->addDays(10),
                'ends_at' => today()->addDays(17),
                'is_active' => true,
            ],
            [
                'title' => 'Mother’s Day Special',
                'description' => 'A relaxing 60-minute massage plus a hydrating facial at a special Mother’s Day rate.',
                'starts_at' => today()->subDays(60),
                'ends_at' => today()->subDays(53),
                'is_active' => false,
            ],
        ];

        foreach ($promos as $promo) {
            Promo::updateOrCreate(['title' => $promo['title']], $promo);
        }
    }

    /**
     * Messages submitted through the Contact Us form.
     */
    protected function seedMessages(): void
    {
        $messages = [
            [
                'name' => 'Liza Mercado',
                'email' => 'liza@example.test',
                'topic' => 'pricing_question',
                'message' => 'Hi! Do you have a package for hair colour plus a blowout? Also, how long does rebonding usually take?',
                'is_read' => false,
            ],
            [
                'name' => 'Toni Alvarez',
                'email' => 'toni@example.test',
                'topic' => 'booking_question',
                'message' => 'Can I reschedule my appointment to Saturday morning instead? Thank you!',
                'is_read' => false,
            ],
            [
                'name' => 'Grace Lim',
                'email' => 'grace@example.test',
                'topic' => 'service_question',
                'message' => 'I have a gel allergy. Which of your nail services would you recommend instead?',
                'is_read' => true,
                'admin_reply' => 'Hi Grace! We suggest the Glow Manicure or Classic Pedicure with regular polish — both avoid gel products entirely. Reply here or call us to book.',
                'replied_at' => now()->subDays(2),
            ],
        ];

        foreach ($messages as $message) {
            ContactMessage::updateOrCreate(
                ['email' => $message['email'], 'topic' => $message['topic']],
                $message,
            );
        }
    }
}
