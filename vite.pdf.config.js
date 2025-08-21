// vite.pdf.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from 'tailwindcss';
import autoprefixer from 'autoprefixer';
import path from 'path';
import fs from 'fs';

const isDev = process.env.NODE_ENV === 'development';

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
            },
            https: {
                key: fs.readFileSync(path.resolve(__dirname, 'C:/laragon/etc/ssl/laragon.key')),
                cert: fs.readFileSync(path.resolve(__dirname, 'C:/laragon/etc/ssl/laragon.crt')),
            },
        },
    }),
});
