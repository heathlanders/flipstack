import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.js',
    ],

    darkMode: 'class',

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Brand indigo primary
                primary: {
                    50: '#EEF2FF',
                    100: '#E0E7FF',
                    200: '#C7D2FE',
                    300: '#A5B4FC',
                    400: '#818CF8',
                    500: '#6875F5',
                    600: '#5850EC',
                    700: '#4338CA',
                    800: '#3730A3',
                    900: '#312E81',
                    950: '#1E1B4B',
                },
                // Department colors
                department: {
                    buying: '#10b981',
                    listing: '#3b82f6',
                    fulfillment: '#f59e0b',
                    support: '#ef4444',
                    admin: '#8b5cf6',
                }
            },
            animation: {
                'fade-in': 'fadeIn 0.5s ease-in-out',
                'slide-up': 'slideUp 0.3s ease-out',
                'bounce-in': 'bounceIn 0.6s ease-out',
            },
            keyframes: {
                fadeIn: {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                slideUp: {
                    '0%': { transform: 'translateY(10px)', opacity: '0' },
                    '100%': { transform: 'translateY(0)', opacity: '1' },
                },
                bounceIn: {
                    '0%, 20%, 40%, 60%, 80%': { transform: 'scale(0.9)' },
                    '100%': { transform: 'scale(1)' },
                },
            },
        },
    },

    plugins: [
        forms,
        typography,
        // line-clamp is now built-in to Tailwind CSS v3.3+
    ],

    // Enable all Tailwind features
    variants: {
        extend: {
            backgroundColor: ['active', 'group-hover'],
            textColor: ['active', 'group-hover'],
            borderColor: ['active', 'group-hover'],
            scale: ['active', 'group-hover'],
            opacity: ['disabled'],
        },
    },

    // Safelist dynamic classes
    safelist: [
        // Role colors
        'bg-purple-100', 'text-purple-800', 'dark:bg-purple-900/30', 'dark:text-purple-300',
        'bg-red-100', 'text-red-800', 'dark:bg-red-900/30', 'dark:text-red-300',
        'bg-blue-100', 'text-blue-800', 'dark:bg-blue-900/30', 'dark:text-blue-300',
        'bg-emerald-100', 'text-emerald-800', 'dark:bg-emerald-900/30', 'dark:text-emerald-300',
        'bg-orange-100', 'text-orange-800', 'dark:bg-orange-900/30', 'dark:text-orange-300',
        'bg-pink-100', 'text-pink-800', 'dark:bg-pink-900/30', 'dark:text-pink-300',
        'bg-gray-100', 'text-gray-800', 'dark:bg-gray-900/30', 'dark:text-gray-300',
        'bg-yellow-100', 'text-yellow-800', 'dark:bg-yellow-900/30', 'dark:text-yellow-300',
        
        // Department colors
        'bg-buying-100', 'text-buying-800', 'bg-buying-500',
        'bg-listing-100', 'text-listing-800', 'bg-listing-500',
        'bg-fulfillment-100', 'text-fulfillment-800', 'bg-fulfillment-500',
        'bg-support-100', 'text-support-800', 'bg-support-500',
        'bg-admin-100', 'text-admin-800', 'bg-admin-500',

        // Dynamic patterns
        {
            pattern: /bg-(red|green|blue|yellow|purple|pink|gray|emerald|orange)-(50|100|200|300|400|500|600|700|800|900)/,
        },
        {
            pattern: /text-(red|green|blue|yellow|purple|pink|gray|emerald|orange)-(50|100|200|300|400|500|600|700|800|900)/,
        },
        {
            pattern: /border-(red|green|blue|yellow|purple|pink|gray|emerald|orange)-(50|100|200|300|400|500|600|700|800|900)/,
        },
    ],
};