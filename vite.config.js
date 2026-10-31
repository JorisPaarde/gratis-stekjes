import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/aanbod.css',
                'resources/css/aanbod-fotos.css',
                'resources/css/berichten.css',
                'resources/css/partners.css',
                'resources/css/deelplekken.css',
                'resources/css/nu-te-stekken.css',
                'resources/css/admin.css',
                'resources/css/stektips.css',
                'resources/css/gratis-planten.css',
                'resources/js/app.js',
                'resources/js/zoeken.js',
                'resources/js/deelplekken.js',
                'resources/js/stad-kaart.js',
                'resources/js/plantsoort-autocomplete.js',
                'resources/css/plantsoort-autocomplete.css',
            ],
            refresh: true,
        }),
    ],
});
