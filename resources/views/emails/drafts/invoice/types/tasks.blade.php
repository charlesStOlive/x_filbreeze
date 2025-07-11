<tr>
    <td colspan="2" style="padding: 8px 0;">
        <table width="100%" cellpadding="10" cellspacing="0" border="0" style="border-bottom: 1px solid #ccc;">
            <tr>
                <!-- Colonne principale (4/6) -->
                <td width="66%" valign="top">
                    <div style="font-size: 14px; font-weight: normal;">
                        <strong>{{ $item['data']['title'] ?? 'N/A' }}</strong>
                    </div>
                    @if (!empty($item['data']['description']))
                        <div style="font-size: 12px; color: #666; margin-top: 6px;">
                            {!! \Illuminate\Support\Str::of($item['data']['description'])->markdown()->toHtmlString() !!}
                        </div>
                    @endif
                </td>

                <!-- Colonne quantité × prix unitaire (1/6) -->
                <td width="17%" align="right" valign="top" style="font-size: 14px;">
                    {{ $item['data']['qty'] ?? 0 }} × {{ number_format($item['data']['cu'], 2, ',', ' ') }} € HT
                </td>

                <!-- Colonne total (1/6) -->
                <td width="17%" align="right" valign="top" style="font-size: 14px;">
                    {{ number_format($item['data']['total'] ?? 0, 2, ',', ' ') }} € HT
                </td>
            </tr>
        </table>
    </td>
</tr>
