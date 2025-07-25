<tr style="border-bottom: 1px solid #ccc;">
    {{-- Colonne 1 : Titre + description (66%) --}}
    <td style="vertical-align: top;">
        <div style="font-size: 14px; font-weight: normal;">
            <strong>{{ $item['data']['title'] ?? 'N/A' }}</strong>
        </div>
        @if (!empty($item['data']['description']))
            <div style="font-size: 12px; color: #666; margin-top: 6px;">
                {!! \Illuminate\Support\Str::of($item['data']['description'])->markdown()->toHtmlString() !!}
            </div>
        @endif
    </td>

    {{-- Colonne 2 : Qté × PU --}}
    <td align="right" style="vertical-align: top; font-size: 14px; white-space: nowrap;">
        {{ $item['data']['qty'] ?? 0 }} × {{ number_format($item['data']['cu'], 2, ',', ' ') }} € HT
    </td>

    {{-- Colonne 3 : Total --}}
    <td align="right" style="vertical-align: top; font-size: 14px; white-space: nowrap;">
        {{ number_format($item['data']['total'] ?? 0, 2, ',', ' ') }} € HT
    </td>
</tr>
