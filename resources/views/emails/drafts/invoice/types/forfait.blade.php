<tr>
    <td colspan="2">
        <table width="100%" cellpadding="10" cellspacing="0" border="0" style="border-bottom: 1px solid #ccc;">
            <tr>
                <td width="80%">
                    <strong>{{ $item['data']['title'] ?? 'N/A' }}</strong><br>
                    @if (!empty($item['data']['description']))
                        <small>{!! str($item['data']['description']) ? str($item['data']['description'])->markdown() : '' !!}</small>
                    @endif
                </td>
                <td width="20%" align="right">
                    {{ number_format($item['data']['total'] ?? 0, 2, ',', ' ') }} € HT
                </td>
            </tr>
        </table>
    </td>
</tr>
