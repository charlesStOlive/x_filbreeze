<tr style="border-bottom: 1px solid #ccc;">
    {{-- Colonne description (60%) --}}
    <td style="padding-right: 10px; vertical-align: top;">
        <strong>{{ $item['data']['title'] ?? 'Produit' }}</strong>
        @if (!empty($item['data']['product_code']))
            <span style="color: #888; font-size: 12px;">({{ $item['data']['product_code'] }})</span>
        @endif

        @if (!empty($item['data']['description']))
            <div style="font-size: 12px; color: #666; margin-top: 4px;">
                {!! str($item['data']['description']) ? str($item['data']['description'])->markdown() : '' !!}
            </div>
        @endif
    </td>

    {{-- Colonne quantité × prix (20%) --}}
    <td align="right" style="vertical-align: top; white-space: nowrap;">
        @php
            $unitLabel = match($item['data']['type'] ?? null) {
                'heures' => 'heures',
                'jours' => 'jours',
                default => null,
            };
        @endphp
        @if ($unitLabel)
            {{ $item['data']['qty'] ?? 0 }} {{ $unitLabel }} × {{ number_format($item['data']['cu'] ?? 0, 2, ',', ' ') }} €
        @endif
    </td>

    {{-- Colonne total (20%) --}}
    <td  style="text-align:right;vertical-align: top; white-space: nowrap;">
        {{ number_format($item['data']['total'] ?? 0, 2, ',', ' ') }} € HT
    </td>
</tr>
