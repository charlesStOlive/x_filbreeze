<div class="text-sm px-3 py-4 w-full">
    @foreach ($getState() as $service)
    <div
        class="mb-2 p-3 rounded {{
                $service['mode'] === 'Actif' ? 'bg-green-100 dark:bg-green-800' :
                ($service['mode'] === 'Test' ? 'bg-orange-100 dark:bg-orange-800' : 'bg-gray-100 dark:bg-gray-800')
            }}">
        <span class="font-bold">{{ $service['label'] }}&nbsp;: {{ $service['mode'] }}</span>
        @if (!empty($service['options']))
        <ul class="ml-4 text-xs">
            @foreach ($service['options'] as $option)
            <li>
                <span @class([ ''=> $option['value'],
                    'italic' => !$option['value'],
                    ])>
                    {{ $option['label'] }}: {{ $option['value'] }}
                </span>
            </li>
            @endforeach
        </ul>

        @endif
    </div>
    @endforeach
</div>