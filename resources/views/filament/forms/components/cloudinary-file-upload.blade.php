@php
    $record = $getLivewire()?->getRecord();
    $relation = $getRelationName();
    $image = $record?->{$relation};
    \Log::info('image (in vue)', ['rel' => $relation, 'img' => $image]);
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field" :label-sr-only="$image?->url ? false : true">
    @if ($image?->url)
        <div 
            class="relative w-fit"
            x-data="{ 
                state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')") }} 
            }"
        >
            <img src="{{ $image->url }}"
                style="max-width: {{ $getPreviewWidth() }}px; height:{{ $getPreviewHeight() === 'auto' ? 'auto' : $getPreviewHeight() . 'px' }};"
                class="rounded-xl" />
            <div class="absolute bottom-2 right-2">
                {{ $getAction('delete') }}
            </div>
        </div>
    @else
        {{-- fallback complet sur FilePond --}}
        @include('filament-forms::components.file-upload')
    @endif
</x-dynamic-component>
