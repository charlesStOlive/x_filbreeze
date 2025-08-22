import Sortable from 'sortablejs';

export default function treeNestableComponent({
    containerKey,
    maxDepth = 10,
}) {
    return {
        containerKey,
        maxDepth,
        sortableInstances: new Map(),

        init() {
            this.initializeTree();
            this.setupEventListeners();
        },

        initializeTree() {
            const containers = document.querySelectorAll(`${this.containerKey} .dd-list`);

            containers.forEach(container => {
                this.initializeSortable(container);
            });
        },

        initializeSortable(container) {
            const sortable = new Sortable(container, {
                group: {
                    name: 'tree-items',
                    pull: true,
                    put: true
                },
                animation: 150,
                fallbackOnBody: true,
                swapThreshold: 0.65,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                handle: '.dd-handle',

                // Validation pour la profondeur maximale
                onMove: (evt) => {
                    const depth = this.getItemDepth(evt.to);
                    return depth < this.maxDepth;
                },

                // Événements de déplacement
                onStart: (evt) => {
                    this.hideAllActions();
                    evt.item.classList.add('dd-dragel');
                },

                onEnd: (evt) => {
                    evt.item.classList.remove('dd-dragel');
                    this.showAllActions();
                    this.updateTreeStructure();
                },

                // Callback quand un élément est ajouté à ce conteneur
                onAdd: (evt) => {
                    this.reinitializeNestedLists(evt.to);
                },

                // Callback quand un élément est retiré de ce conteneur
                onRemove: (evt) => {
                    // Cleanup si nécessaire
                }
            });

            this.sortableInstances.set(container, sortable);
        },

        getItemDepth(element) {
            let depth = 0;
            let current = element;

            while (current && !current.matches(this.containerKey)) {
                if (current.matches('.dd-list')) {
                    depth++;
                }
                current = current.parentElement;
            }

            return depth;
        },

        reinitializeNestedLists(container) {
            // Réinitialise les listes imbriquées qui pourraient avoir été ajoutées
            const nestedLists = container.querySelectorAll('.dd-list');
            nestedLists.forEach(list => {
                if (!this.sortableInstances.has(list)) {
                    this.initializeSortable(list);
                }
            });
        },

        setupEventListeners() {
            const container = document.querySelector(this.containerKey);

            // Gestion des boutons expand/collapse
            container.addEventListener('click', (e) => {
                if (e.target.matches('[data-action="expand"]')) {
                    this.expandItem(e.target);
                } else if (e.target.matches('[data-action="collapse"]')) {
                    this.collapseItem(e.target);
                }
            });
        },

        expandItem(button) {
            const listItem = button.closest('li');
            if (listItem) {
                button.classList.add('hidden');
                const collapseBtn = listItem.querySelector('[data-action="collapse"]');
                if (collapseBtn) {
                    collapseBtn.classList.remove('hidden');
                }

                const subList = listItem.querySelector(':scope > .dd-list');
                if (subList) {
                    subList.classList.remove('hidden');
                    subList.style.display = 'block';

                    const items = subList.querySelectorAll(':scope > .dd-item');
                    items.forEach(item => {
                        item.classList.remove('dd-collapsed', 'hidden');
                    });
                }
            }
        },

        collapseItem(button) {
            const listItem = button.closest('li');
            if (listItem) {
                button.classList.add('hidden');
                const expandBtn = listItem.querySelector('[data-action="expand"]');
                if (expandBtn) {
                    expandBtn.classList.remove('hidden');
                }

                const subList = listItem.querySelector(':scope > .dd-list');
                if (subList) {
                    subList.classList.add('hidden');
                    subList.style.display = 'none';

                    const items = subList.querySelectorAll(':scope > .dd-item');
                    items.forEach(item => {
                        item.classList.add('dd-collapsed', 'hidden');
                    });
                }
            }
        },

        hideAllActions() {
            const actions = document.querySelectorAll(`${this.containerKey} .dd-item-actions`);
            actions.forEach(action => {
                action.style.opacity = '0.3';
            });
        },

        showAllActions() {
            const actions = document.querySelectorAll(`${this.containerKey} .dd-item-actions`);
            actions.forEach(action => {
                action.style.opacity = '1';
            });
        },

        updateTreeStructure() {
            // Cette fonction sera appelée après chaque déplacement
            // Vous pouvez ajouter ici la logique pour envoyer les changements au serveur
            const treeData = this.serialize();

            // Dispatch un événement personnalisé avec les nouvelles données
            const event = new CustomEvent('tree-updated', {
                detail: { treeData }
            });
            document.querySelector(this.containerKey).dispatchEvent(event);

            // Si vous utilisez Livewire
            if (window.Livewire) {
                this.$wire.updateTreeOrder(treeData);
            }
        },

        serialize() {
            const container = document.querySelector(this.containerKey);
            return this.serializeList(container.querySelector('.dd-list'));
        },

        serializeList(list) {
            if (!list) return [];

            const items = [];
            const directChildren = list.querySelectorAll(':scope > .dd-item');

            directChildren.forEach(item => {
                const itemData = {
                    id: item.dataset.id || item.getAttribute('data-id'),
                };

                // Cherche une sous-liste
                const subList = item.querySelector(':scope > .dd-list');
                if (subList) {
                    itemData.children = this.serializeList(subList);
                }

                items.push(itemData);
            });

            return items;
        },

        // Méthode pour détruire toutes les instances Sortable
        destroy() {
            this.sortableInstances.forEach(instance => {
                instance.destroy();
            });
            this.sortableInstances.clear();
        },

        // Méthode pour réinitialiser complètement l'arbre
        refresh() {
            this.destroy();
            this.init();
        }
    };
}
