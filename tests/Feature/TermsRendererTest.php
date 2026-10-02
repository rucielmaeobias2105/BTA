<?php

namespace Tests\Feature;

use App\Support\TermsRenderer;
use Tests\TestCase;

/**
 * The numbered list the Terms dialog is built from.
 *
 * Unit tests rather than feature tests, because the interesting cases are inputs
 * no page produces today — content typed as plain text, a draft whose author
 * forgot to number the headings, a document with no headings at all. All of them
 * arrive from a column an admin can type into, so all of them have to render
 * something a customer can agree to rather than a broken dialog.
 *
 * The rule being protected throughout: the *numbering* is structural. It comes
 * from the `<ol>`, never from the copy, so an admin who numbers their headings
 * and one who does not both get exactly one set of numbers.
 */
class TermsRendererTest extends TestCase
{
    public function test_nothing_published_renders_nothing(): void
    {
        // An empty string rather than an empty list: the caller decides what an
        // unpublished policy says, and an empty `<ol></ol>` would render as a
        // numbered list with no items.
        $this->assertSame('', TermsRenderer::numbered(null));
        $this->assertSame('', TermsRenderer::numbered(''));
        $this->assertSame('', TermsRenderer::numbered('   '));
    }

    public function test_each_section_becomes_a_numbered_item(): void
    {
        $html = TermsRenderer::numbered(
            '<h2>Booking Terms</h2><p>Intro.</p><h3>Confirmation</h3><p>Not confirmed until…</p><h3>Arrival</h3><p>Be early.</p>',
        );

        $this->assertStringContainsString('<ol class="terms-list">', $html);
        $this->assertSame(2, substr_count($html, 'terms-list__item'));
        $this->assertStringContainsString('Confirmation', $html);
        $this->assertStringContainsString('Arrival', $html);
    }

    public function test_a_hand_written_number_is_stripped_so_the_list_numbers_itself(): void
    {
        $html = TermsRenderer::numbered('<h3>1. Confirmation</h3><p>Body.</p>');

        // The seeded content numbers its headings by hand. Left alone, that
        // would render as "1. 1. Confirmation" against the list's own counter.
        $this->assertStringNotContainsString('1. Confirmation', $html);
        $this->assertStringContainsString('Confirmation', $html);
    }

    public function test_a_leading_figure_that_is_not_a_number_is_left_alone(): void
    {
        $html = TermsRenderer::numbered('<h3>24 Hours Notice</h3><p>Body.</p>');

        // "24" is a quantity here, not an ordinal, and must survive.
        $this->assertStringContainsString('24 Hours Notice', $html);
    }

    public function test_the_documents_own_title_and_standfirst_sit_above_the_list(): void
    {
        $html = TermsRenderer::numbered(
            '<h2>Cancellation Policy</h2><p>We understand that plans change.</p><h3>Free Cancellation</h3><p>Up to 24 hours.</p>',
        );

        $this->assertLessThan(
            strpos($html, '<ol'),
            strpos($html, 'We understand that plans change.'),
            'The standfirst must come before the list, not inside it.',
        );
    }

    public function test_markup_inside_a_term_is_preserved(): void
    {
        $html = TermsRenderer::numbered(
            '<h3>Late Cancellation</h3><p>May forfeit the <strong>down payment</strong>. See <a href="/x">policy</a>.</p>',
        );

        // The admin's own emphasis and links are part of the terms; flattening
        // them to plain text would be silently rewriting a legal document.
        $this->assertStringContainsString('<strong>down payment</strong>', $html);
        $this->assertStringContainsString('href="/x"', $html);
    }

    public function test_content_after_a_divider_is_kept_out_of_the_list(): void
    {
        $html = TermsRenderer::numbered(
            '<h3>Pricing</h3><p>Final amount.</p><hr><p><em>Version 2 — updated.</em></p>',
        );

        // The seeder appends its version note after an `<hr>`. That is metadata
        // about the copy, not a term, and it would otherwise be welded onto the
        // pricing policy as if it were part of it.
        $this->assertStringContainsString('terms-list__after', $html);
        $this->assertGreaterThan(
            strpos($html, '</ol>'),
            strpos($html, 'terms-list__after'),
            'The version note must come after the list, not inside the last term.',
        );
    }

    public function test_plain_text_is_escaped(): void
    {
        $html = TermsRenderer::numbered('A & B and a "quoted" phrase.');

        $this->assertStringContainsString('&amp;', $html);
        $this->assertStringNotContainsString('A & B', $html);
    }

    /**
     * A pasted `<script>` loses its *content*, not just its tags.
     *
     * Unwrapping it would publish the JavaScript as body text; the safe reading
     * of a `<script>` in a legal document is that none of it is prose, so it goes
     * entirely.
     */
    public function test_a_pasted_script_is_removed_whole(): void
    {
        $html = TermsRenderer::numbered('Be on time. <script>alert(1)</script> Bring ID.');

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringContainsString('Be on time.', $html);
        $this->assertStringContainsString('Bring ID.', $html);
    }

    /**
     * The one that matters most: the dialog injects this with `x-html`, so
     * anything the allowlist does not cover executes.
     */
    public function test_markup_outside_the_editor_allowlist_never_survives(): void
    {
        $html = TermsRenderer::numbered(
            '<h3>Pricing</h3><p>Fine.</p><script>fetch("/steal")</script><img src=x onerror="alert(1)">'
            .'<a href="javascript:alert(1)">click</a><iframe src="//evil"></iframe>',
        );

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('<img', $html);

        // The words survive even though the wrapper did not — a pasted <div>
        // should not take the copy with it.
        $this->assertStringContainsString('Fine.', $html);
    }

    public function test_a_safe_link_keeps_its_href(): void
    {
        $html = TermsRenderer::numbered('<p>See <a href="/terms/cancellation">the policy</a>.</p>');

        $this->assertStringContainsString('href="/terms/cancellation"', $html);
    }

    public function test_plain_text_becomes_a_numbered_list(): void
    {
        $html = TermsRenderer::numbered("Be on time.\n\n- Bring your own towels.\nCancel early if you can.");

        $this->assertStringContainsString('<ol class="terms-list">', $html);
        $this->assertSame(3, substr_count($html, 'terms-list__item'));
        $this->assertStringContainsString('Bring your own towels.', $html);
    }

    public function test_a_document_with_headings_but_no_intro_still_renders(): void
    {
        $html = TermsRenderer::numbered('<h3>Only Heading</h3><p>Body.</p>');

        $this->assertStringContainsString('terms-list__title', $html);
        $this->assertStringNotContainsString('terms-list__intro', $html);
    }
}
