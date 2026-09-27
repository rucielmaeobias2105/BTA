import { computed, reactive, watch } from 'vue';

/**
 * Client-side session.
 *
 * The Blade templates branched on `auth()` / `auth('admin')` and flashed
 * `session('status')` messages after a redirect. There is no server any more, so
 * both guards and the flash bag are kept here in `localStorage` instead. The
 * nav, the layouts and the "signed in as" footers all read from this module, so
 * the demo behaves like a signed-in walkthrough of the original app.
 */

const STORAGE_KEY = 'bta.session.v1';

/**
 * The signed-in demo customer.
 *
 * This is Maria Santos (`users` id 2), chosen because the seeded appointments
 * give her the fullest walkthrough: one in-progress visit, two completed ones,
 * and one completed-but-unrated so the "rate your visit" path stays reachable.
 */
const CUSTOMER = {
    id: 2,
    first_name: 'Maria',
    last_name: 'Santos',
    full_name: 'Maria Santos',
    email: 'maria@example.test',
    phone: '09181234567',
    contact_number: '09181234567',
    address: '18 Rizal St., Patucannay, Tayum, Abra',
    profile_photo_path: null,
    is_active: true,
};

const ADMIN = {
    id: 1,
    first_name: 'Ana',
    last_name: 'Reyes',
    full_name: 'Ana Reyes',
    email: 'admin@balaitiarjud.test',
    role: 'Super Admin',
    role_label: 'Super Admin',
    profile_photo_path: null,
};

function load() {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);

        return raw ? JSON.parse(raw) : {};
    } catch {
        return {};
    }
}

const stored = load();

const state = reactive({
    user: stored.user ?? null,
    admin: stored.admin ?? null,
    flash: stored.flash ?? null,
});

watch(
    state,
    (value) => {
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(value));
        } catch {
            // Private-mode browsers reject writes; the in-memory state is
            // still correct for the lifetime of the tab.
        }
    },
    { deep: true },
);

export const isAuthenticated = computed(() => state.user !== null);
export const isAdminAuthenticated = computed(() => state.admin !== null);
export const currentUser = computed(() => state.user);
export const currentAdmin = computed(() => state.admin);
export const flashMessage = computed(() => state.flash);

/** Any accepted credential signs you in as the demo customer. */
export function signInCustomer() {
    state.user = { ...CUSTOMER };
}

/** Any accepted credential signs you in as the demo administrator. */
export function signInAdmin() {
    state.admin = { ...ADMIN };
}

export function signOutCustomer() {
    state.user = null;
    state.flash = null;
}

export function signOutAdmin() {
    state.admin = null;
    state.flash = null;
}

/** Mirrors `->with('status', ...)` — cleared as soon as it is read. */
export function setFlash(message) {
    state.flash = message;
}

export function consumeFlash() {
    const message = state.flash;

    state.flash = null;

    return message;
}

/** Wipes the demo session, e.g. when signing in as a different role. */
export function resetSession() {
    state.user = null;
    state.admin = null;
    state.flash = null;

    try {
        window.localStorage.removeItem(STORAGE_KEY);
    } catch {
        // Nothing to clean up.
    }
}
