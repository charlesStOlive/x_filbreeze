<div class="grid grid-cols-6 my-2 p-2 w-full border-b border-zinc-300 text-right">
    <div class="col-span-4 text-left">
        <div>
            
            <span class="font-semibold text-lg">{{ $item['data']['title'] ?? 'Produit' }}</span>
            @if (!empty($item['data']['product_code']))
                <span class="text-sm text-zinc-500 ml-2">({{ $item['data']['product_code'] }})</span>
            @endif
            @if ($item['data']['is_option'] ?? false)
                <span class="bg-green-500 rounded text-white mr-2 p-1">OPTION</span>
            @endif
        </div>

        @if (!empty($item['data']['description']))
            <div class="prose text-sm text-zinc-600 py-1 max-w-none">
                {!! str($item['data']['description']) ? str($item['data']['description'])->markdown() : '' !!}
            </div>
        @endif
    </div>

    @php
        $unitLabel = match($item['data']['type'] ?? null) {
            'heures' => 'h',
            'jours' => 'j',
            'forfait_m' => 'm',
            'forfait_u' => 'u',
            default => null,
        };
    @endphp

    <div>
        @if ($unitLabel)
            {{ $item['data']['qty'] ?? 0 }} {{ $unitLabel }} × {{ number_format($item['data']['cu'] ?? 0, 2, ',', ' ') }} € HT
        @endif
    </div>

    <div>
        {{ number_format($item['data']['total'] ?? 0, 2, ',', ' ') }} € HT
    </div>
</div>
