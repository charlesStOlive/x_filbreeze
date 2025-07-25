<tr style="background-color: #e6f5e6;">
    {{-- Colonne 1 : titre de la remise --}}
    <td style="vertical-align: top; color: green;">
        <strong>{{ $item['data']['title'] ?? 'Remise' }}</strong>
    </td>

    {{-- Colonne 2 vide (structure 60/20/20 respectée) --}}
    <td></td>

    {{-- Colonne 3 : montant de la remise --}}
    <td align="right" style="vertical-align: top; color: green;">
        -{{ number_format($item['data']['total'] ?? 0, 2, ',', ' ') }} €
    </td>
</tr>
