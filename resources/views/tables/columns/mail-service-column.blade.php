<div class="text-sm px-3 py-4">
    @foreach ($getState() as $service)
        <div class="mb-2">
            <span class="font-bold">{{ $service['label'] }}</span>: 
            <span class="text-primary-500">{{ $service['mode'] }}</span>

            @if (!empty($service['options']))
                <ul class="ml-4 text-xs">
                    @foreach ($service['options'] as $option)
                        <li>
                            <span @class([
                                'text-primary-500 font-bold' => $option['value'],
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
