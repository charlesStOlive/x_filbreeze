import Sortable from 'sortablejs';

export default function filamentTreeComponent({
    containerKey,
    maxDepth,
}) {
    return {
        containerKey,
        maxDepth,
        nestedTreeElement: null,
        sortableInstances: [],
        hasUnsavedChanges: false,

        init: function () {
            console.log('Initializing Tree Component with SortableJS');

            this.nestedTreeElement = document.querySelector(this.containerKey);
            if (!this.nestedTreeElement) {
                console.error(`Tree container not found: ${this.containerKey}`);
                return;
            }

            // Initialize SortableJS
            this.initializeSortable();

            // Add reference to this component on the container
            this.nestedTreeElement._treeComponent = this;

            // Add indentation buttons
            this.addIndentationButtons();

            // Add click event listeners for indent/outdent buttons
            this.nestedTreeElement.addEventListener('click', (e) => {
                const indentBtn = e.target.closest('.indent-btn');
                const outdentBtn = e.target.closest('.outdent-btn');

                if (indentBtn) {
                    const item = indentBtn.closest('.dd-item');
                    this.indentItemManually(item);
                } else if (outdentBtn) {
                    const item = outdentBtn.closest('.dd-item');
                    this.outdentItemManually(item);
                }
            });
        },

        initializeSortable: function () {
            console.log('Initializing SortableJS...');

            // Destroy existing instances
            this.destroySortable();

            // Find all lists in the tree
            const lists = this.nestedTreeElement.querySelectorAll('.dd-list');

            lists.forEach((list, index) => {
                try {
                    const sortableInstance = new Sortable(list, {
                        group: 'nested',
                        animation: 150,
                        fallbackOnBody: true,
                        swapThreshold: 0.65,
                        handle: '.dd-handle',
                        filter: '.indent-controls',

                        onMove: (evt) => {
                            const depth = this.calculateDepth(evt.to);
                            if (depth > this.maxDepth) {
                                return false;
                            }
                            return true;
                        },

                        onStart: (evt) => {
                            console.log('Drag started');
                        },

                        onEnd: (evt) => {
                            console.log('Drag ended');
                            if (evt.oldIndex !== evt.newIndex || evt.from !== evt.to) {
                                this.hasUnsavedChanges = true;
                                this.updateButtonStates();
                            }
                        }
                    });

                    this.sortableInstances.push(sortableInstance);
                    console.log(`Sortable instance ${index} created successfully`);

                } catch (error) {
                    console.error(`Error creating Sortable instance ${index}:`, error);
                }
            });
        },

        destroySortable: function () {
            this.sortableInstances.forEach(instance => {
                if (instance && typeof instance.destroy === 'function') {
                    instance.destroy();
                }
            });
            this.sortableInstances = [];
        },

        calculateDepth: function (element) {
            let depth = 0;
            let current = element;

            while (current && current !== this.nestedTreeElement) {
                if (current.classList && current.classList.contains('dd-list')) {
                    depth++;
                }
                current = current.parentElement;
            }

            return depth;
        },

        addIndentationButtons: function () {
            const items = this.nestedTreeElement.querySelectorAll('.dd-item');

            items.forEach(item => {
                // Skip if buttons already exist
                if (item.querySelector('.indent-controls')) {
                    return;
                }

                const handle = item.querySelector('.dd-handle');
                if (!handle) return;

                // Create button container
                const controls = document.createElement('div');
                controls.className = 'indent-controls';

                // Create indent button
                const indentBtn = document.createElement('button');
                indentBtn.className = 'indent-btn';
                indentBtn.type = 'button';
                indentBtn.title = 'Faire enfant du précédent';
                indentBtn.innerHTML = '→';

                // Create outdent button
                const outdentBtn = document.createElement('button');
                outdentBtn.className = 'outdent-btn';
                outdentBtn.type = 'button';
                outdentBtn.title = 'Remonter d\'un niveau';
                outdentBtn.innerHTML = '←';

                controls.appendChild(indentBtn);
                controls.appendChild(outdentBtn);

                // Add to handle
                handle.appendChild(controls);
            });

            // Update button states
            this.updateButtonStates();
        },

        updateButtonStates: function () {
            const items = this.nestedTreeElement.querySelectorAll('.dd-item');

            items.forEach(item => {
                const indentBtn = item.querySelector('.indent-btn');
                const outdentBtn = item.querySelector('.outdent-btn');

                if (!indentBtn || !outdentBtn) return;

                // Check if can indent (has previous sibling)
                const prevSibling = item.previousElementSibling;
                const canIndent = prevSibling && prevSibling.classList.contains('dd-item');

                indentBtn.disabled = !canIndent;
                indentBtn.style.opacity = canIndent ? '1' : '0.5';
                indentBtn.style.cursor = canIndent ? 'pointer' : 'not-allowed';

                // Check if can outdent (not at root level)
                const currentList = item.parentElement;
                const parentItem = currentList.closest('.dd-item');
                const canOutdent = !!parentItem;

                outdentBtn.disabled = !canOutdent;
                outdentBtn.style.opacity = canOutdent ? '1' : '0.5';
                outdentBtn.style.cursor = canOutdent ? 'pointer' : 'not-allowed';
            });
        },

        indentItemManually: function (item) {
            const prevSibling = item.previousElementSibling;

            if (!prevSibling || !prevSibling.classList.contains('dd-item')) {
                console.log('Cannot indent: no previous sibling');
                return;
            }

            this.indentItem(item, prevSibling);
        },

        outdentItemManually: function (item) {
            const currentList = item.parentElement;
            const parentItem = currentList.closest('.dd-item');

            if (!parentItem) {
                console.log('Item is already at root level');
                return;
            }

            this.outdentItem(item);
        },

        indentItem: function (item, parentItem) {
            console.log('Indenting item:', item, 'under parent:', parentItem);

            // Create or find child list of parent
            let childList = parentItem.querySelector(':scope > .dd-list');
            if (!childList) {
                childList = document.createElement('ol');
                childList.className = 'dd-list';
                parentItem.appendChild(childList);
            }

            // Add item to end of child list
            childList.appendChild(item);

            // Add Sortable to new list if needed
            this.ensureSortableOnList(childList);

            // Mark changes
            this.hasUnsavedChanges = true;
            this.updateButtonStates();
        },

        outdentItem: function (item) {
            console.log('Outdenting item:', item);

            const currentList = item.parentElement;
            const parentItem = currentList.closest('.dd-item');

            if (!parentItem) {
                console.log('Item is already at root level');
                return;
            }

            const grandParentList = parentItem.parentElement;
            const parentIndex = Array.from(grandParentList.children).indexOf(parentItem);

            // Insert item right after its old parent
            const insertPosition = parentIndex + 1;
            if (insertPosition < grandParentList.children.length) {
                grandParentList.insertBefore(item, grandParentList.children[insertPosition]);
            } else {
                grandParentList.appendChild(item);
            }

            // Clean up empty list if needed
            if (currentList.children.length === 0) {
                currentList.remove();
            }

            this.hasUnsavedChanges = true;
            this.updateButtonStates();
        },

        ensureSortableOnList: function (list) {
            // Check if this list already has a Sortable instance
            const hasInstance = this.sortableInstances.some(instance => instance.el === list);

            if (!hasInstance) {
                try {
                    const sortableInstance = new Sortable(list, {
                        group: 'nested',
                        animation: 150,
                        fallbackOnBody: true,
                        swapThreshold: 0.65,
                        handle: '.dd-handle',
                        filter: '.indent-controls',

                        onMove: (evt) => {
                            const depth = this.calculateDepth(evt.to);
                            if (depth > this.maxDepth) {
                                return false;
                            }
                            return true;
                        },

                        onStart: (evt) => {
                            console.log('Drag started on new list');
                        },

                        onEnd: (evt) => {
                            console.log('Drag ended on new list');
                            if (evt.oldIndex !== evt.newIndex || evt.from !== evt.to) {
                                this.hasUnsavedChanges = true;
                                this.updateButtonStates();
                            }
                        }
                    });

                    this.sortableInstances.push(sortableInstance);
                    console.log('Added Sortable instance to new list');

                } catch (error) {
                    console.error('Error creating Sortable instance for new list:', error);
                }
            }
        },

        serialize: function () {
            const serialize = (list) => {
                const items = [];
                const children = list.children;

                for (let i = 0; i < children.length; i++) {
                    const item = children[i];
                    if (item.classList.contains('dd-item')) {
                        const id = item.getAttribute('data-id');
                        const childList = item.querySelector(':scope > .dd-list');

                        const itemData = { id };
                        if (childList) {
                            itemData.children = serialize(childList);
                        }

                        items.push(itemData);
                    }
                }

                return items;
            };

            return serialize(this.nestedTreeElement);
        },

        save: async function () {
            if (!this.hasUnsavedChanges) {
                console.log('No changes to save');
                return;
            }

            const value = this.serialize();
            console.log('Saving tree data:', value);

            try {
                const result = await this.$wire.updateTree(value);

                if (result && result['reload'] === true) {
                    setTimeout(() => {
                        this.initializeSortable();
                        this.addIndentationButtons();
                    }, 100);
                }

                this.hasUnsavedChanges = false;
                console.log('Tree saved successfully');

            } catch (error) {
                console.error('Error saving tree:', error);
            }
        },

        collapseAll: function () {
            console.log('Collapse all not implemented yet');
        },

        expandAll: function () {
            console.log('Expand all not implemented yet');
        }
    };
}
