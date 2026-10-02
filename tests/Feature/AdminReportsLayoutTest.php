<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Reports screen's layout: dashboard-styled stat cards on top, then one
 * card that holds both the filters and the table they produce.
 *
 * These assertions are structural rather than string-based on purpose. The
 * point is that the filters and the table are inside *one* card wrapper, which
 * a `assertStringContainsString` can neither prove nor refute — so the page is
 * parsed and the two nodes are walked up to the card that contains them.
 */
class AdminReportsLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function reports(): string
    {
        return $this->actingAs($this->makeAdmin(), 'admin')
            ->get('/admin/reports')
            ->assertOk()
            ->getContent();
    }

    /**
     * Scoped to <main>: the sidebar links carry the same words as several
     * cards, and matching those would answer questions about the wrong tree.
     */
    private function xpath(string $html): DOMXPath
    {
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($doc);
    }

    /** The nearest ancestor carrying the shared card class, if there is one. */
    private function card(DOMNode $node): ?DOMElement
    {
        for ($n = $node; $n instanceof DOMElement; $n = $n->parentNode) {
            if (str_contains($n->getAttribute('class'), 'bta-card')) {
                return $n;
            }
        }

        return null;
    }

    private function assertIsCard(DOMNode $node, string $what): DOMElement
    {
        $card = $this->card($node);

        $this->assertNotNull($card, "{$what} should sit inside a card.");

        return $card;
    }

    public function test_the_filters_and_the_table_share_one_card(): void
    {
        // A real booking in the default range, so the table has a `data-row`
        // to find rather than only its empty state.
        $this->makeAppointment($this->makeUser(), null, [
            'status' => AppointmentStatus::Confirmed,
            'preferred_date' => today(),
        ]);

        $xpath = $this->xpath($this->reports());

        // The date fields, not the removed Period dropdown — `name="from"` is the
        // first control in the filter form and the one that bounds the range.
        $filterCard = $this->assertIsCard(
            $xpath->query('//main//input[@name="from"]')->item(0),
            'The Start Date filter'
        );

        $tableCard = $this->assertIsCard(
            $xpath->query('//main//table')->item(0),
            'The revenue table'
        );

        // One card, not two — the old layout had a filter card above a
        // separate table card.
        $this->assertSame(
            $filterCard,
            $tableCard,
            'The filters and the table should live in the same card.',
        );

        // ...and that same card carries the rest of the list-page furniture.
        $inner = $tableCard->ownerDocument->saveHTML($tableCard);

        // `adminTable` hangs off the card's own tag, not its children.
        $this->assertStringContainsString('adminTable(', $tableCard->getAttribute('x-data'));
        $this->assertStringContainsString('entries per page', $inner);
        $this->assertStringContainsString('Export Sales CSV', $inner);
        $this->assertStringContainsString('Export Inventory CSV', $inner);
        $this->assertStringContainsString('data-row', $inner, 'Rows must stay filterable by the search box.');
    }

    public function test_the_page_reads_stat_cards_then_summary_then_filters_then_table(): void
    {
        $html = $this->reports();

        $xpath = $this->xpath($html);

        $this->assertIsCard(
            $xpath->query('//main//p[normalize-space(text())="Total Revenue"]')->item(0),
            'The Total Revenue card'
        );

        // Order is read off the source, which is the only ordering that means
        // anything here: getLineNo() is not meaningful after loadHTML. Each
        // marker is unique to its own block on this page.
        $statCards = strpos($html, 'Total Revenue');
        $summary = strpos($html, 'grouped by');
        $filters = strpos($html, 'name="from"');
        $table = strpos($html, '<table');

        foreach (['the stat cards' => $statCards, 'the summary line' => $summary, 'the filters' => $filters, 'the table' => $table] as $what => $at) {
            $this->assertNotFalse($at, "Could not find {$what} in the page.");
        }

        $this->assertTrue($statCards < $summary, 'The stat cards belong above the combined card.');
        $this->assertTrue($summary < $filters, 'The date-range summary belongs above the filter controls.');
        $this->assertTrue($filters < $table, 'The filters belong above the table, inside the same card.');
    }

    public function test_the_three_stat_cards_use_the_dashboards_shape(): void
    {
        $xpath = $this->xpath($this->reports());

        foreach (['Total Revenue', 'Bookings', 'Services'] as $label) {
            $labelNode = $xpath->query('//main//p[normalize-space(text())="'.$label.'"]')->item(0);

            $this->assertNotNull($labelNode, "Missing the {$label} stat card.");

            $card = $this->assertIsCard($labelNode, "The {$label} card");
            $inner = $card->ownerDocument->saveHTML($card);

            // The Dashboard card's parts, verbatim, so the two cannot drift.
            $this->assertStringContainsString('text-xs font-semibold uppercase tracking-wider text-ink-muted', $inner);
            $this->assertStringContainsString('font-display text-3xl font-bold text-primary', $inner);
            $this->assertStringContainsString('flex h-11 w-11 shrink-0 items-center justify-center rounded-full', $inner);
        }
    }

    public function test_each_stat_card_keeps_the_caption_it_had(): void
    {
        $html = $this->reports();

        $this->assertStringContainsString('Confirmed, in-progress and completed', $html);
        $this->assertStringContainsString('Service lines booked in this period', $html);
    }

    public function test_the_reports_and_dashboard_stat_cards_are_the_same_component(): void
    {
        $admin = $this->makeAdmin();

        $needle = 'flex h-11 w-11 shrink-0 items-center justify-center rounded-full';

        $this->assertStringContainsString($needle, $this->reports());
        $this->assertStringContainsString(
            $needle,
            $this->actingAs($admin, 'admin')->get('/admin')->assertOk()->getContent(),
        );
    }

    public function test_every_control_survives_the_merge(): void
    {
        $html = $this->reports();

        foreach (['name="from"', 'name="to"', 'Start Date', 'End Date'] as $control) {
            $this->assertStringContainsString($control, $html);
        }

        $this->assertStringContainsString('Generate Report', $html);
        $this->assertStringContainsString('Reset', $html);
        $this->assertStringContainsString('Export Sales CSV', $html);
        $this->assertStringContainsString('Export Inventory CSV', $html);
        $this->assertStringContainsString('Total</td>', $html, 'The totals row should still be there.');

        // The filter form posts to itself, unchanged.
        $this->assertMatchesRegularExpression(
            '/<form method="GET" action="[^"]*\/admin\/reports"/',
            $html,
        );
    }

/**
 * The Period dropdown is gone.
 *
 * It duplicated Start Date / End Date and could contradict them: choosing
 * "Monthly" over a three-day range produced a single bucket that read like a
 * whole month. The grouping is derived from the range now.
 *
 * Scoped to the filter form rather than the whole page: the shared table card
 * carries an "entries per page" `<select>`, which has nothing to do with this and
 * must not be mistaken for the dropdown that was removed.
 */
public function test_the_period_dropdown_is_gone(): void
    {
        $html = $this->reports();

        // Nothing posts a bucket size any more.
        $this->assertStringNotContainsString('name="type"', $html);
        $this->assertStringNotContainsString('"type"', $html);

        // And the only two named inputs in the filter form are the dates.
        $filterForm = $this->xpath($html)->query('//main//form[@method="GET"]')->item(0);

        $this->assertNotNull($filterForm, 'The report filter form should still be there.');

        $named = [];

        foreach ($this->xpath($filterForm->ownerDocument->saveHTML($filterForm))->query('//input[@name]') as $input) {
            $named[] = $input->getAttribute('name');
        }

        $this->assertSame(['from', 'to'], array_values(array_filter($named, fn ($n) => $n !== '_token' && $n !== '_method')));
    }
}
