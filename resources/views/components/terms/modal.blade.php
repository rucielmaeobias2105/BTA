@php
    use App\Enums\TermsCategory;
    use App\Models\TermsAndCondition;
    use App\Support\TermsRenderer;

    /*
     * The one Terms dialog, for the whole application.
     *
     * Mounted in both layouts rather than per page, because the triggers are all
     * over the place — a checkbox label on registration, three links in the
     * booking form, two inside the cancel and reschedule dialogs, the admin's
     * preview link — and a dialog that had to be added to each of those screens
     * is a dialog that one of them would eventually be missing.
     *
     * The content is read here rather than fetched on open. MCA Café's version of
     * this dialog fetches from an endpoint, which is the right call when the
     * terms can be megabytes; these are three short documents that every page
     * load would otherwise re-request, and having the text in the HTML means the
     * numbered list is there for a test, a search engine and a reader mode, none
     * of which run the bundle. It also means the dialog opens instantly instead
     * of showing a spinner.
     *
     * Reads the same `publishedFor` the standalone page uses, so the modal and
     * `/terms/{category}` can never disagree about which text is live.
     *
     * A view querying here, rather than a controller passing it in, matches how
     * the admin top bar and sidebar already read their badge counts: it is
     * chrome that is identical on every screen, so there is nothing for a
     * controller to vary it by.
     */
    $published = [];

    foreach (TermsCategory::cases() as $case) {
        $terms = TermsAndCondition::publishedFor($case);

        $published[$case->value] = $terms ? [
            'title' => $terms->category->modalTitle(),
            'updated' => $terms->published_at?->format('M j, Y'),
            'html' => TermsRenderer::numbered($terms->content),
        ] : null;
    }
@endphp

<div
    x-data="termsModal(@js($published))"
    x-on:terms-modal.window="show($event.detail)"
    x-on:keydown.escape.window="if (open) close()"
>
    <div
        x-show="open"
        x-cloak
        class="fixed inset-0 z-[70] flex items-end justify-center overflow-y-auto p-4 sm:items-center"
        role="dialog"
        aria-modal="true"
        aria-labelledby="terms-modal-title"
    >
        <div class="modal-backdrop fixed inset-0" x-on:click="close()" aria-hidden="true"></div>

        <div x-on:click.stop class="modal-panel flex max-h-[85vh] w-full max-w-2xl flex-col">
            {{-- The header bar: shield icon, the title, and the close X — the
                 three things MCA Café's dialog has, in this panel's own colours
                 so it belongs to the rest of the site. --}}
            <div class="flex shrink-0 items-center gap-3 rounded-t-2xl bg-primary px-5 py-4 text-cream">
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>
                </svg>

                <h2 id="terms-modal-title" class="font-display text-lg font-semibold" x-text="title"></h2>

                <button
                    type="button"
                    x-on:click="close()"
                    class="ml-auto -mr-1.5 flex h-8 w-8 items-center justify-center rounded-lg text-lg leading-none text-cream/80 transition hover:bg-cream/15 hover:text-cream"
                    aria-label="Close"
                >&times;</button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5">
                <template x-if="current">
                    <div>
                        <p class="mb-4 text-xs text-ink-muted" x-show="meta"></p>

                        {{-- `x-html`, not `x-text`: the content is the admin's own
                             markup, rendered through TermsRenderer into a numbered
                             list, and escaping it here would show a customer the
                             literal tags instead of the terms. --}}
                        <div class="prose-bta" x-html="current?.html"></div>
                    </div>
                </template>

                <template x-if="! current">
                    <p class="py-6 text-center text-sm text-ink-muted">
                        This policy has not been published yet. Please check back later or contact the salon.
                    </p>
                </template>
            </div>

            {{-- One button, bottom right, as specified. --}}
            <div class="flex shrink-0 justify-end border-t border-line/70 bg-linen/50 px-5 py-4">
                <button type="button" class="btn-primary" x-on:click="close()">Close</button>
            </div>
        </div>
    </div>
</div>
