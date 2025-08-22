// Point d'entrée principal pour le composant Tree avec jQuery
import $ from 'jquery';

// Inclure jQuery Nestable
import './jquery.nestable.js';
import './custom.nestable.js';
import './filament-tree-component.js';

// S'assurer que jQuery est disponible globalement
window.$ = window.jQuery = $;

// Exporter le composant pour le bundle
export default window.filamentTreeComponent;
