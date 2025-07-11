<tr>
    <td colspan="2">
        <table width="100%" cellpadding="10" cellspacing="0" border="0" style="border-bottom: 1px solid #ccc;">
            <tr>
                <td width="80%" style="color: green;">
                    <strong>{{ $item['data']['title'] ?? 'Remise' }}</strong>
                </td>
                <td width="20%" align="right" style="color: green;">
                    -{{ number_format($item['data']['total'] ?? 0, 2, ',', ' ') }} €
                </td>
            </tr>
        </table>
    </td>
</tr>