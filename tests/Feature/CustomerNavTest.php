<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The customer navbar: every top-level link carries an icon, "My Appointments"
 * sits immediately before the bell in a single cluster, and the profile menu is
 * cut down to the two account actions.
 *
 * "My Appointments" appears twice in the markup by design — once in the desktop
 * nav and once in the mobile list — so the assertions below work on scoped
 * regions rather than the whole page. The home page also uses `fa-calendar-check`
 * in its own hero, which is why icon checks are scoped too.
 *
 * Ordering assertions key off route hrefs rather than `>Label</a>`: the icons
 * live inside each anchor, so a label is no longer wrapped by its own angle
 * brackets.
 */
class CustomerNavTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Memoised: `makeUser()` mints a new email every call, so calling it twice
     * would compare one user's header against another's.
     */
    private ?\App\Models\User $user = null;

    private function user()
    {
        return $this->user ??= $this->makeUser();
    }

    private function html(): string
    {
        return $this->actingAs($this->user())
            ->get(route('home'))
            ->assertOk()
            ->getContent();
    }

    /** The desktop `<nav>` only. */
    private function desktopNav(string $html): string
    {
        return $this->between($html, '<nav class="hidden items-center gap-7 lg:flex"', '</nav>');
    }

    /**
     * The profile menu panel. It is the last thing inside the right-hand
     * cluster, so the mobile panel that follows is a clean end boundary.
     */
    private function dropdown(string $html): string
    {
        return $this->between($html, '<div x-show="open"', '<div x-show="mobileOpen"');
    }

    /** The mobile list. */
    private function mobileNav(string $html): string
    {
        return $this->between($html, 'aria-label="Mobile"', '</nav>');
    }

    private function between(string $html, string $from, string $to): string
    {
        $start = strpos($html, $from);
        $this->assertNotFalse($start, "Expected to find `{$from}` in the rendered header.");

        $end = strpos($html, $to, $start + strlen($from));
        $this->assertNotFalse($end, "Expected to find `{$to}` after `{$from}`.");

        return substr($html, $start, $end - $start);
    }

    /**
     * The menu opens on the user's name. It is the short list now: the
     * name/email header, Profile, Log Out.
     */
    public function test_the_profile_dropdown_holds_only_profile_and_log_out(): void
    {
        $menu = $this->dropdown($this->html());

        $this->assertStringNotContainsString('>Dashboard<', $menu);
        $this->assertStringNotContainsString('My Appointments', $menu);

        // The two that stay, and the header above them, untouched.
        $this->assertStringContainsString('>Profile<', $menu);
        $this->assertStringContainsString('>Log Out</button>', $menu);
        $this->assertStringContainsString($this->user()->email, $menu);
    }

    /**
     * My Appointments is a top-level nav item: in the desktop `<nav>`, with the
     * site's icon style and the house link classes.
     */
    public function test_my_appointments_is_a_top_level_nav_item_with_an_icon(): void
    {
        $nav = $this->desktopNav($this->html());

        // The label is not adjacent to `>` here: the icon sits inside the
        // anchor, so match the anchor's own text node instead.
        $this->assertStringContainsString('My Appointments', $nav);
        $this->assertStringContainsString('fas fa-calendar-check', $nav);

        // `customer-nav-link` is the whole styling story now — the flex row
        // that seats the icon beside the label moved into the class, rather
        // than being spelled out as utilities on whichever link had an icon.
        $this->assertMatchesRegularExpression(
            '/<a\s+href="'.preg_quote(route('appointments.index'), '/').'"\s+class="customer-nav-link\b/',
            $nav,
        );
    }

    /**
     * Every nav item carries the same icon treatment, in the same order, so the
     * bar reads as one set rather than "the one link somebody added an icon to".
     *
     * Asserted on the icon *and* the label, because a bare icon list would still
     * pass if a link had been left bare.
     */
    public function test_every_desktop_nav_link_has_an_icon_and_its_label(): void
    {
        $nav = $this->desktopNav($this->html());

        foreach ([
            'fas fa-house',
            'fas fa-spa',
            'fas fa-tag',
            'fas fa-circle-info',
            'fas fa-phone',
            'fas fa-calendar-check',
        ] as $icon) {
            $this->assertStringContainsString($icon, $nav);
        }

        foreach (['Home', 'Services', 'Promo', 'About Us', 'Contact', 'My Appointments'] as $label) {
            $this->assertStringContainsString($label, $nav);
        }

        // One size class on every glyph, so the run stays visually even.
        $this->assertSame(
            6,
            substr_count($nav, 'customer-nav-icon'),
            'Expected a sized icon on each of the six nav links.',
        );
    }

    /**
     * The nav list and the bell group are siblings inside one cluster, so the
     * space between the last link and the bell is the cluster's own `gap-5`
     * rather than the free space of a three-child `justify-between` row — which
     * is what left "My Appointments" stranded away from the bell.
     */
    public function test_the_nav_list_and_the_bell_share_one_cluster(): void
    {
        $html = $this->html();

        $cluster = strpos($html, 'class="customer-nav-cluster"');
        $nav = strpos($html, '<nav class="hidden items-center gap-7 lg:flex"');
        $bell = strpos($html, 'aria-label="Notifications');

        $this->assertNotFalse($cluster, 'Expected the nav cluster wrapper.');
        $this->assertNotFalse($nav);
        $this->assertNotFalse($bell);

        $this->assertTrue(
            $cluster < $nav && $nav < $bell,
            'Expected cluster → nav list → bell, so the bell abuts the nav.',
        );
    }

    /**
     * Order matters: the link comes after the public items and before the bell.
     */
    public function test_my_appointments_sits_after_contact_and_before_the_bell(): void
    {
        $html = $this->html();

        // By href, not by `>Contact</a>`: every link now carries an icon inside
        // its anchor, so the label is no longer wrapped by its own angle
        // brackets. The route is the stable part.
        $contact = strpos($html, 'href="'.route('contact.create').'"');
        $appointments = strpos($html, 'href="'.route('appointments.index').'"');
        $bell = strpos($html, 'aria-label="Notifications');

        $this->assertNotFalse($contact);
        $this->assertNotFalse($appointments);
        $this->assertNotFalse($bell);

        $this->assertTrue(
            $contact < $appointments && $appointments < $bell,
            'Expected Contact → My Appointments → Bell → profile menu.',
        );
    }

    /** A relocation only: the link still points at the same route. */
    public function test_the_link_still_points_at_the_appointments_index(): void
    {
        $this->assertStringContainsString(
            'href="'.route('appointments.index').'"',
            $this->html(),
        );
    }

    /**
     * It collapses into the mobile list with the other top-level items rather
     * than down with the account actions.
     */
    public function test_the_mobile_menu_carries_it_too(): void
    {
        $mobile = $this->mobileNav($this->html());

        $this->assertStringContainsString('My Appointments', $mobile);
        $this->assertStringContainsString('fas fa-calendar-check', $mobile);

        // Grouped with the public items, so it precedes the divider. By href,
        // for the same reason as the desktop ordering test.
        $contact = strpos($mobile, 'href="'.route('contact.create').'"');
        $appointments = strpos($mobile, 'href="'.route('appointments.index').'"');
        $divider = strpos($mobile, 'bta-divider');

        $this->assertTrue(
            $contact < $appointments && $appointments < $divider,
            'Expected My Appointments in the mobile top-level group, after Contact.',
        );
    }

    /**
     * The hamburger rows carry the same icon + label pairing as the desktop nav,
     * so the menu does not become a different-looking list on small screens.
     */
    public function test_the_mobile_rows_share_the_icon_treatment(): void
    {
        $mobile = $this->mobileNav($this->html());

        foreach (['Home', 'Services', 'Promo', 'About Us', 'Contact', 'My Appointments'] as $label) {
            $this->assertMatchesRegularExpression(
                '/<i class="fas fa-[a-z-]+ customer-nav-icon"[^>]*><\/i>\s*'.$label.'/',
                $mobile,
                "Expected the mobile \"{$label}\" row to lead with its icon.",
            );
        }

        $this->assertStringContainsString('customer-nav-mobile-link', $mobile);
    }

    /**
     * The desktop nav is `hidden lg:flex` and the profile cluster is
     * `hidden sm:block`, so each link is only ever shown at its own breakpoint.
     */
    public function test_the_responsive_breakpoints_are_unchanged(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('<nav class="hidden items-center gap-7 lg:flex"', $html);
        $this->assertStringContainsString('class="relative hidden sm:block"', $html);
        $this->assertStringContainsString('text-primary lg:hidden', $html);
    }

    /**
     * `Nav::CUSTOMER` already maps `appointments.*`, so the promoted link still
     * highlights on the appointments pages.
     */
    public function test_the_link_still_highlights_on_the_appointments_pages(): void
    {
        $nav = $this->desktopNav(
            $this->actingAs($this->user())
                ->get(route('appointments.index'))
                ->assertOk()
                ->getContent(),
        );

        $this->assertStringContainsString('customer-nav-link-active', $nav);
    }

    /**
     * A guest must not get the link at all: there are no appointments to show.
     * Scoped to the header, since the home page hero legitimately uses the same
     * icon.
     */
    public function test_a_guest_does_not_see_the_link(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('My Appointments', $this->desktopNav($html));
        $this->assertStringNotContainsString('My Appointments', $this->mobileNav($html));
        $this->assertStringNotContainsString('fa-calendar-check', $this->desktopNav($html));
    }

    /**
     * The toggles use `x-on:`, not the `@click` shorthand.
     *
     * Blade compiles a handful of directives (`@class`, `@checked`, `@disabled`,
     * …) and leaves every other `@word` as literal text, so `@click` rendered as
     * an inert attribute — the profile menu and the mobile menu could not be
     * opened at all. This is the third time that shorthand has turned up in
     * this codebase, so it gets a guard rather than another silent fix.
     */
    public function test_no_uncompiled_click_shorthand_is_left_in_the_nav(): void
    {
        $source = file_get_contents(resource_path('views/partials/customer/nav.blade.php'));

        // Strip Blade comments — this one explains the shorthand in prose.
        $source = preg_replace('/\{\{--.*?--\}\}/s', '', $source);

        $this->assertDoesNotMatchRegularExpression('/(?<!x-on:)@click/', $source);
        $this->assertStringContainsString('x-on:click="toggle()"', $source);
        $this->assertStringContainsString('x-on:click.outside="close()"', $source);
        $this->assertStringContainsString('x-on:click="mobileOpen = ! mobileOpen"', $source);
    }
}
