import { reactive, computed } from 'vue';
import { AdminRole } from './enums';

/**
 * Staff accounts.
 *
 * Ported from `database/seeders/AdminSeeder.php`. Therapists (the `staff` role)
 * double as the "Preferred Stylist" list on the booking form, which is why
 * they live in the same table as the administrators.
 */
export const admins = reactive([
    {
        id: 1,
        first_name: 'Maia',
        last_name: 'Arjud',
        full_name: 'Maia Arjud',
        username: 'admin',
        email: 'admin@balaitiarjud.test',
        role: AdminRole.SuperAdmin.value,
        is_active: true,
        profile_photo_path: null,
    },
    {
        id: 2,
        first_name: 'Rina',
        last_name: 'Bautista',
        full_name: 'Rina Bautista',
        username: 'manager',
        email: 'manager@balaitiarjud.test',
        role: AdminRole.Manager.value,
        is_active: true,
        profile_photo_path: null,
    },
    {
        id: 3,
        first_name: 'Jade',
        last_name: 'Panganiban',
        full_name: 'Jade Panganiban',
        username: 'jade',
        email: 'jade@balaitiarjud.test',
        role: AdminRole.Staff.value,
        is_active: true,
        profile_photo_path: null,
    },
    {
        id: 4,
        first_name: 'Marco',
        last_name: 'Soriano',
        full_name: 'Marco Soriano',
        username: 'marco',
        email: 'marco@balaitiarjud.test',
        role: AdminRole.Staff.value,
        is_active: true,
        profile_photo_path: null,
    },
    {
        id: 5,
        first_name: 'Aling',
        last_name: 'Reyes',
        full_name: 'Aling Reyes',
        username: 'aling',
        email: 'aling@balaitiarjud.test',
        role: AdminRole.Staff.value,
        is_active: true,
        profile_photo_path: null,
    },
]);

/** The "Preferred Stylist" picker only offers active therapists. */
export const stylists = computed(() => admins.filter((admin) => admin.is_active));

export function findAdmin(id) {
    return admins.find((admin) => String(admin.id) === String(id)) ?? null;
}

export function findAdminByEmail(email) {
    const needle = String(email ?? '').trim().toLowerCase();

    return admins.find((admin) => admin.email.toLowerCase() === needle) ?? null;
}

/** The super admin, used as the "changed by" actor on seeded status history. */
export function superAdmin() {
    return admins.find((admin) => admin.role === AdminRole.SuperAdmin.value) ?? admins[0] ?? null;
}
