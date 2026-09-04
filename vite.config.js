import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    build: {
        // Emit classic max-width media queries instead of modern range syntax
        // (width<=767px). Range syntax is unsupported on iOS < 16.4 — on older
        // phones (e.g. iPhone 8 on iOS 12-15) the whole mobile stylesheet
        // silently fails to apply. safari12 target forces max-width lowering;
        // visually identical on modern browsers, works everywhere.
        cssTarget: 'safari12',
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/admin.css',
                'resources/css/mobile-responsive.css',
                'resources/css/landing.css',
                'resources/css/login.css',
                'resources/css/maint-form.css',
                'resources/css/ict-form.css',
                'resources/css/cmms-official.css',
                'resources/js/inventory.js',
                'resources/js/login.js',
                'resources/js/qr-scanner.js',
                'resources/css/maintenance-calendar.css',
                'resources/js/maintenance-calendar.js',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});