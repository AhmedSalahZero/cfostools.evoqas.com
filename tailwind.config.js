import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    // 'class' means dark mode is controlled by adding/removing
    // the 'dark' class on the <html> tag — not by OS setting
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                mp: {
                    page: 'rgb(var(--mp-page) / <alpha-value>)',
                    card: 'rgb(var(--mp-card) / <alpha-value>)',
                    'card-hover': 'rgb(var(--mp-card-hover) / <alpha-value>)',
                    input: 'rgb(var(--mp-input) / <alpha-value>)',
                    border: 'rgb(var(--mp-border) / <alpha-value>)',
                    teal: 'rgb(var(--mp-teal) / <alpha-value>)',
                    'teal-dark': 'rgb(var(--mp-teal-dark) / <alpha-value>)',
                    'teal-subtle': 'rgb(var(--mp-teal-subtle) / <alpha-value>)',
                    gold: 'rgb(var(--mp-gold) / <alpha-value>)',
                    'gold-dark': 'rgb(var(--mp-gold-dark) / <alpha-value>)',
                    text: 'rgb(var(--mp-text) / <alpha-value>)',
                    'text-secondary': 'rgb(var(--mp-text-secondary) / <alpha-value>)',
                    muted: 'rgb(var(--mp-muted) / <alpha-value>)',
                    success: 'rgb(var(--mp-success) / <alpha-value>)',
                    danger: 'rgb(var(--mp-danger) / <alpha-value>)',
                    warning: 'rgb(var(--mp-warning) / <alpha-value>)',
                },
            },
        },
    },

    plugins: [forms],
};