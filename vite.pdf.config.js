// vite.pdf.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from 'tailwindcss';
import autoprefixer from 'autoprefixer';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/pdf/theme.css'],
            refresh: true,
            hotFile: 'public/pdf.hot',
            buildDirectory: 'pdf',
        }),
    ],
    css: {
        postcss: {
            plugins: [
                tailwindcss({
                    config: path.resolve(__dirname, 'resources/css/pdf/tailwind.pdf.config.js'),
                }),
                autoprefixer,
            ],
        },
    },
});
