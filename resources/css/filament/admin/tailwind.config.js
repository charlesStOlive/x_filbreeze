import preset from '../../../../vendor/filament/filament/tailwind.config.preset';

export default {
    presets: [preset],
    content: [
        './app/Filament/**/*.php',
        './resources/views/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
        './vendor/pboivin/filament-peek/resources/**/*.blade.php',
    ],
    theme: {
        extend: {
            colors: {
                'primary-base': '#F87F04', // Couleur principale
                'primary-light': '#FF9F36', // Version plus claire
                'primary-dark': '#C46303', // Version plus foncée
            },
        },
    },
};
