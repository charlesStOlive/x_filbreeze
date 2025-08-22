@props(['title', 'icon', 'description', 'record'])
@php
    use Illuminate\Support\HtmlString;
@endphp

<div 
{{
    $attributes->merge([
        'class' => 'flex items-center flex-1 gap-1'
    ])
}}>
    @if ($icon)
        <div class="w-4">
            <x-dynamic-component :component="$icon" class="w-4 h-4"/>
        </div>
    @endif

    <div @class([
        'ml-4 rtl:mr-4' => !$icon,
        'flex-1',
    ])>
        <span @class([
            'font-semibold',
        ])>
            {{ str($title)->sanitizeHtml()->toHtmlString() }}
        </span>
    
        @if ($description && (is_string($description) || $description instanceof HtmlString))
            @if (is_string($description))
                <span class="text-gray-500 dark:text-gray-400 text-sm truncate">
                    {{ str($description)->sanitizeHtml()->toHtmlString() }}
                </span>
                
            @else
                {!! $description->toHtml() !!}
            @endif
            
        @endif
    </div>
    
</div>