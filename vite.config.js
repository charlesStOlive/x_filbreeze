import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';


export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/filament/admin/filament.css',
                //
                'resources/css/front/front.css',
                'resources/js/front/front.js',
                //
                'resources/css/pdf/pdf.css'
            ],
            refresh: true,
        }),
    ],
});
