<?php

namespace Tests\Feature;

use App\Models\Promo;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the two sections added to the landing page, and the booking row's
 * duration/price line.
 *
 * The promos section reuses the same `.home-promo-*` card as the Promo page, so
 * this asserts the *data* behind it — active and in-window only, never an
 * expired offer — rather than re-asserting the card's markup that
 * `CustomerPromoLayoutTest` already covers.
 *
 * The booking line is here because it is the only place a service's duration is
 * shown to a customer before they commit to it.
 */
class HomePromosAndMostBookedTest extends TestCase
{
    use RefreshDatabase;

    // `TestCase::makeService()` is used as-is: it already defaults a price, a
    // duration and an active flag, which is all these tests need.

    /* ------------------------------------------------------------------ */
    /* Promos on the landing page                                          */
    /* ------------------------------------------------------------------ */

    public function test_the_home_page_shows_active_promos(): void
    {
        $promo = Promo::create([
            'title' => 'Glow Weekend Package',
            'description' => 'Two services and a facial for one price.',
            'starts_at' => today()->subDay(),
            'ends_at' => today()->addDays(6),
            'is_active' => true,
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Current Promos', $html);
        $this->assertStringContainsString($promo->title, $html);
        $this->assertStringContainsString($promo->validity_label, $html);
        $this->assertStringContainsString('SPECIAL OFFERS', $html);
    }

    /** An expired or switched-off offer must never be advertised on the front page. */
    public function test_the_home_page_never_shows_an_inactive_or_expired_promo(): void
    {
        $expired = Promo::create([
            'title' => 'Expired Offer',
            'description' => 'Last month.',
            'starts_at' => today()->subDays(30),
            'ends_at' => today()->subDay(),
            'is_active' => true,
        ]);

        $switchedOff = Promo::create([
            'title' => 'Switched Off Offer',
            'description' => 'Paused.',
            'starts_at' => today()->subDay(),
            'ends_at' => today()->addDays(30),
            'is_active' => false,
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString($expired->title, $html, 'An expired promo must not be advertised.');
        $this->assertStringNotContainsString($switchedOff->title, $html, 'A switched-off promo must not be advertised.');
        $this->assertStringNotContainsString('Current Promos', $html, 'The section hides itself when there is nothing to show.');
    }

    /* ------------------------------------------------------------------ */
    /* Most booked                                                          */
    /* ------------------------------------------------------------------ */

    public function test_the_home_page_shows_a_most_booked_section_with_book_now(): void
    {
        $popular = $this->makeService(['name' => 'Glow Hand Spa', 'category' => 'Manicure & Pedicure']);
        $this->makeService(['name' => 'Something Else', 'category' => 'Manicure & Pedicure']);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('MOST BOOKED', $html);
        $this->assertStringContainsString('Services our clients love', $html);
        $this->assertStringContainsString($popular->name, $html);
        $this->assertStringContainsString('Book Now', $html);

        // The card links through to the booking form with the service chosen.
        $this->assertStringContainsString(
            'href="'.route('appointments.create', ['services' => $popular->slug]).'"',
            $html,
        );
    }

    /** A site with no bookings must still show something under that heading. */
    public function test_the_most_booked_section_is_never_empty(): void
    {
        $this->makeService(['name' => 'Only Service Available', 'category' => 'Manicure & Pedicure']);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('MOST BOOKED', $html);
        $this->assertStringContainsString('Only Service Available', $html);
    }

    /** An inactive service is never advertised, however often it was booked before. */
    public function test_an_inactive_service_is_not_featured_however_often_it_was_booked(): void
    {
        $retired = $this->makeService([
            'name' => 'Retired Treatment',
            'category' => 'Manicure & Pedicure',
            'is_active' => false,
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString($retired->name, $html);
    }

    /* ------------------------------------------------------------------ */
    /* The booking row's duration and price line                           */
    /* ------------------------------------------------------------------ */

    /**
     * A service with no duration must not read "0 min".
     *
     * `(int) null` is 0, so an un-durated service used to claim it takes no time
     * at all. The label is empty instead, and the separator goes with it.
     */
    public function test_a_service_with_no_duration_shows_no_duration_in_booking(): void
    {
        $this->assertSame('', (new Service(['duration_minutes' => null]))->duration_label);
        $this->assertSame('', (new Service(['duration_minutes' => 0]))->duration_label);
        $this->assertSame('30 min', (new Service(['duration_minutes' => 30]))->duration_label);
        $this->assertSame('1 hr', (new Service(['duration_minutes' => 60]))->duration_label);
        $this->assertSame('1 hr 15 min', (new Service(['duration_minutes' => 75]))->duration_label);
    }

    /**
     * No "from" on the booking rows, and no leading separator.
     *
     * The empty-duration case cannot be created on a clean schema —
     * `services.duration_minutes` is NOT NULL in the migrations — so the label's
     * behaviour for a null duration is asserted directly above, and this checks
     * the rendered row around it.
     */
    public function test_the_booking_row_has_no_from_prefix_and_no_leading_separator(): void
    {
        ServiceCategory::create(['name' => 'Nail Care', 'color' => '#E11D48', 'sort_order' => 0]);
        $this->makeService([
            'name' => 'Timed Service',
            'slug' => 'timed-service',
            'category' => 'Nail Care',
            'price' => '799',
            'duration_minutes' => 75,
        ]);

        $user = $this->makeUser();
        $html = $this->actingAs($user)->get(route('appointments.create'))->assertOk()->getContent();

        $this->assertStringContainsString('1 hr 15 min', $html, 'The duration is shown.');
        $this->assertStringNotContainsString('0 min', $html);
        $this->assertStringNotContainsString('>from ₱', $html, 'The "from" prefix was removed from the booking rows.');
        $this->assertStringNotContainsString(' · </span>', $html, 'No separator left stranded where a duration would be.');
    }

    /** A priced service shows its price plainly — no "from" on a fixed figure. */
    public function test_a_fixed_price_shows_without_a_from_prefix(): void
    {
        ServiceCategory::create(['name' => 'Manicure & Pedicure', 'color' => '#E11D48', 'sort_order' => 0]);
        $this->makeService([
            'name' => 'Fixed Price Service',
            'slug' => 'fixed-price-service',
            'category' => 'Manicure & Pedicure',
            'price' => '799',
            'duration_minutes' => 60,
        ]);

        $user = $this->makeUser();
        $html = $this->actingAs($user)->get(route('appointments.create'))->assertOk()->getContent();

        $this->assertStringContainsString('1 hr', $html, 'A service with a duration still shows it.');
        $this->assertStringNotContainsString('>from ₱', $html);
    }
}
