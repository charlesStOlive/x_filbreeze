@php
    use App\Services\EmailsProcessorRegisterServices;

    $services = EmailsProcessorRegisterServices::getAll();
    $record = $getRecord();
@endphp

<div class="text-sm px-3 py-4">
    @foreach ($services as $serviceKey => $service)
        @php
            // Récupérer le mode via getAttribute
            $mode = $record->getAttribute("services.{$serviceKey}.mode") ?? 'inactive';
        @endphp

        @if ($mode !== 'inactive')
            <div class="mb-2">
                <span class="font-bold">{{ $service['label'] }} Succès ?</span>: 
                @php
                    $resultSuccess = $record->getAttribute("results.{$serviceKey}.success") ? 'OUI' : 'NON';
                    $reasonError = $record->getAttribute("results.{$serviceKey}.reason") ?? null;
                @endphp
                <span class="text-primary-500">{{ strtoupper($resultSuccess) }}</span>

                @if ($resultSuccess === 'NON')
                    <p class="text-xs">
                        <span class="font-bold">Raison:</span> 
                        <span class="text-primary-500">{{ $reasonError }}</span>
                    </p>
                @elseif(!empty($service['results']) && $resultSuccess === 'OUI')
                    <ul class="ml-4 text-xs">
                        @foreach ($service['results'] as $optionKey => $option)
                            @if ($optionKey !== 'success' && $optionKey !== 'reason')
                                @php
                                    $value = $record->getAttribute("results.{$serviceKey}.{$optionKey}") ?? null;
                                @endphp
                                <li>
                                    <span @class([
                                        'text-primary-500 font-bold' => $value,
                                        'italic' => !$value,
                                    ])>
                                        {{ $option['label'] }}: {{ $value }}
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
