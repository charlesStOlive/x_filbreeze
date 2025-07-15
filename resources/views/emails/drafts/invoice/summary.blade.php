<table width="100%" cellpadding="1" cellspacing="0" border="0" style="font-family: Arial, sans-serif; font-size: 14px; color: #333;">

    @if($options['show_intro'] ?? true)
        <tr>
            <td colspan="2" style="font-size: 18px; font-weight: bold; border-bottom: 2px solid #000;">
                Facture #{{ $invoice->code }}
            </td>
        </tr>

        <tr><td colspan="2" height="10"></td></tr>

        @if(!empty($invoice->title))
            <tr>
                <td colspan="2" style="text-transform: uppercase; font-weight: lighter; color: #555;">TITRE : {{ $invoice->title }}</td>
            </tr>
        @endif

        @if (!empty($invoice->description))
            <tr><td colspan="2" height="10"></td></tr>
            <tr>
                <td colspan="2" style="text-transform: uppercase; font-weight: lighter; color: #555;">Description</td>
            </tr>
            <tr>
                <td colspan="2" style="background-color: #f0f0f0; padding: 10px;">
                    {!! nl2br(e(strip_tags($invoice->description))) !!}
                </td>
            </tr>
        @endif

        <tr><td colspan="2" height="20"></td></tr>
    @endif

    <tr>
        <td colspan="2" style="background-color: #000; color: #fff; font-weight: bold; text-transform: uppercase;">
            Postes
        </td>
    </tr>

    @foreach ($invoice->items as $item)
        @php $type = $item['type']; @endphp
        @includeIf("emails.drafts.invoice.types." . $type, ['item' => $item])
    @endforeach

    <tr><td colspan="2" height="20"></td></tr>

    @if ($invoice->total_ht_br != $invoice->total_ht)
        <tr>
            <td align="right">Total avant remise :</td>
            <td align="right">{{ number_format($invoice->total_ht_br ?? 0, 2, ',', ' ') }} €</td>
        </tr>
    @endif
    <tr>
        <td align="right">Total HT :</td>
        <td align="right">{{ number_format($invoice->total_ht ?? 0, 2, ',', ' ') }} €</td>
    </tr>

    @if(($options['show_tva'] ?? true) && $invoice->tx_tva)
        <tr>
            <td align="right">Montant TVA :</td>
            <td align="right">{{ number_format($invoice->tva ?? 0, 2, ',', ' ') }} €</td>
        </tr>
        <tr>
            <td align="right"><strong>Total TTC :</strong></td>
            <td align="right"><strong>{{ number_format($invoice->total_ttc ?? 0, 2, ',', ' ') }} €</strong></td>
        </tr>
    @endif
</table>