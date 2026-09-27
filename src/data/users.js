import { reactive, computed } from 'vue';

/**
 * Registered customers.
 *
 * Ported from `database/seeders/UserSeeder.php`, including the one deactivated
 * account that makes the admin "toggle status" path demonstrable.
 */

/** `User::deriveUsername()` — the local part of the address, dots stripped. */
function deriveUsername(email) {
    return String(email).split('@')[0].replace(/\W+/g, '');
}

function customer(first, last, email, contact_number, is_active = true) {
    return {
        first_name: first,
        last_name: last,
        full_name: `${first} ${last}`,
        email,
        username: deriveUsername(email),
        contact_number,
        is_active,
        profile_photo_path: null,
    };
}

export const users = reactive([
    { id: 1, ...customer('Juan', 'Dela Cruz', 'juan@example.test', '09171234567') },
    { id: 2, ...customer('Maria', 'Santos', 'maria@example.test', '09181234567') },
    { id: 3, ...customer('Angeline', 'Reyes', 'angeline@example.test', '09191234567') },
    { id: 4, ...customer('Paolo', 'Garcia', 'paolo@example.test', '09201234567') },
    { id: 5, ...customer('Kristine', 'Mendoza', 'kristine@example.test', '09211234567') },
    { id: 6, ...customer('Diego', 'Aquino', 'diego@example.test', '09221234567') },
    { id: 7, ...customer('Test', 'Deactivated', 'inactive@example.test', '09231234567', false) },
]);

export const activeUsers = computed(() => users.filter((user) => user.is_active));

export function findUser(id) {
    return users.find((user) => String(user.id) === String(id)) ?? null;
}

export function findUserByEmail(email) {
    const needle = String(email ?? '').trim().toLowerCase();

    return users.find((user) => user.email.toLowerCase() === needle) ?? null;
}

/** Case-insensitive match across name, email and contact number. */
export function searchUsers(term) {
    const needle = String(term ?? '').trim().toLowerCase();

    if (!needle) return users;

    return users.filter((user) =>
        [user.full_name, user.email, user.contact_number, user.username].some((field) =>
            String(field ?? '').toLowerCase().includes(needle),
        ),
    );
}
