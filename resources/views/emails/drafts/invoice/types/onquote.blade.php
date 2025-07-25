<tr style="border-bottom: 1px solid #ccc;">
    {{-- Colonne 1 : Titre + description --}}
    <td style="vertical-align: top;">
        <strong>{{ $item['data']['title'] ?? 'Depuis devis' }}</strong><br>
        @if (!empty($item['data']['description']))
            <div style="font-size: 12px; color: #666;">
                {{ strip_tags($item['data']['description']) }}
            </div>
        @endif
    </td>

    {{-- Colonne 2 : Pourcentage x montant --}}
    <td align="right" style="vertical-align: top; white-space: nowrap;">
        {{ $item['data']['billing_percentage'] ?? 0 }}% de {{ number_format($item['data']['total_quote'] ?? 0, 2, ',', ' ') }} €
    </td>

    {{-- Colonne 3 : Total HT --}}
    <td align="right" style="vertical-align: top; white-space: nowrap;">
        {{ number_format($item['data']['total'] ?? 0, 2, ',', ' ') }} € HT
    </td>
</tr>
