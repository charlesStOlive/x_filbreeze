<tr>
    <td colspan="2">
        <table width="100%" cellpadding="5" cellspacing="0" border="1" style="border-bottom: 1px solid #ccc;">
            <tr>
                <td width="60%">
                    <strong>{{ $item['data']['title'] ?? 'Produit' }}</strong>
                    @if (!empty($item['data']['product_code']))
                        <span style="color: #888; font-size: 12px;">({{ $item['data']['product_code'] }})</span>
                    @endif
                    @if (!empty($item['data']['description']))
                        <div style="font-size: 12px; color: #666;">
                            {{ strip_tags($item['data']['description']) }}
                        </div>
                    @endif
                </td>
                <td width="20%" align="right">
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
                <td width="20%" align="right">
                    {{ number_format($item['data']['total'] ?? 0, 2, ',', ' ') }} € HT
                </td>
            </tr>
        </table>
    </td>
</tr>