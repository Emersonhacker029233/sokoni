import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// No web-font CDN plugin (the previous scaffold's `bunny(...)` font
// loader) — this app bundles Plus Jakarta Sans locally
// (resources/fonts/, referenced via @font-face in app.css) for the same
// reason the Flutter app dropped google_fonts: a runtime CDN fetch is the
// wrong tradeoff for a site whose whole premise is working on patchy 3G.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
