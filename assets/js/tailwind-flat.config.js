/**
 * assets/js/tailwind-flat.config.js
 * Flat / Metro UI Design Tokens for Tailwind CSS Play CDN
 * WinCommerce People HRM System (WinMart / Masan Group)
 */
const FLAT_RADIUS = '2px';
const NO_SHADOW = '0 0 #0000'; // Dùng '0 0 #0000' thay 'none' để các tiện ích outline/ring của Tailwind không bị vỡ

// Bảng màu chuẩn WinCommerce / WinMart Red (#DA251C)
const WIN_RED = {
    50: '#fff1f0',
    100: '#ffe0de',
    200: '#ffc6c2',
    300: '#ff9d96',
    400: '#f8645a',
    500: '#ee3a2e',
    600: '#da251c', // Primary WinMart Red
    700: '#b81d15',
    800: '#981b15',
    900: '#7e1c17',
    950: '#450a07',
};

// Bảng màu phụ WinMart Wine / Ruby cho các gradient phong cách Masan
const WIN_WINE = {
    50: '#fdf2f4',
    100: '#fce7eb',
    200: '#f9d0d9',
    300: '#f4a9ba',
    400: '#ec7593',
    500: '#df4a70',
    600: '#c92a57',
    700: '#a91e47',
    800: '#8d1c40',
    900: '#781b3b',
    950: '#430a1c',
};

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
                sans: ['"Be Vietnam Pro"', 'Inter', 'Plus Jakarta Sans', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', 'sans-serif'],
                mono: ['JetBrains Mono', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'Monaco', 'Consolas', 'monospace'],
            },
            colors: {
                // Ánh xạ toàn bộ class indigo-* sang WinMart Red để 1,500+ class giao diện đổi màu đồng bộ
                indigo: WIN_RED,
                // Ánh xạ violet-* sang Wine Red cho các gradient
                violet: WIN_WINE,
                brand: WIN_RED,
                accent: {
                    400: '#ffd24d',
                    500: '#ffc709', // WinMart Golden Yellow
                    600: '#e5b000',
                }
            }
        }
    }
};
