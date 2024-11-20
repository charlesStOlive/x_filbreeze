@php
    // Charger les services depuis la configuration
    $servicesConfig = config('msgraph.services');
    $record = $getRecord();
@endphp

<div class="text-sm px-3 py-4">
    @foreach ($servicesConfig as $serviceKey => $service)
        @php
            // Récupérer le mode de chaque service
            $mode = $record->{$serviceKey . '_mode'} ?? 'inactive';
        @endphp

        @if ($mode !== 'inactive')
            <div class="mb-2">
                <span class="font-bold">{{ $serviceKey }}</span>: 
                <span class="text-primary-500">{{ ucfirst($mode) }}</span>

                @if (!empty($service['options']))
                    <ul class="ml-4 text-xs">
                        @foreach ($service['options'] as $optionKey => $option)
                            @if ($optionKey !== 'mode') {{-- Exclure le champ mode des options --}}
                                <li>
                                    <span @class([
                                        'text-primary-500 font-bold' => $record->{$serviceKey . '_' . $optionKey},
                                        'italic' => !$record->{$serviceKey . '_' . $optionKey},
                                    ])>
                                        {{ $option['label'] ?? ucfirst($optionKey) }}: {{ $record->{$serviceKey . '_' . $optionKey} }}
                                    </span>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    @endforeach
</div>
