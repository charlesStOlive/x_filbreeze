<tr>
    <td colspan="2">
        <table width="100%" cellpadding="5" cellspacing="0" border="0" style="border-bottom: 1px solid #ccc;">
            <tr>
                <td width="60%">
                    <strong>{{ $item['data']['title'] ?? 'Depuis devis' }}</strong><br>
                    @if (!empty($item['data']['description']))
                        <small>{{ strip_tags($item['data']['description']) }}</small>
                    @endif
                </td>
                <td width="20%" align="right">
                    {{ $item['data']['billing_percentage'] ?? 0 }}% de {{ $item['data']['total_quote'] ?? 0 }} €
                </td>
                <td width="20%" align="right">
                    {{ number_format($item['data']['total'] ?? 0, 2, ',', ' ') }} € HT
                </td>
            </tr>
        </table>
    </td>
</tr>