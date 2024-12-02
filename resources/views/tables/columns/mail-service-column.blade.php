<div class="text-sm px-3 py-4 w-full">
    @foreach ($getState() as $service)
    <div
        class="mb-2 p-3 rounded {{ 
        $service['mode'] === 'Actif' ? 'bg-green-100' : 
        ($service['mode'] === 'Test' ? 'bg-orange-100' : 'bg-gray-100 bg-pattern-diagonal-stripes' 
        ) }}">
        <span class="font-bold">{{ $service['label'] }}&nbsp;: {{ $service['mode'] }}</span>
        @if (!empty($service['options']))
        <ul class="ml-4 text-xs">
            @foreach ($service['options'] as $option)
            <li>
                <span @class([ ''=> $option['value'],
                    'italic' => !$option['value'],
                    ])>
                    {{ $option['label'] }}
                </span>
                <span @class([ 'font-bold'=> $option['value'],
                    'italic' => !$option['value'],
                    ])>
                    : {{ $option['value'] }}
                </span>
            </li>
            @endforeach
        </ul>
        @endif
    </div>
    @endforeach
</div>