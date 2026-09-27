import defaultTheme from 'tailwindcss/defaultTheme';

/**
 * Balai ti Arjud design tokens.
 *
 * Colours are declared as CSS custom properties in src/assets/app.css. Adjust
 * a token in ONE place (app.css `:root`) and every component follows.
 */
const withOpacity = (variable) => ({ opacityValue }) => {
    if (opacityValue === undefined) return `rgb(var(${variable}))`;

    return `rgb(var(${variable}) / ${opacityValue})`;
};

export default {
    content: ['./index.html', './src/**/*.{vue,js}'],
    theme: {
        extend: {
            colors: {
                primary: {
                    DEFAULT: withOpacity('--color-primary'),
                    dark: withOpacity('--color-primary-dark'),
                    light: withOpacity('--color-primary-light'),
                },
                gold: {
                    DEFAULT: withOpacity('--color-gold'),
                    mid: withOpacity('--color-gold-mid'),
                    light: withOpacity('--color-gold-light'),
                    dark: withOpacity('--color-gold-dark'),
                },
                linen: withOpacity('--color-linen'),
                cream: withOpacity('--color-cream'),
                soft: withOpacity('--color-soft'),
                line: withOpacity('--color-line'),
                sienna: withOpacity('--color-sienna'),
                blush: withOpacity('--color-blush'),
                ink: {
                    DEFAULT: withOpacity('--color-text-dark'),
                    muted: withOpacity('--color-text-muted'),
                },
                status: {
                    pending: withOpacity('--color-status-pending'),
                    'pending-bg': withOpacity('--color-status-pending-bg'),
                    confirmed: withOpacity('--color-status-confirmed'),
                    'confirmed-bg': withOpacity('--color-status-confirmed-bg'),
                    progress: withOpacity('--color-status-progress'),
                    'progress-bg': withOpacity('--color-status-progress-bg'),
                    completed: withOpacity('--color-status-completed'),
                    'completed-bg': withOpacity('--color-status-completed-bg'),
                    cancelled: withOpacity('--color-status-cancelled'),
                    'cancelled-bg': withOpacity('--color-status-cancelled-bg'),
                    'low-stock': withOpacity('--color-status-low-stock'),
                    'low-stock-bg': withOpacity('--color-status-low-stock-bg'),
                    'best-seller': withOpacity('--color-status-best-seller'),
                    'best-seller-bg': withOpacity('--color-status-best-seller-bg'),
                    'sold-out': withOpacity('--color-status-sold-out'),
                    'sold-out-bg': withOpacity('--color-status-sold-out-bg'),
                },
            },
            fontFamily: {
                display: ['"Playfair Display"', 'Georgia', 'serif'],
                heading: ['"Cormorant Garamond"', 'Georgia', 'serif'],
                script: ['Parisienne', '"Brush Script MT"', 'cursive'],
                sans: ['Poppins', 'Inter', ...defaultTheme.fontFamily.sans],
            },
            borderRadius: {
                card: '1rem',
                pill: '999px',
            },
            boxShadow: {
                card: '0 1px 2px rgba(74,42,32,0.04), 0 8px 24px -12px rgba(122,36,27,0.18)',
                'card-hover': '0 2px 4px rgba(74,42,32,0.06), 0 16px 32px -12px rgba(122,36,27,0.28)',
                panel: '0 1px 3px rgba(74,42,32,0.05), 0 12px 28px -16px rgba(74,42,32,0.25)',
            },
            backgroundImage: {
                'gold-sheen': 'linear-gradient(135deg, rgb(var(--color-gold)) 0%, rgb(var(--color-gold-light)) 100%)',
            },
            keyframes: {
                'fade-in-up': {
                    '0%': { opacity: '0', transform: 'translateY(8px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                'bell-ring': {
                    '0%, 100%': { transform: 'rotate(0deg)' },
                    '15%': { transform: 'rotate(12deg)' },
                    '30%': { transform: 'rotate(-10deg)' },
                    '45%': { transform: 'rotate(6deg)' },
                    '60%': { transform: 'rotate(-3deg)' },
                },
            },
            animation: {
                'fade-in-up': 'fade-in-up 0.35s ease-out both',
                'bell-ring': 'bell-ring 1.1s ease-in-out',
            },
        },
    },
    plugins: [],
};
