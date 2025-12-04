<div class="flex flex-wrap gap-3 p-1">
    @if (empty($this->servicesData))
        <div class="text-gray-500 text-sm p-4 text-center">
            Aucun service détecté
        </div>
    @else
        @foreach ($this->servicesData as $service)
            @php
                $target = "openService('{$service['key']}')";
                // Couleur du texte selon le mode results ou non
                $textColor = str_contains($service['background_color'], 'bg-white')
                    ? 'text-gray-800 dark:text-gray-200'
                    : 'text-white';

            @endphp

            {{-- Layout horizontal unique : icône gauche, texte ferré à gauche, icônes droite --}}
            <button type="button"
                class="relative flex items-center gap-2 p-1 {{ $service['background_color'] }} rounded-lg border-2 {{ $service['border_color'] }} {{ $buttonSize }} cursor-pointer hover:opacity-90 transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 overflow-hidden"
                wire:click.stop="openService('{{ $service['key'] }}')" wire:loading.attr="disabled"
                wire:target="{{ $target }}">

                {{-- Icône principale à gauche (centrée verticalement) --}}
                <div class="flex items-center justify-center w-8 h-8 shrink-0">
                    {{-- Icône normale --}}
                    <x-filament::icon :icon="$service['icon']" class="h-6 w-6 {{ $textColor }}" wire:loading.remove
                        wire:target="{{ $target }}" />

                    {{-- Spinner pendant le chargement --}}
                    <svg class="animate-spin h-6 w-6 {{ $textColor }}" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 24 24" wire:loading wire:target="{{ $target }}" role="status"
                        aria-label="Chargement">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                </div>

                {{-- Icône status (uniquement en mode results) --}}
                @if ($service['show_status_icon'] ?? false)
                    <div class="flex items-center justify-center w-6 h-6 shrink-0">
                        <div class="{{ $service['status_icon_background'] }} rounded-full p-0.5">
                            <x-filament::icon :icon="$service['status_icon']" class="h-4 w-4 text-white" />
                        </div>
                    </div>
                @endif

                {{-- Texte ferré à gauche (2 lignes) --}}
                <div class="flex-1 min-w-0 text-left">
                    {{-- Ligne 1 : Label --}}
                    <div class="text-sm font-medium {{ $textColor }} truncate leading-tight"
                        title="{{ $service['label'] }}">
                        {{ $service['label'] }}
                    </div>

                    {{-- Ligne 2 : Message (si showMessage) --}}
                    @if ($showMessage && !empty($service['message']))
                        <div class="text-xs {{ $textColor }} truncate leading-tight mt-0.5"
                            title="{{ $service['message'] }}">
                            {{ $service['message'] }}
                        </div>
                    @endif
                </div>

                {{-- Icônes à droite (2 icônes : mode ouverture + mode service) --}}
                <div class="flex items-center gap-1 flex-shrink-0">
                    {{-- Icône 1 : Mode d'ouverture (view/edit) - cachée en mode results --}}
                    @if ($service['show_open_mode_icon'])
                        <div class="rounded p-1">
                            <x-filament::icon :icon="$service['open_mode_icon']"
                                class="h-4 w-4 {{ $service['open_mode_icon_color'] }}" />
                        </div>
                    @endif

                    {{-- Icône 2 : Mode de service (actif/test/inactif) - affichée selon le contexte --}}
                    @if ($service['show_mode_icon'])
                        <div class="{{ $service['mode_icon_background'] }} rounded-full p-1">
                            <x-filament::icon :icon="$service['mode_icon']" class="h-4 w-4 text-white" />
                        </div>
                    @endif
                </div>
            </button>
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
    @endif
</div>
