<x-dynamic-component
    :component="$getEntryWrapperView()"
    :entry="$entry"
>
    @php
        $modelClass = $getState();
        $diagramId = $entry->getDiagramId();
        $apiEndpoint = $entry->getApiEndpoint();
        $height = $entry->getHeight();
        $theme = $entry->getTheme();
        $type = $entry->getType();
        $direction = $entry->getDirection();
    @endphp
    
        <div 
        x-load
        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('mermaid-diagram') }}"
        x-data="mermaidDiagramComponent({
            apiEndpoint: '{{ $apiEndpoint }}',
            diagramId: '{{ $diagramId }}',
            height: '{{ $height }}',
            theme: '{{ $theme }}',
            type: '{{ $type }}',
            direction: '{{ $direction }}'
        })"
        class="mermaid-diagram-container"
        data-diagram-id="{{ $diagramId }}"
    >
        {{-- Loading state --}}
        <div x-show="loading" class="flex items-center justify-center p-8 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
            <div class="text-center">
                <svg class="animate-spin -ml-1 mr-3 h-8 w-8 text-gray-500 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Chargement du diagramme...</p>
            </div>
        </div>

        {{-- Error state --}}
        <div x-show="error" class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Erreur de chargement</h3>
                    <p class="mt-1 text-sm text-red-700 dark:text-red-300" x-text="error"></p>
                    <div class="mt-2">
                        <button 
                            @click="refresh()"
                            class="text-sm bg-red-100 dark:bg-red-800 text-red-800 dark:text-red-200 px-3 py-1 rounded hover:bg-red-200 dark:hover:bg-red-700"
                        >
                            Réessayer
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Diagram container --}}
        <div 
            x-show="!loading && !error"
            class="mermaid-diagram-container bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4"
        >
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    Diagramme des états
                </h3>
                <button 
                    @click="refresh()"
                    class="text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-3 py-1 rounded hover:bg-gray-200 dark:hover:bg-gray-600"
                >
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Actualiser
                </button>
            </div>
            
            <div 
                id="{{ $diagramId }}"
                class="mermaid-diagram text-center"
                style="min-height: 300px;"
            ></div>
            
            {{-- Metadata --}}
            <div x-html="getMetadataHtml()"></div>
        </div>
    </div>
</x-dynamic-component>
