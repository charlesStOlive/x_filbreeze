<x-filament-panels::page @class([
    'fi-resource-edit-record-page',
    'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    'fi-resource-record-' . $record->getKey(),
])>
    {{-- Layout avec formulaire à gauche (75%) et infolist à droite (25%) --}}
    <div class="flex flex-wrap gap-6">
        {{-- Formulaire principal (75%) --}}
        <div class="flex-grow md:flex-[3] min-w-0">
            {{ $this->form }}
        </div>

        {{-- Infolist (25%) --}}
        <div class="self-start w-full md:w-1/4 sticky top-20">
            {{ $this->infolist }}
        </div>
    </div>

    {{-- Relation managers (seront affichés automatiquement par Filament) --}}
</x-filament-panels::page>
