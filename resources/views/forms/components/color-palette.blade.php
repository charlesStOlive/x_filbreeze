@php
    $isDisabled = $isDisabled();
    $statePath = $getStatePath();
    $options = $getOptions();
    $selectedState = $getState();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div 
        class="flex flex-wrap gap-4"
        x-data="{ selected: '{{ $selectedState }}' }"
        id="{{ $getId() }}"
        wire:model.defer="{{ $statePath }}" {{-- Liaison Livewire --}}
    >
        @if (empty($options))
            <div class="text-gray-500 italic">
                Nous n'avons pas trouvé d'image ou extrait les couleurs.
            </div>
        @else
            @foreach ($options as $value => $label)
                <div
                    class="w-10 h-10 rounded-full cursor-pointer border-2 flex items-center justify-center"
                    :class="{ 'ring-4 ring-blue-500': selected === '{{ $label }}' }" {{-- Comparer avec le label --}}
                    style="background-color: {{ $label }};" {{-- Utilisation du label comme couleur --}}
                    @click="selected = '{{ $label }}'; $dispatch('input', '{{ $label }}'); Livewire.emit('updateFormState', '{{ $statePath }}', '{{ $label }}')" {{-- Émettre le label --}}
                ></div>
            @endforeach
        @endif
    </div>
</x-dynamic-component>
