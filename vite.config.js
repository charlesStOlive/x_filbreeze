import fs from 'fs';
import path from 'path';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

const isDev = process.env.NODE_ENV === 'development';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/app.js',
                'resources/css/filament/admin/theme.css',
            ],
            refresh: true,
        }),
    ],
    ...(isDev && {
        server: {
            host: '0.0.0.0',
            port: 5200,
            strictPort: true,
            cors: true,
            origin: 'https://x_filbreeze.test:5200',
            allowedHosts: ['x_filbreeze.test'],
            hmr: {
                host: 'x_filbreeze.test',
                port: 5200,
                protocol: 'wss',
            },
            https: {
                key: fs.readFileSync(path.resolve(__dirname, 'C:/laragon/etc/ssl/laragon.key')),
                cert: fs.readFileSync(path.resolve(__dirname, 'C:/laragon/etc/ssl/laragon.crt')),
            },
        },
    }),
});
