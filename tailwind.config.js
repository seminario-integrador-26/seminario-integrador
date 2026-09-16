import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Sistema visual "Atalaya" (consola táctica SOC). Paleta y criterios en
 * docs/diseno-atalaya.md: superficies azul marino obsidiana, acento cian para
 * telemetría y semáforo por eje de clasificación (RN-02) —
 * naranja = Prevención, ámbar = Convivencia Urbana, carmesí = Seguridad Pública.
 */

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
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
                mono: [
                    'JetBrains Mono',
                    'ui-monospace',
                    'SFMono-Regular',
                    ...defaultTheme.fontFamily.mono,
                ],
            },
            colors: {
                atalaya: {
                    // Superficies
                    canvas: '#031427',
                    surface: '#0B1C30',
                    elevated: '#0F2847',
                    border: '#1E3A5F',
                    // Acentos de telemetría
                    cyan: '#00D2FF',
                    'blue-ops': '#0284C7',
                    sky: '#38BDF8',
                    'blue-deep': '#0369A1',
                    // Semáforo por eje de clasificación
                    orange: '#F97316', // Prevención
                    amber: '#F59E0B', // Convivencia Urbana
                    crimson: '#EF4444', // Seguridad Pública
                    // Texto
                    'text-primary': '#FFFFFF',
                    'text-muted': '#94A3B8',
                    'text-dim': '#475569',
                },
            },
        },
    },

    plugins: [forms],
};
