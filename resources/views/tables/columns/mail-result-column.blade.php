<div class="text-sm px-3 py-4">
    @foreach ($getState() as $service)
        <div class="mb-2">
            <span class="font-bold">{{ $service['label'] }} Succès ?</span>: 
            @php
                $success = collect($service['options'])->firstWhere('label', 'success')['value'] ?? false;
                $reason = collect($service['options'])->firstWhere('label', 'reason')['value'] ?? null;
            @endphp
            <span class="text-primary-500">{{ strtoupper($success ? 'OUI' : 'NON') }}</span>

            @if (!$success)
                <p class="text-xs">
                    <span class="font-bold">Raison:</span> 
                    <span class="text-primary-500">{{ $reason }}</span>
                </p>
            @elseif($success)
                <ul class="ml-4 text-xs">
                    @foreach ($service['options'] as $option)
                        @if (!in_array($option['label'], ['success', 'reason']))
                            <li>
                                <span @class([
                                    'text-primary-500 font-bold' => $option['value'],
                                    'italic' => !$option['value'],
                                ])>
                                    {{ $option['label'] }}: {{ $option['value'] }}
                                </span>
                            </li>
                        @endif
                    @endforeach
                </ul>
            @endif
        </div>
    @endforeach
</div>
