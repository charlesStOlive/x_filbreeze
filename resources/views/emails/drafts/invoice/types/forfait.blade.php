<tr style="border-bottom: 1px solid #ccc;">
    {{-- Colonne 1 : titre + description --}}
    <td style="vertical-align: top;">
        <strong>{{ $item['data']['title'] ?? 'N/A' }}</strong><br>
        @if (!empty($item['data']['description']))
            <div style="font-size: 12px; color: #666;">
                {!! str($item['data']['description'])->markdown() !!}
            </div>
        @endif
    </td>

    {{-- Colonne 2 vide (pas utilisée ici, mais structure respectée) --}}
    <td></td>

    {{-- Colonne 3 : total --}}
    <td align="right" style="vertical-align: top; white-space: nowrap;">
        {{ number_format($item['data']['total'] ?? 0, 2, ',', ' ') }} € HT
    </td>
</tr>
