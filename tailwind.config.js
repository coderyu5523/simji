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
            colors: {
                deepgreen: '#1F4D3F', teal: '#2E7D6B', navy: '#1E2A44',
                cream: '#F7F3EA', mint: '#BFE3D4',
                signal: { green: '#3FAE5A', yellow: '#F2B705', red: '#E0584E' },
            },
            fontFamily: { sans: ['Pretendard', 'sans-serif'] },
        },
    },

    plugins: [forms],
};
