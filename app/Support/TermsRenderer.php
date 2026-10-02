<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Turning published Terms & Conditions into a numbered list.
 *
 * The terms are authored in the admin's rich-text editor, so what arrives is
 * whatever HTML that editor produced — in practice an `<h2>` title, an intro
 * paragraph, and then a run of `<h3>` headings each followed by a paragraph. The
 * seeded content numbers those headings by hand ("1. Confirmation"), but nothing
 * *forces* an admin to, and a version written by someone else may well not.
 *
 * The modal is specified as a numbered list, so the numbers cannot be left to
 * whoever typed the copy. This class makes them structural instead: each section
 * becomes an `<li>` of a real `<ol>`, and any number the author already wrote
 * into the heading is stripped so the list does not read "1. 1. Confirmation".
 * The author keeps every bit of their own markup inside the item — the bold in
 * "up to 24 hours" and the link they pasted both survive.
 *
 * Rendered in PHP rather than in the browser for two reasons. The markup is then
 * in the HTML for a test to assert on, and — more importantly — it is in the HTML
 * for a search engine, a reader mode and a print stylesheet, none of which run
 * the bundle.
 *
 * Deliberately tolerant: anything it does not recognise is passed through rather
 * than dropped. A term that mentions something unusual in its body is better
 * shown as written than silently missing from the dialog a customer has to agree
 * to.
 */
class TermsRenderer
{
    /**
     * Render published content as a numbered list.
     *
     * Returns an empty string for nothing published, so a caller can show its
     * own "not published yet" message rather than rendering an empty dialog.
     */
    public static function numbered(?string $content): string
    {
        $content = trim((string) $content);

        if ($content === '') {
            return '';
        }

        /*
         * Sanitised before anything else looks at it.
         *
         * The modal injects this with `x-html`, so every tag here reaches the
         * browser as markup. That is the point — the content is the admin's own
         * rich text and the bold in "up to 24 hours" is part of the terms — but
         * it means the allowlist below is the only thing standing between a
         * pasted `<script>` and every customer who opens the dialog. Trusting
         * "only admins can write here" is not good enough on its own: one
         * compromised or careless account, or an admin pasting a snippet off a
         * website, would be enough.
         *
         * The allowlist is exactly the set the admin editor's toolbar can
         * produce, so this strips nothing a legitimately authored document
         * contains.
         */
        $content = self::sanitise($content);

        if ($content === '') {
            return '';
        }

        // Plain text, typed into the editor as-is. No structure to preserve, so
        // every non-empty line becomes a numbered term.
        if (! self::hasMarkup($content)) {
            return self::listFromLines($content);
        }

        $parts = self::split($content);

        $items = self::itemsFromSections($parts['sections']);
        $html = self::wrap($parts['preamble'], $items, $parts['after']);

        return $html === '' ? '' : $html;
    }

    /**
     * The tags the admin editor's toolbar can produce, and nothing else.
     *
     * @var list<string>
     */
    private const ALLOWED_TAGS = [
        'p', 'br', 'hr',
        'h2', 'h3',
        'ul', 'ol', 'li',
        'strong', 'b', 'em', 'i', 'u',
        'a', 'blockquote',
    ];

    /** Attributes each allowed tag may keep. */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title', 'target', 'rel'],
    ];

    /**
     * Strip anything outside the allowlist.
     *
     * A disallowed tag is removed but its text is kept: someone who pastes a
     * `<div>` wrapper still gets their words, and someone who pastes a
     * `<script>` gets nothing, which is the whole point.
     */
    private static function sanitise(string $html): string
    {
        // Drop the dangerous elements *with* their content first, because
        // unwrapping a `<script>` would publish the JavaScript as body text.
        $html = preg_replace('#<(script|style|iframe|object|embed)\b[^>]*>.*?</\1\s*>#is', '', $html) ?? $html;

        /*
         * Then unwrap any remaining tag that is not on the list.
         *
         * The optional leading slash is captured because a closing tag has to
         * stay a closing tag: emitting `<h3>` for `</h3>` would leave the
         * document unclosed and quietly swallow the rest of the terms into the
         * heading.
         */
        $html = preg_replace_callback(
            '#<(/?)([a-z][a-z0-9]*)(\s[^>]*)?/?>#i',
            function (array $match): string {
                $tag = strtolower($match[2]);

                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    return '';
                }

                // Attributes are meaningless on a closing tag, and were stripped
                // before the sanitiser ran, so only the open form needs them.
                if ($match[1] === '/') {
                    return '</'.$tag.'>';
                }

                /*
                 * Read once, with a default: a trailing group that does not
                 * participate is *absent* from `$match` rather than null, so a
                 * bare `$match[3]` would warn on every plain `<p>`.
                 */
                return '<'.$tag.self::filterAttributes($tag, $match[3] ?? '').'>';
            },
            $html,
        ) ?? $html;

        return $html;
    }

    /** Keep only the attributes the allowlist permits, minus unsafe values. */
    private static function filterAttributes(string $tag, string $attributes): string
    {
        $permitted = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

        if ($permitted === [] || ! preg_match_all('/([a-z-]+)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', $attributes, $found, PREG_SET_ORDER)) {
            return '';
        }

        $kept = '';

        foreach ($found as $attribute) {
            $name = strtolower($attribute[1]);

            if (! in_array($name, $permitted, true)) {
                continue;
            }

            $value = trim($attribute[2], '"\'');

            // A `javascript:` or `data:` URL in an href is the one way an
            // allowed tag can still execute something.
            if ($name === 'href' && preg_match('#^\s*(javascript|data|vbscript)\s*:#i', $value)) {
                continue;
            }

            $kept .= ' '.$name.'="'.e($value).'"';
        }

        return $kept;
    }

    /** Whether the content carries any tag worth treating as structure. */
    private static function hasMarkup(string $content): bool
    {
        return (bool) preg_match('#</?[a-z][a-z0-9]*\b[^>]*>#i', $content);
    }

    /**
     * Break the document into the three things a numbered list needs.
     *
     * @return array{preamble: string, sections: array<int, array{title: string, body: string}>, after: string}
     */
    private static function split(string $content): array
    {
        $preamble = '';
        $sections = [];
        $after = '';

        /*
         * Split on `<h3>` first, then on `<hr>` within each piece.
         *
         * `<hr>` is the editor's Divider button, and whatever an admin puts
         * below it is not a term: it is a note about the copy. Cutting there
         * keeps that note below the list as a footnote instead of welding it onto
         * the end of the last term, where it would read as part of the pricing
         * policy.
         */
        $chunks = preg_split('#<h3\b[^>]*>#i', $content) ?: [$content];

        $preamble = array_shift($chunks);

        foreach ($chunks as $chunk) {
            // `limit: 1` on the heading match — the heading is the first tag.
            $heading = Str::before($chunk, '</h3>');
            $rest = Str::after($chunk, '</h3>');

            [$body, $tail] = self::splitOnDivider($rest);

            $sections[] = ['title' => $heading, 'body' => trim($body)];

            if ($tail !== '') {
                // Anything after a divider belongs to no term, so it is kept out
                // of the list and printed underneath it.
                $after .= $tail;
            }
        }

        return [
            'preamble' => trim((string) $preamble),
            'sections' => $sections,
            'after' => trim($after),
        ];
    }

    /**
     * @return array{0: string, 1: string}  [before the divider, from it onwards]
     */
    private static function splitOnDivider(string $html): array
    {
        if (! preg_match('#<hr\b[^>]*>#i', $html, $match, PREG_OFFSET_CAPTURE)) {
            return [$html, ''];
        }

        $at = $match[0][1];

        return [substr($html, 0, $at), substr($html, $at)];
    }

    /**
     * @param  array<int, array{title: string, body: string}>  $sections
     * @return list<string>
     */
    private static function itemsFromSections(array $sections): array
    {
        $items = [];

        foreach ($sections as $section) {
            $title = self::stripLeadingNumber($section['title']);
            $body = $section['body'];

            $items[] = $title === ''
                ? $body
                : '<h3 class="terms-list__title">'.$title.'</h3>'.$body;
        }

        return $items;
    }

    /**
     * @param  list<string>  $items
     */
    private static function wrap(string $preamble, array $items, string $after): string
    {
        $html = '';

        if ($preamble !== '') {
            $html .= '<div class="terms-list__intro">'.$preamble.'</div>';
        }

        if ($items !== []) {
            $html .= '<ol class="terms-list">'
                .'<li class="terms-list__item">'.implode('</li><li class="terms-list__item">', $items).'</li>'
                .'</ol>';
        }

        if ($after !== '') {
            $html .= '<div class="terms-list__after">'.$after.'</div>';
        }

        return $html;
    }

    /**
     * Plain text, one term per line.
     *
     * A leading bullet is dropped rather than turned into a nested list: the
     * spec for this dialog is a flat numbered list, and a term that was typed as
     * "- arrive 10 minutes early" should still read as one numbered term.
     */
    private static function listFromLines(string $text): string
    {
        $items = [];

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $line = self::stripLeadingNumber($line);
            $line = preg_replace('/^[-•*]\s*/u', '', $line) ?? $line;

            $items[] = e($line);
        }

        if ($items === []) {
            return '';
        }

        return '<ol class="terms-list">'
            .'<li class="terms-list__item">'.implode('</li><li class="terms-list__item">', $items).'</li>'
            .'</ol>';
    }

    /**
     * Remove a hand-written "1." or "1)" from the front of a heading.
     *
     * Only a number followed by a dot or bracket, and only at the very start, so
     * a heading that legitimately opens with a figure — "24 Hours Notice" — is
     * left alone. The list supplies the number either way.
     */
    private static function stripLeadingNumber(string $text): string
    {
        return preg_replace('/^\s*\d+\s*[.)]\s*/u', '', trim($text)) ?? $text;
    }
}
