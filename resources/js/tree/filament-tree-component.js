// Filament Tree Component
// Composant principal qui intègre jQuery Nestable avec Filament
import $ from 'jquery';

window.filamentTreeComponent = function (config) {
    return {
        containerKey: config.containerKey || '.dd',
        maxDepth: config.maxDepth || 5,

        init() {
            this.initializeNestable();
            this.setupEventListeners();
        },

        initializeNestable() {
            const container = this.$el.querySelector(this.containerKey);
            if (!container) {
                console.error('Tree container not found:', this.containerKey);
                return;
            }

            const $container = $(container);

            // Initialiser Nestable
            $container.nestable({
                group: 1,
                maxDepth: this.maxDepth,
                threshold: 20,
                handleClass: 'dd-handle',
                rootClass: 'dd',
                listClass: 'dd-list',
                itemClass: 'dd-item',
                dragClass: 'dd-dragel',
                collapsedClass: 'dd-collapsed',
                placeClass: 'dd-placeholder',
                noDragClass: 'dd-nodrag',
                emptyClass: 'dd-empty',
                expandBtnHTML: '<button data-action="expand" type="button" class="dd-expand">Expand</button>',
                collapseBtnHTML: '<button data-action="collapse" type="button" class="dd-collapse">Collapse</button>'
            });

            // Initialiser les actions personnalisées
            $container.nestableActions({
                expandAll: '[data-action="expand-all"]',
                collapseAll: '[data-action="collapse-all"]',
                save: '[data-action="save"]'
            });

            // Initialiser les personnalisations
            $container.nestableCustom();

            // Écouter les changements
            $container.on('change', () => {
                this.onTreeChange();
            });

            // Écouter l'événement de sauvegarde
            $container.on('nestable:save', (event, data) => {
                this.onSave(data);
            });
        },

        setupEventListeners() {
            // Gérer les clics sur les boutons d'action
            this.$el.addEventListener('click', (e) => {
                const action = e.target.getAttribute('data-action');

                switch (action) {
                    case 'expand-all':
                        this.expandAll();
                        break;
                    case 'collapse-all':
                        this.collapseAll();
                        break;
                    case 'save':
                        this.save();
                        break;
                }
            });
        },

        expandAll() {
            const container = this.$el.querySelector(this.containerKey);
            if (container) {
                $(container).nestable('expandAll');
            }
        },

        collapseAll() {
            const container = this.$el.querySelector(this.containerKey);
            if (container) {
                $(container).nestable('collapseAll');
            }
        },

        save() {
            const container = this.$el.querySelector(this.containerKey);
            if (container) {
                const data = $(container).nestable('serialize');
                this.onSave(data);
            }
        },

        onTreeChange() {
            // Appelé quand l'arbre change (drag & drop)
            console.log('Tree structure changed');
        },

        onSave(data) {
            console.log('Saving tree data:', data);

            // Émettre un événement personnalisé
            const event = new CustomEvent('tree-updated', {
                detail: { treeData: data }
            });
            this.$el.dispatchEvent(event);

            // Appeler Livewire si disponible
            if (window.Livewire && this.$wire) {
                this.$wire.call('updateTreeOrder', data);
            }
        },

        serialize() {
            const container = this.$el.querySelector(this.containerKey);
            if (container) {
                return $(container).nestable('serialize');
            }
            return [];
        }
    };
};

// Export pour le module
export default window.filamentTreeComponent;
