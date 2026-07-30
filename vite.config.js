import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
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
