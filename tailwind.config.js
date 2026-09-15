import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

// Colores semánticos: cada token es una variable CSS (canales RGB) definida por
// tema en resources/css/app.css -> "claro" y "marino" (azul marino oscuro).
// Radios: controles (botones, campos, chips) rounded-md; menús rounded-lg;
// paneles rounded-xl; badges rounded.
const token = (nombre) => `rgb(var(--${nombre}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"IBM Plex Sans"', ...defaultTheme.fontFamily.sans],
                mono: ['"IBM Plex Mono"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                canvas: token('canvas'),
                surface: {
                    DEFAULT: token('surface'),
                    2: token('surface-2'),
                },
                line: {
                    DEFAULT: token('line'),
                    strong: token('line-strong'),
                },
                ink: {
                    DEFAULT: token('ink'),
                    muted: token('ink-muted'),
                    subtle: token('ink-subtle'),
                },
                brand: {
                    DEFAULT: token('brand'),
                    hover: token('brand-hover'),
                    ink: token('brand-ink'),
                    deep: token('brand-deep'),
                    'deep-ink': token('brand-deep-ink'),
                    'deep-muted': token('brand-deep-muted'),
                },
                accent: {
                    DEFAULT: token('accent'),
                    soft: token('accent-soft'),
                },
                success: {
                    DEFAULT: token('success'),
                    soft: token('success-soft'),
                },
                warning: {
                    DEFAULT: token('warning'),
                    soft: token('warning-soft'),
                },
                danger: {
                    DEFAULT: token('danger'),
                    soft: token('danger-soft'),
                    solid: token('danger-solid'),
                    'solid-ink': token('danger-solid-ink'),
                },
            },
            boxShadow: {
                panel: '0 1px 2px rgb(var(--shadow) / 0.05), 0 4px 12px -4px rgb(var(--shadow) / 0.08)',
            },
        },
    },

    plugins: [forms],
};
