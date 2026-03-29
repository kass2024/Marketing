import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Parrot Canada Visa Consultant — logo palette (class names kept for Blade compatibility)
                xander: {
                    navy: '#4B7930',
                    secondary: '#3D6CBA',
                    accent: '#355A23',
                    gold: '#E31F26',
                },
            },
        },
    },

    plugins: [forms],
};
