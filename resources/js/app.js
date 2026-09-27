import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

/**
 * Password visibility toggle used by the auth + profile forms.
 *
 * Markup contract:
 *   <input type="password" data-password-toggle />
 *   <button type="button" data-password-toggle-for="<input id>">Show</button>
 *
 * Kept as a tiny vanilla helper (rather than Alpine) so it works identically on
 * pages that do not initialise Alpine, e.g. the standalone admin login screen.
 */
function initPasswordToggles(root = document) {
    root.querySelectorAll('[data-password-toggle]').forEach((button) => {
        if (button.dataset.passwordToggleBound === '1') return;
        button.dataset.passwordToggleBound = '1';

        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.passwordToggleFor);

            if (!input) return;

            const revealed = input.type === 'text';

            input.type = revealed ? 'password' : 'text';
            button.textContent = revealed ? 'Show' : 'Hide';
            button.setAttribute('aria-pressed', String(!revealed));
        });
    });
}

document.addEventListener('DOMContentLoaded', () => initPasswordToggles());

/**
 * Retitles the browser tab when the visitor jumps to an in-page section.
 *
 * A link like `/#offers` does not reload the document, so the server-rendered
 * <title> would stay "HOME". Markup contract:
 *   <a href="/#offers" data-tab-title="PROMO | Balai ti Arjud">Promo</a>
 *   <section id="offers" data-tab-title="PROMO | Balai ti Arjud">
 *
 * The section carries the attribute as well, but it may not be rendered at all
 * (the offers block is conditional on an active promo existing), so each link's
 * claim is remembered for its own hash and used as the fallback. Otherwise the
 * click would set the title and the following hash change would immediately
 * reset it back to the page title.
 */
function initSectionTabTitles(root = document) {
    const pageTitle = document.title;
    const titlesByHash = new Map();

    const titleFor = (hash) => {
        if (!hash || hash === '#') return pageTitle;

        try {
            const claimed = document.querySelector(hash)?.dataset.tabTitle;
            if (claimed) return claimed;
        } catch {
            // A malformed hash is just a normal anchor.
        }

        return titlesByHash.get(hash) || pageTitle;
    };

    root.querySelectorAll('a[data-tab-title]').forEach((link) => {
        const title = link.dataset.tabTitle;

        // Recorded on every pass, not just the first, so a re-init (Alpine
        // re-render, a soft navigation) cannot leave the fallback map empty.
        if (link.hash) titlesByHash.set(link.hash, title);

        if (link.dataset.tabTitleBound === '1') return;
        link.dataset.tabTitleBound = '1';

        link.addEventListener('click', () => {
            document.title = title;
        });
    });

    window.addEventListener('hashchange', () => {
        document.title = titleFor(window.location.hash);
    });

    document.title = titleFor(window.location.hash);
}

document.addEventListener('DOMContentLoaded', () => initSectionTabTitles());

document.addEventListener('alpine:init', () => {
    Alpine.data('dropdown', () => ({
        open: false,
        toggle() {
            this.open = !this.open;
        },
        close() {
            this.open = false;
        },
    }));

    /**
     * Read-only booking summary + 5 star rating live in the same component.
     */
    Alpine.data('starRating', (initial = 0) => ({
        rating: Number(initial) || 0,
        hover: 0,
        get display() {
            return this.hover || this.rating;
        },
        set(value) {
            this.rating = value;
        },
        clear() {
            this.rating = 0;
            this.hover = 0;
        },
    }));
});

// Alpine's npm build does not auto-start.
window.Alpine = Alpine;
Alpine.start();
