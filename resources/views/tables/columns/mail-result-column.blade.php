<div class="text-sm px-3 py-4">
    @foreach ($getState() as $service)
        <div class="mb-2">
            <span class="font-bold">{{ $service['label'] }} Succès ?</span>: 
            <span class="{{ $service['success'] ? 'text-green-500' : 'text-red-500' }}">
                {{ strtoupper($service['success'] ? 'OUI' : 'NON') }}
            </span>

            <ul class="ml-4 text-xs">
                @foreach ($service['results'] as $result)
                    <li>
                        <span @class([
                            'text-primary-500 font-bold' => $result['value'],
                            'italic' => !$result['value'],
                        ])>
                            {{ $result['label'] }}: {{ $result['value'] }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</div>
