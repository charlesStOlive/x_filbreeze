@php
    use App\Services\EmailsProcessorRegisterServices;

    $services = EmailsProcessorRegisterServices::getAll();

    $record = $getRecord();
@endphp

<div class="text-sm px-3 py-4">
    @foreach ($services as $serviceKey => $service)
        @php
            // Récupérer le mode via getAttribute
            \Log::info($serviceKey);
            
            \Log::info("services.{$serviceKey}.mode");
            $mode = $record->getAttribute("services.{$serviceKey}.mode") ?? 'inactive';
            \Log::info($mode);
        @endphp

        @if ($mode !== 'inactive')
            <div class="mb-2">
                <span class="font-bold">{{ $service['label'] }}</span>: 
                <span class="text-primary-500">{{ ucfirst($mode) }}</span>

                @if (!empty($service['options']))
                    <ul class="ml-4 text-xs">
                        @foreach ($service['options'] as $optionKey => $option)
                            @if ($optionKey !== 'mode')
                                @php
                                    $value = $record->getAttribute("services.{$serviceKey}.{$optionKey}") ?? null;
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
