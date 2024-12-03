<div class="text-sm px-3 py-4">
    @foreach ($getState() as $service)
        <div 
            class="mb-2 p-3 rounded 
            {{ $service['success'] ? 'bg-green-100 dark:bg-green-800' : 'bg-gray-100 dark:bg-gray-800 opacity-50' }}">
            <p class="font-bold">{{ $service['label'] }}  : 
            {{ strtoupper($service['success'] ? 'OUI' : 'NON') }}</p>

            <ul class="ml-4 text-xs">
                @foreach ($service['results'] as $result)
                    <li>
                        {{ $result['label'] }}= {{ $result['value'] }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</div>
