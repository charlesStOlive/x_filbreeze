@php
    $containerKey = 'tree_container_' . $this->getId();
    $maxDepth = $getMaxDepth() ?? 1;
    $records = collect($this->getTreeRecords() ?? []);
@endphp

<div wire:ignore.self class="filament-tree-original-wrapper">
    <x-filament::section :heading="$this->hasTreeTitle() ?? false ? $this->getTreeTitle() : null">
        <!-- Controls -->
        <div class="flex gap-2 mb-4">
            <x-filament::button color="gray" tag="button"
                onclick="window.TreeControls.expandAll('{{ $containerKey }}')" size="sm">
                <x-heroicon-o-plus class="w-4 h-4 mr-1" />
                Développer tout
            </x-filament::button>

            <x-filament::button color="gray" tag="button"
                onclick="window.TreeControls.collapseAll('{{ $containerKey }}')" size="sm">
                <x-heroicon-o-minus class="w-4 h-4 mr-1" />
                Réduire tout
            </x-filament::button>

            <div class="ml-auto">
                {{ $this->getActions() }}
            </div>
        </div>

        <!-- Tree Container -->
        <div class="dd" id="{{ $containerKey }}" wire:ignore>
            <ol class="dd-list">
                @foreach ($records as $record)
                    @include('components.tree.item', [
                        'record' => $record,
                        'recordKeyName' => 'id',
                        'childrenKeyName' => 'children',
                        'recordTitleAttribute' => 'title',
                    ])
                @endforeach
            </ol>
        </div>
    </x-filament::section>
</div>

@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('vendor/filament-tree/filament-tree.css') }}">
        <style>
            /* Override et amélioration du style original */
            .dd-handle {
                display: flex;
                align-items: center;
                gap: 8px;
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 6px;
                padding: 12px;
                margin: 4px 0;
                cursor: move;
                min-height: 50px;
            }

            .dd-handle:hover {
                border-color: #d1d5db;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            }

            .dd-item-content {
                display: flex;
                align-items: center;
                justify-content: space-between;
                width: 100%;
                gap: 8px;
            }

            .dd-drag-handle {
                color: #9ca3af;
                cursor: move;
            }

            .dd-item-text {
                flex: 1;
                font-weight: 500;
                color: #374151;
            }

            .dd-item-btns {
                display: flex;
                gap: 4px;
            }

            .dd-item-btns button {
                padding: 4px;
                border: none;
                background: none;
                color: #6b7280;
                cursor: pointer;
                border-radius: 4px;
                transition: all 0.2s;
            }

            .dd-item-btns button:hover {
                color: #374151;
                background: #f3f4f6;
            }

            .dd-item-actions {
                display: flex;
                gap: 4px;
            }

            .dd-item-actions button {
                padding: 6px;
                border: none;
                background: none;
                color: #6b7280;
                cursor: pointer;
                border-radius: 4px;
                transition: all 0.2s;
            }

            .dd-item-actions button:hover {
                color: #374151;
                background: #f3f4f6;
            }

            .dd-nodrag {
                cursor: default;
            }

            .dd-placeholder {
                background: #dbeafe;
                border: 2px dashed #3b82f6;
                border-radius: 6px;
                margin: 4px 0;
                min-height: 50px;
            }

            .dd-dragel {
                z-index: 50;
            }

            .dd-dragel .dd-handle {
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
                transform: rotate(3deg);
            }

            .hidden {
                display: none !important;
            }

            .rotate-90 {
                transform: rotate(90deg);
            }
        </style>
    @endpush

    @push('scripts')
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="{{ asset('vendor/filament-tree/components/filament-tree-component.js') }}"></script>
        <script>
            // Global tree controls
            window.TreeControls = {
                instances: {},

                init: function(containerId, maxDepth) {
                    const $container = $('#' + containerId);

                    if ($container.length === 0) return;

                    // Initialize with original plugin functionality
                    const treeInstance = window.filamentTreeComponent({
                        containerKey: containerId,
                        maxDepth: maxDepth
                    });

                    // Override save to work with Livewire
                    const originalSave = treeInstance.save;
                    treeInstance.save = async function() {
                        try {
                            const serializedData = this.serialize();
                            const livewireComponent = Livewire.find(document.querySelector('[wire\\:id]')
                                .getAttribute('wire:id'));
                            if (livewireComponent) {
                                await livewireComponent.call('updateTree', serializedData);
                            }
                        } catch (error) {
                            console.error('Error saving tree:', error);
                        }
                    };

                    treeInstance.init();
                    this.instances[containerId] = treeInstance;
                },

                expandAll: function(containerId) {
                    if (this.instances[containerId]) {
                        this.instances[containerId].expandAll();
                    }
                },

                collapseAll: function(containerId) {
                    if (this.instances[containerId]) {
                        this.instances[containerId].collapseAll();
                    }
                }
            };

            // Initialize when DOM is ready
            document.addEventListener('DOMContentLoaded', function() {
                window.TreeControls.init('{{ $containerKey }}', {{ $maxDepth }});
            });

            // Re-initialize after Livewire updates
            document.addEventListener('livewire:navigated', function() {
                setTimeout(() => {
                    window.TreeControls.init('{{ $containerKey }}', {{ $maxDepth }});
                }, 100);
            });
        </script>
    @endpush
@endonce
