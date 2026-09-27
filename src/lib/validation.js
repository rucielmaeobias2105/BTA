import { computed, reactive } from 'vue';

/**
 * Client-side form validation.
 *
 * The Laravel app validated with Form Request classes, which produced the
 * `$errors` bag every Blade form read through `@error`. The same rules and the
 * same message strings are implemented here, and `createForm()` exposes an
 * identical `errors` object so the ported form components needed no changes to
 * how they surface a failure.
 */

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
const PHONE = /^[0-9+\-\s()]{7,32}$/;

/** Rules that make an empty value a failure rather than a pass. */
const DEMANDING = new Set(['required', 'required_if', 'accepted']);

/** Rules with no client-side equivalent — the native control or the dataset handles them. */
const SKIPPED = new Set(['nullable', 'required_if', 'required', 'exists', 'unique', 'same', 'different']);

function ruleName(rule) {
    return String(rule).split(':')[0];
}

function ruleParameter(rule) {
    return String(rule).split(':')[1] ?? null;
}

function isEmpty(value) {
    if (value === null || value === undefined || value === false) return true;
    if (Array.isArray(value)) return value.length === 0;
    if (typeof value === 'string') return value.trim() === '';

    return false;
}

/**
 * Runs one field's rules and returns the first failure, or null.
 *
 * `all` is the whole payload, which cross-field rules (`confirmed`) read from.
 * `field` is the field's own name, used to locate `{field}_confirmation`.
 *
 * An empty value short-circuits the whole list unless a `required`-family rule
 * claims it. That is how Laravel treats `nullable` fields: leaving the optional
 * password blank on the profile form must not trip `min:8`.
 */
export function validateField(field, value, rules, all = {}, messages = {}) {
    const list = rules ?? [];

    const message = (name, key = '') => messages[`${name}.${key}`] ?? messages[name] ?? null;

    if (isEmpty(value)) {
        const demanded = list.some((rule) => DEMANDING.has(ruleName(rule)));

        return demanded ? message('required') ?? 'This field is required.' : null;
    }

    if (Array.isArray(value)) {
        const max = list.find((rule) => ruleName(rule) === 'max');
        const min = list.find((rule) => ruleName(rule) === 'min');

        if (max && value.length > Number(ruleParameter(max))) {
            return message('max', ruleParameter(max)) ?? `This may not have more than ${ruleParameter(max)} items.`;
        }

        if (min && value.length < Number(ruleParameter(min))) {
            return message('min', ruleParameter(min)) ?? `Please choose at least ${ruleParameter(min)} items.`;
        }

        return null;
    }

    for (const rule of list) {
        const name = ruleName(rule);
        const parameter = ruleParameter(rule);

        if (SKIPPED.has(name)) continue;

        switch (name) {
            case 'string':
                if (typeof value !== 'string') return message(name) ?? 'This must be text.';
                break;

            case 'integer':
                if (!Number.isInteger(Number(value))) return message(name) ?? 'This must be a whole number.';
                break;

            case 'array':
                return message(name) ?? 'This must be a list.';

            case 'email':
                if (!EMAIL.test(String(value))) return message(name) ?? 'Please enter a valid email address.';
                break;

            case 'max':
                if (String(value).length > Number(parameter)) {
                    return message(name, parameter) ?? `This may not be greater than ${parameter} characters.`;
                }
                break;

            case 'min':
                if (String(value).length < Number(parameter)) {
                    return message(name, parameter) ?? `This must be at least ${parameter} characters.`;
                }
                break;

            case 'regex':
                if (!new RegExp(parameter).test(String(value))) return message(name) ?? 'This format is invalid.';
                break;

            case 'in':
                if (!String(parameter).split(',').includes(String(value))) {
                    return message(name) ?? 'This is not a valid selection.';
                }
                break;

            case 'accepted':
                return message(name) ?? 'This must be accepted.';

            case 'confirmed':
                // Laravel looks for a sibling `{field}_confirmation` key.
                if (String(value) !== String(all[`${field}_confirmation`] ?? '')) {
                    return message(name) ?? 'The confirmation does not match.';
                }
                break;

            case 'date_format':
            case 'mimes':
            case 'image':
                // Enforced by the native control (`<input type="date">`,
                // `accept="image/*"`), so there is nothing left to check.
                break;

            default:
                break;
        }
    }

    return null;
}

/**
 * Builds a form with an `errors` bag matching Laravel's shape.
 *
 * `rules` maps a field to its rule list. `after` runs once the declarative
 * rules pass, which is where the Form Requests' `withValidator()` hooks put
 * their cross-field checks (slot availability, required down payment, …).
 */
export function createForm({ initial = {}, rules = {}, messages = {}, after = null, context = {} } = {}) {
    const values = reactive({ ...initial });
    const errors = reactive({});
    const submitted = reactive({ attempted: false });

    function clearErrors() {
        Object.keys(errors).forEach((key) => delete errors[key]);
    }

    function validate() {
        clearErrors();

        const payload = { ...context, ...values };

        Object.entries(rules).forEach(([field, fieldRules]) => {
            const error = validateField(field, payload[field], fieldRules, payload, messages);

            if (error) errors[field] = error;
        });

        submitted.attempted = true;

        // `after` mirrors `withValidator()`: it only runs on a clean field pass.
        if (after && Object.keys(errors).length === 0) {
            after(errors, values);
        }

        return Object.keys(errors).length === 0;
    }

    function reset(next = initial) {
        Object.keys(values).forEach((key) => delete values[key]);

        Object.assign(values, next);

        clearErrors();
        submitted.attempted = false;
    }

    return {
        values,
        errors,
        submitted,
        validate,
        reset,
        /** `$errors->any()` */
        hasErrors: computed(() => Object.keys(errors).length > 0),
        /** `$errors->all()` */
        all: computed(() => Object.values(errors)),
        /** `$errors->only([...])` */
        only: (fields) => fields.map((field) => errors[field]).filter(Boolean),
    };
}

/** The shared "valid contact number" rule, reused across three forms. */
export const CONTACT_NUMBER_RULES = ['required', 'string', 'max:32', `regex:${PHONE.source}`];

export const CONTACT_NUMBER_MESSAGES = {
    regex: 'Please enter a valid contact number.',
};

export { EMAIL, PHONE };
