<div class="flex flex-wrap gap-3 p-1">
    @foreach ($this->servicesData as $service)
        @php
            $target = "openService('{$service['key']}')";
            // Couleur du texte selon le mode results ou non
            $textColor = str_contains($service['background_color'], 'bg-white')
                ? 'text-gray-800 dark:text-gray-200'
                : 'text-white';

        @endphp

        <div class="relative">
            @if ($showMessage && !empty($service['message']))
                {{-- Layout avec message : bouton avec dimensions fixes et message à droite --}}
                <button type="button"
                    class="relative flex items-center p-3 {{ $service['background_color'] }} rounded-lg border-6 {{ $service['border_color'] }} {{ $buttonSize }} cursor-pointer hover:opacity-90 transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                    wire:click.stop="openService('{{ $service['key'] }}')" wire:loading.attr="disabled"
                    wire:target="{{ $target }}">

                    {{-- Partie gauche avec icône et titre (taille fixe avec contraintes) --}}
                    <div class="flex flex-col items-center justify-center w-12 flex-shrink-0 min-w-0 overflow-hidden">
                        {{-- Icône du service : remplacée pendant la requête --}}
                        <div class="h-5 w-5 mb-1 flex items-center justify-center flex-shrink-0">
                            {{-- Icône normale --}}
                            <x-filament::icon :icon="$service['icon']" class="h-4 w-4 {{ $textColor }}" wire:loading.remove
                                wire:target="{{ $target }}" />

                            {{-- Spinner à la place de l'icône pendant le chargement --}}
                            <svg class="animate-spin h-4 w-4 {{ $textColor }}" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24" wire:loading wire:target="{{ $target }}"
                                role="status" aria-label="Chargement">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z">
                                </path>
                            </svg>
                        </div>

                        {{-- Label (affiché tout le temps, tronqué si trop long) --}}
                        <span
                            class="text-xs font-medium {{ $textColor }} text-center leading-tight truncate w-full px-1"
                            title="{{ $service['label'] }}">
                            {{ $service['label'] }}
                        </span>
                    </div>

                    {{-- Message à droite dans l'espace restant --}}
                    <div class="flex-1 min-w-0 ml-2 text-left">
                        <div class="text-xs {{ $textColor }} truncate leading-tight"
                            title="{{ $service['message'] }}">
                            {{ $service['message'] }}
                        </div>
                    </div>
                </button>
            @else
                {{-- Layout standard : bouton carré seul --}}
                <button type="button"
                    class="relative flex flex-col items-center justify-center p-3 {{ $service['background_color'] }} rounded-lg border-6 {{ $service['border_color'] }} {{ $buttonSize }} cursor-pointer hover:opacity-90 transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                    wire:click.stop="openService('{{ $service['key'] }}')" wire:loading.attr="disabled"
                    wire:target="{{ $target }}">

                    {{-- Icône du service : remplacée pendant la requête --}}
                    <div class="h-6 w-6 mb-1 flex items-center justify-center">
                        {{-- Icône normale --}}
                        <x-filament::icon :icon="$service['icon']" class="h-6 w-6 {{ $textColor }}" wire:loading.remove
                            wire:target="{{ $target }}" />

                        {{-- Spinner à la place de l'icône pendant le chargement --}}
                        <svg class="animate-spin h-6 w-6 {{ $textColor }}" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 24 24" wire:loading wire:target="{{ $target }}"
                            role="status" aria-label="Chargement">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                    </div>

                    {{-- Label (affiché tout le temps) --}}
                    <span
                        class="text-xs font-medium {{ $textColor }} text-center leading-tight max-w-full truncate">
                        {{ $service['label'] }}
                    </span>
                </button>
            @endif

            {{-- Icônes de statut positionnées par-dessus le bouton --}}
            {{-- Icône en haut à gauche --}}
            <div class="absolute top-1 left-1 {{ $service['icon_background_color'] }} rounded shadow-sm z-20">
                <x-filament::icon :icon="$service['top_left_icon'] ?? 'heroicon-o-pencil'" class=" h-5 w-5 text-white" />
            </div>

            {{-- Icône en bas à droite --}}
            <div
                class="absolute bottom-1 right-1 {{ $service['icon_background_color'] ?? 'bg-gray-400' }} rounded  shadow-sm z-20">
                <x-filament::icon :icon="$service['bottom_right_icon'] ?? 'heroicon-o-check'" class="h-5 w-5 text-white" />
            </div>
        </div>
    @endforeach

    {{-- Modal --}}
    <x-filament::modal :id="$this->modalId()" :heading="$this->heading" :slide-over="false" :width="$modalWidth">
        @if ($openMode === 'edit')
            <form wire:submit.prevent="save" class="space-y-6">
                {{ $this->form }}

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="fi-btn fi-color-gray"
                        wire:click="$dispatch('close-modal', { id: '{{ $this->modalId() }}' })">
                        Fermer
                    </button>

                    <button type="submit" class="fi-btn" wire:loading.attr="disabled" wire:target="save">
                        Enregistrer
                    </button>
                </div>
            </form>
        @else
            {{ $this->serviceInfoSchema }}

            <div class="mt-4 flex justify-end">
                <button type="button" class="fi-btn fi-color-gray"
                    wire:click="$dispatch('close-modal', { id: '{{ $this->modalId() }}' })">
                    Fermer
                </button>
            </div>
        @endif
    </x-filament::modal>
</div>
