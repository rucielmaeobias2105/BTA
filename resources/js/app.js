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
