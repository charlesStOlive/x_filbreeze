@props([
    'record' => [],
    'recordKeyName' => 'id',
    'childrenKeyName' => 'children',
    'recordTitleAttribute' => 'title',
])

@php
    $recordKey = data_get($record, $recordKeyName);
    $recordTitle = data_get($record, $recordTitleAttribute, 'Untitled');
    $children = data_get($record, $childrenKeyName, []);
    $hasChildren = !empty($children);
@endphp

<li class="dd-item" data-{{ $recordKeyName }}="{{ $recordKey }}">
    <div class="dd-handle">
        <div class="dd-item-content">
            <!-- Drag Handle Icon -->
            <div class="dd-drag-handle">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path
                        d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
                </svg>
            </div>

            <!-- Expand/Collapse Button for items with children -->
            @if ($hasChildren)
                <div class="dd-item-btns">
                    <button data-action="expand" type="button" class="dd-expand-btn hidden" title="Expand">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7">
                            </path>
                        </svg>
                    </button>
                    <button data-action="collapse" type="button" class="dd-collapse-btn" title="Collapse">
                        <svg class="w-4 h-4 rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7">
                            </path>
                        </svg>
                    </button>
                </div>
            @endif

            <!-- Record Title/Content -->
            <div class="dd-item-text">
                {{ $recordTitle }}
            </div>

            <!-- Actions (No-drag zone) -->
            <div class="dd-nodrag dd-item-actions">
                @if (method_exists($this, 'hasEditAction') && $this->hasEditAction())
                    <button type="button" wire:click="callAction('edit', '{{ $recordKey }}')" title="Modifier">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                            </path>
                        </svg>
                    </button>
                @endif

                @if (method_exists($this, 'hasViewAction') && $this->hasViewAction())
                    <button type="button" wire:click="callAction('view', '{{ $recordKey }}')" title="Voir">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                            </path>
                        </svg>
                    </button>
                @endif

                @if (method_exists($this, 'hasDeleteAction') && $this->hasDeleteAction())
                    <button type="button" wire:click="callAction('delete', '{{ $recordKey }}')"
                        wire:confirm="Êtes-vous sûr de vouloir supprimer cet élément ?" title="Supprimer">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                            </path>
                        </svg>
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Children -->
    @if ($hasChildren)
        <ol class="dd-list">
            @foreach ($children as $child)
                @include('components.tree.item', [
                    'record' => $child,
                    'recordKeyName' => $recordKeyName,
                    'childrenKeyName' => $childrenKeyName,
                    'recordTitleAttribute' => $recordTitleAttribute,
                ])
            @endforeach
        </ol>
    @endif
</li>
