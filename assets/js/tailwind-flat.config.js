/**
 * assets/js/tailwind-flat.config.js
 * Flat / Metro UI Design Tokens for Tailwind CSS Play CDN
 * AURA HRM System
 */
const FLAT_RADIUS = '2px';
const NO_SHADOW = '0 0 #0000'; // Dùng '0 0 #0000' thay 'none' để các tiện ích outline/ring của Tailwind không bị vỡ

window.tailwind = window.tailwind || {};
window.tailwind.config = {
    darkMode: 'class',
    theme: {
        borderRadius: {
            none: '0px',
            xs: '1px',
            sm: FLAT_RADIUS,
            DEFAULT: FLAT_RADIUS,
            md: FLAT_RADIUS,
            lg: FLAT_RADIUS,
            xl: FLAT_RADIUS,
            '2xl': FLAT_RADIUS,
            '3xl': FLAT_RADIUS,
            full: FLAT_RADIUS, // Phẳng thuần (Metro): Avatar, badges và pills đều vuông góc 2px
            circle: '9999px',   // Dành riêng cho spinner, live status dot
        },
        boxShadow: {
            none: NO_SHADOW,
            '2xs': NO_SHADOW,
            xs: NO_SHADOW,
            sm: NO_SHADOW,
            DEFAULT: NO_SHADOW,
            md: NO_SHADOW,
            lg: NO_SHADOW,
            xl: NO_SHADOW,
            '2xl': NO_SHADOW,
            inner: NO_SHADOW,
        },
        extend: {
            fontFamily: {
                sans: ['Inter', 'Plus Jakarta Sans', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', 'sans-serif'],
                mono: ['JetBrains Mono', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'Monaco', 'Consolas', 'monospace'],
            },
            colors: {
                brand: {
                    50: '#eef2ff',
                    100: '#e0e7ff',
                    500: '#6366f1',
                    600: '#4f46e5',
                    700: '#4338ca',
                }
            }
        }
    }
};
