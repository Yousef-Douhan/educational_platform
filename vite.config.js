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
                'resources/css/cms/crud-common.css',
                'resources/css/cms/user-profiles.css',
                'resources/js/cms/user-profiles.js',
                'resources/css/cms/roles.css',
                'resources/js/cms/roles.js',
                'resources/css/cms/permissions.css',
                'resources/js/cms/permissions.js',
                'resources/css/cms/categories.css',
                'resources/js/cms/categories.js',
                'resources/css/cms/courses.css',
                'resources/js/cms/courses.js',
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