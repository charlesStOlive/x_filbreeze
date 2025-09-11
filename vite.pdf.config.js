// vite.pdf.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcssPostcss from '@tailwindcss/postcss';
import path from 'path';
import fs from 'fs';

const isDev = process.env.NODE_ENV === 'development';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/pdf/theme.css'],
            refresh: true, // Pas besoin de refresh pour PDF
            hotFile: 'public/pdf.hot',
            buildDirectory: 'pdf',
        }),
    ],
    css: {
        postcss: {
            plugins: [
                tailwindcssPostcss(),
            ],
        },
    },
    build: {
        rollupOptions: {
            output: {
                // Évite la génération de JS inutile pour le PDF
                entryFileNames: 'assets/[name].[hash].js',
                chunkFileNames: 'assets/[name].[hash].js',
                assetFileNames: 'assets/[name].[hash].[ext]'
            }
        }
    },
});
