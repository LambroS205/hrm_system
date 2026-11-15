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
            xs: '2px',
            sm: '4px',
            DEFAULT: '6px',
            md: '6px',
            lg: '8px',
            xl: '10px',
            '2xl': '12px',
            '3xl': '14px',
            full: '9999px',
            circle: '9999px',
        },
        boxShadow: {
            none: 'none',
            '2xs': '0 1px 2px 0 rgb(15 23 42 / 0.04)',
            xs: '0 1px 2px 0 rgb(15 23 42 / 0.05)',
            sm: '0 1px 3px 0 rgb(15 23 42 / 0.08), 0 1px 2px -1px rgb(15 23 42 / 0.06)',
            DEFAULT: '0 1px 3px 0 rgb(15 23 42 / 0.08), 0 1px 2px -1px rgb(15 23 42 / 0.06)',
            md: '0 4px 6px -1px rgb(15 23 42 / 0.08), 0 2px 4px -2px rgb(15 23 42 / 0.06)',
            lg: '0 10px 15px -3px rgb(15 23 42 / 0.08), 0 4px 6px -4px rgb(15 23 42 / 0.05)',
            xl: '0 20px 25px -5px rgb(15 23 42 / 0.10), 0 8px 10px -6px rgb(15 23 42 / 0.06)',
            '2xl': '0 25px 50px -12px rgb(15 23 42 / 0.22)',
            inner: 'inset 0 2px 4px 0 rgb(15 23 42 / 0.05)',
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
