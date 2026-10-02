<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The salon's contact details, and that only one copy of them exists.
 *
 * These facts — where the shop is, how to phone it, where to find it on
 * Facebook — used to be typed into three separate views, and they had already
 * drifted: the footer's `tel:` link dialled +63 900 000 000 while the text beside
 * it, and every other surface, read +63 965 6244 405. A visitor who tapped the
 * number in the footer dialled nobody.
 *
 * So the assertions here are less about the values than about there being a
 * single place they come from. Each surface must render exactly the configured
 * string, and the phone link's `href` must carry the E.164 form of that same
 * number rather than a second, hand-typed one.
 */
class SalonContactDetailsTest extends TestCase
{
    use RefreshDatabase;

    /* ------------------------------------------------------------------ */
    /* 1. The About card                                                    */
    /* ------------------------------------------------------------------ */

    public function test_the_about_card_shows_the_location_phone_and_facebook_page(): void
    {
        $html = $this->get('/about')->assertOk()->getContent();

        $this->assertStringContainsString((string) config('salon.address'), $html);
        $this->assertStringContainsString((string) config('salon.phone_display'), $html);
        $this->assertStringContainsString((string) config('salon.facebook_url'), $html);

        // Labelled, not just present.
        $this->assertStringContainsString('Location', $html);
        $this->assertStringContainsString('Contact Number', $html);
        $this->assertStringContainsString('Facebook', $html);
    }

    public function test_the_about_card_is_where_the_trading_name_lives(): void
    {
        $html = $this->get('/about')->assertOk()->getContent();

        $this->assertStringContainsString('Balai ti Arjud, Glow &amp; Co. Beauty Lounge', $html);
    }

    /** Facebook opens in a new tab, and safely. */
    public function test_the_facebook_link_opens_in_a_new_tab_with_noopener(): void
    {
        $html = $this->get('/about')->assertOk()->getContent();

        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        $links = (new \DOMXPath($doc))->query('//a[@href="'.config('salon.facebook_url').'"]');

        $this->assertGreaterThan(0, $links->length, 'The About card should link to the Facebook page.');

        /** @var \DOMElement $link */
        $link = $links->item(0);
        $this->assertSame('_blank', $link->getAttribute('target'));
        $this->assertStringContainsString('noopener', $link->getAttribute('rel'));
    }

    /* ------------------------------------------------------------------ */
    /* 2. One source of truth                                               */
    /* ------------------------------------------------------------------ */

    /**
     * The phone link's `href` is the same number the page displays.
     *
     * The mismatch this guards against is not hypothetical: the footer carried
     * `tel:+639000000000` next to the text "+63 965 6244 405", so tapping it
     * dialled a number that does not exist. `phone_display` is for a human and
     * `phone_e164` is for the dialer, and they have to describe one number.
     */
    public function test_the_phone_link_dials_the_number_the_page_shows(): void
    {
        foreach (['/about', '/contact', '/'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString(
                'tel:'.config('salon.phone_e164'),
                $html,
                "{$path} should link to the configured phone number.",
            );

            $this->assertStringContainsString(
                (string) config('salon.phone_display'),
                $html,
                "{$path} should show the configured phone number.",
            );

            $this->assertStringNotContainsString(
                'tel:+639000000000',
                $html,
                "{$path} still carries the placeholder phone number.",
            );
        }
    }

    /** Every surface agrees, because they all read the one config value. */
    public function test_every_surface_that_shows_the_details_shows_the_same_values(): void
    {
        foreach (['/about', '/contact', '/'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString((string) config('salon.address'), $html, $path);
        }
    }

    public function test_the_facebook_page_is_reachable_from_the_about_page_the_contact_page_and_the_footer(): void
    {
        foreach (['/about', '/contact', '/'] as $path) {
            $this->assertStringContainsString(
                (string) config('salon.facebook_url'),
                $this->get($path)->assertOk()->getContent(),
                $path,
            );
        }
    }

    /* ------------------------------------------------------------------ */
    /* 3. The values are the salon's                                        */
    /* ------------------------------------------------------------------ */

    /**
     * The actual numbers, so a config typo is caught here rather than by a
     * customer dialling it.
     */
    public function test_the_configured_details_are_the_salons_own(): void
    {
        $this->assertSame('Unit 4, 2 wins Bldg. Abra Kalinga Rd. Patucannay, Tayum, Abra', config('salon.address'));
        $this->assertSame('+63 965 6244 405', config('salon.phone_display'));
        $this->assertSame('+639656244405', config('salon.phone_e164'));
        $this->assertSame('https://www.facebook.com/profile.php?id=61550541591947', config('salon.facebook_url'));
    }

    /**
     * The display form and the dialled form are the same digits.
     *
     * Derived rather than restated, so the two config entries cannot disagree
     * about which number the salon has.
     */
    public function test_the_display_and_dialled_phone_numbers_are_the_same_number(): void
    {
        $digits = fn (string $value) => preg_replace('/\D+/', '', $value);

        $this->assertSame(
            $digits((string) config('salon.phone_display')),
            $digits((string) config('salon.phone_e164')),
        );
    }
}
