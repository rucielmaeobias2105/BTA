/**
 * Enumerations.
 *
 * The PHP backed each of these with a backed enum carrying `label()` and
 * `badge()` methods. They are plain frozen objects here, with the same shape, so
 * a ported view can keep reading `status.badge` and `status.label`.
 */

function makeEnum(values, labels, badges) {
    return Object.freeze(
        Object.fromEntries(
            Object.entries(values).map(([key, value]) => [
                value,
                Object.freeze({
                    value,
                    label: labels[value] ?? value,
                    badge: badges?.[value] ?? value,
                }),
            ]),
        ),
    );
}

/** `App\Enums\AppointmentStatus` */
export const AppointmentStatus = makeEnum(
    { Pending: 'pending', Confirmed: 'confirmed', InProgress: 'in_progress', Completed: 'completed', Cancelled: 'cancelled' },
    { pending: 'Pending', confirmed: 'Confirmed', in_progress: 'In Progress', completed: 'Completed', cancelled: 'Cancelled' },
    { in_progress: 'progress' },
);

export const APPOINTMENT_STATUS_VALUES = Object.keys(AppointmentStatus);

/** Only these two may still be cancelled, rescheduled or rated by a customer. */
export const CUSTOMER_ACTIONABLE_STATUSES = [AppointmentStatus.Pending.value, AppointmentStatus.Confirmed.value];

/** Options for the customer-facing status filter (no action buttons on the list). */
export const APPOINTMENT_FILTER_OPTIONS = [
    { value: 'all', label: 'All' },
    { value: 'pending', label: 'Pending' },
    { value: 'confirmed', label: 'Confirmed' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
];

/** `App\Enums\DownPaymentStatus` */
export const DownPaymentStatus = makeEnum(
    { Unverified: 'unverified', Verified: 'verified', Rejected: 'rejected', NotRequired: 'not_required' },
    {
        unverified: 'Awaiting Verification',
        verified: 'Verified',
        rejected: 'Rejected',
        not_required: 'Not Required',
    },
    { unverified: 'pending', verified: 'confirmed', rejected: 'cancelled', not_required: 'gold' },
);

export const DOWN_PAYMENT_STATUS_VALUES = Object.keys(DownPaymentStatus);

/** `App\Enums\ItemTag` */
export const ItemTag = makeEnum(
    { Available: 'available', LowStock: 'low_stock', BestSeller: 'best_seller', SoldOut: 'sold_out' },
    { available: 'Available', low_stock: 'Low Stock', best_seller: 'Best Seller', sold_out: 'Sold Out' },
    { available: 'available', low_stock: 'lowstock', best_seller: 'bestseller', sold_out: 'soldout' },
);

export const ITEM_TAG_VALUES = Object.keys(ItemTag);

/** Manual-override tags an admin may assign. "available" is the reset. */
export const ASSIGNABLE_TAGS = [
    { value: 'low_stock', label: 'Low Stock' },
    { value: 'sold_out', label: 'Sold Out / Unavailable' },
    { value: 'best_seller', label: 'Best Seller' },
    { value: 'available', label: 'Available (clear tag)' },
];

/** `App\Enums\TermsCategory` */
export const TermsCategory = makeEnum(
    { Booking: 'booking', Cancellation: 'cancellation', Rescheduling: 'rescheduling' },
    { booking: 'Booking', cancellation: 'Cancellation', rescheduling: 'Rescheduling' },
);

/** `App\Enums\InquiryTopic` */
export const InquiryTopic = makeEnum(
    {
        GeneralInquiry: 'general_inquiry',
        BookingQuestion: 'booking_question',
        ServiceQuestion: 'service_question',
        PricingQuestion: 'pricing_question',
        ProductInquiry: 'product_inquiry',
        FeedbackComplaint: 'feedback_complaint',
        Others: 'others',
    },
    {
        general_inquiry: 'General Inquiry',
        booking_question: 'Booking Question',
        service_question: 'Service Question',
        pricing_question: 'Pricing / Packages',
        product_inquiry: 'Product Inquiry',
        feedback_complaint: 'Feedback or Complaint',
        others: 'Others',
    },
);

export const INQUIRY_TOPIC_VALUES = Object.keys(InquiryTopic);

/** `App\Enums\ChangedBy` */
export const ChangedBy = makeEnum(
    { Admin: 'admin', Customer: 'customer', System: 'system' },
    { admin: 'Admin', customer: 'Customer', system: 'System' },
);

/** `App\Enums\AdminRole` */
export const AdminRole = makeEnum(
    { SuperAdmin: 'super_admin', Manager: 'manager', Staff: 'staff' },
    { super_admin: 'Super Admin', manager: 'Manager', staff: 'Staff' },
);

/**
 * Every gated ability in the admin panel, and what each role may do.
 *
 * The routes used to enforce these through `admin.role:*` middleware and the
 * views through `@can`. With no server the same matrix drives a `v-can`-style
 * check in the admin layout, so a Staff account still sees a read-only panel.
 */
export const ADMIN_ABILITIES = [
    'dashboard.view',
    'appointments.manage',
    'calendar.view',
    'calendar.manage',
    'catalog.view',
    'catalog.manage',
    'inventory.view',
    'inventory.manage',
    'tags.manage',
    'users.view',
    'users.manage',
    'users.delete',
    'terms.view',
    'terms.manage',
    'reviews.view',
    'reviews.manage',
    'reports.view',
    'promos.manage',
    'messages.manage',
];

const MANAGER_ABILITIES = [
    'dashboard.view',
    'appointments.manage',
    'calendar.view',
    'calendar.manage',
    'catalog.view',
    'catalog.manage',
    'inventory.view',
    'inventory.manage',
    'tags.manage',
    'users.view',
    'users.manage',
    'terms.view',
    'reviews.view',
    'reviews.manage',
    'reports.view',
    'promos.manage',
    'messages.manage',
];

const STAFF_ABILITIES = [
    'dashboard.view',
    'appointments.manage',
    'calendar.view',
    'catalog.view',
    'inventory.view',
    'users.view',
    'reviews.view',
];

/** Abilities granted to a role. Super Admin implicitly holds them all. */
export function abilitiesFor(role) {
    switch (role) {
        case AdminRole.SuperAdmin.value:
            return ADMIN_ABILITIES;
        case AdminRole.Manager.value:
            return MANAGER_ABILITIES;
        default:
            return STAFF_ABILITIES;
    }
}

export function roleCan(role, ability) {
    return abilitiesFor(role).includes(ability);
}
