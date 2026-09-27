import { APP_NAME, upper } from './format';

/**
 * Tab-title handling for in-page sections.
 *
 * A link like `/#offers` does not reload the document, so the title rendered by
 * the router would stay "HOME". Markup contract:
 *   <a href="/#offers" data-tab-title="PROMO | Balai ti Arjud">Promo</a>
 *   <section id="offers" data-tab-title="PROMO | Balai ti Arjud">
 *
 * The section carries the attribute as well, but it may not be rendered at all
 * (the offers block is conditional on an active promo existing), so each link's
 * claim is remembered for its own hash and used as the fallback. Otherwise the
 * click would set the title and the following hash change would immediately
 * reset it back to the page title.
 */
const titlesByHash = new Map();
let listenersBound = false;

function titleFor(hash, pageTitle) {
    if (!hash || hash === '#') return pageTitle;

    try {
        const claimed = document.querySelector(hash)?.dataset.tabTitle;

        if (claimed) return claimed;
    } catch {
        // A malformed hash is just a normal anchor.
    }

    return titlesByHash.get(hash) || pageTitle;
}

/** Builds the canonical `SECTION | Site` title string. */
export function sectionTitle(section) {
    return `${upper(section)} | ${APP_NAME}`;
}

function bind(root) {
    const pageTitle = document.title;

    root.querySelectorAll('a[data-tab-title]').forEach((link) => {
        const title = link.dataset.tabTitle;

        // Recorded on every pass, not just the first, so a re-render after a
        // route change cannot leave the fallback map empty.
        if (link.hash) titlesByHash.set(link.hash, title);

        if (link.dataset.tabTitleBound === '1') return;
        link.dataset.tabTitleBound = '1';

        link.addEventListener('click', () => {
            document.title = title;
        });
    });

    if (!listenersBound) {
        listenersBound = true;

        window.addEventListener('hashchange', () => {
            document.title = titleFor(window.location.hash, document.title);
        });
    }

    document.title = titleFor(window.location.hash, pageTitle);
}

/** Call after each navigation so newly rendered links are picked up. */
export function refreshTabTitles(root = document) {
    bind(root);
}
