// resources/css/pdf/tailwind.pdf.config.js
import preset from '../../../vendor/filament/filament/tailwind.config.preset'

export default {
    presets: [preset],
    content: [
        './resources/views/pdf/**/*.blade.php',
        // Ajoutez d'autres chemins si nécessaire
    ],
    // Ajoutez des extensions spécifiques pour les PDF si besoin
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
