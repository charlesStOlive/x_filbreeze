// tailwind.config.js - Configuration globale pour app.css
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        // Exclure les vues Filament qui ont leur propre config
        '!./resources/views/filament/**/*.blade.php',
    ],
    theme: {
        extend: {
            colors: {
                'primary-base': '#F87F04',
                'primary-light': '#FF9F36',
                'primary-dark': '#C46303',
            },
        },
    },
    plugins: [],
};
