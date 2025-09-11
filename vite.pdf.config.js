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
            refresh: false, // Pas besoin de refresh pour PDF
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
    ...(isDev && {
        server: {
            host: '0.0.0.0',
            port: 5201, // Port différent pour éviter les conflits
            strictPort: false,
            cors: true,
            origin: 'https://x_filbreeze.test:5201',
            allowedHosts: ['x_filbreeze.test'],
            hmr: {
                host: 'x_filbreeze.test',
                port: 5201,
                protocol: 'wss',
                // Désactive le rechargement automatique de page pour PDF
                overlay: false,
            },
            // Mode watch uniquement pour CSS, pas de HMR complet
            watch: {
                usePolling: true,
                interval: 300,
            },
            https: {
                key: fs.readFileSync(path.resolve(__dirname, 'C:/laragon/etc/ssl/laragon.key')),
                cert: fs.readFileSync(path.resolve(__dirname, 'C:/laragon/etc/ssl/laragon.crt')),
            },
        },
    }),
});
