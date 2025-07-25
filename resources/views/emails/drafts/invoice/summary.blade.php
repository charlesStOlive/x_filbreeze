@if ($options['show_intro'] ?? true)
    <h2>Facture #{{ $invoice->code }}</h2>

    @if (!empty($invoice->title))
        <p><strong>Titre :</strong> {{ $invoice->title }}</p>
    @endif

    @if (!empty($invoice->description))
        <p><strong>Description :</strong></p>
        <p>{!! nl2br(e(strip_tags($invoice->description))) !!}</p>
    @endif

    <p>&nbsp;</p>
@endif

<table width="100%" cellpadding="6" cellspacing="0" border="0"
       style="border-collapse: collapse; table-layout: fixed; font-size: 0.8rem; width: 100%; max-width: 800px;">

    {{-- Définir les colonnes : 60% / 20% / 20% --}}
    <colgroup>
        <col style="width: 60%;">
        <col style="width: 20%;">
        <col style="width: 20%;">
    </colgroup>

    {{-- En-tête --}}
    <tr>
        <td colspan="3" style="background-color: #000000; color: #ffffff; font-weight: bold; text-transform: uppercase;">
            Postes
        </td>
    </tr>

    {{-- Lignes des postes --}}
    @foreach ($invoice->items as $item)
        @php $type = $item['type']; @endphp
        @includeIf('emails.drafts.invoice.types.' . $type, ['item' => $item])
    @endforeach

    {{-- Espacement --}}
    <tr><td colspan="3" height="20"></td></tr>

    {{-- Totaux alignés à droite --}}
    @if ($invoice->total_ht_br != $invoice->total_ht)
        <tr>
            <td></td>
            <td style="text-align: right;">Total avant remise :</td>
            <td style="text-align: right;">{{ number_format($invoice->total_ht_br ?? 0, 2, ',', ' ') }} €</td>
        </tr>
    @endif

    <tr>
        <td></td>
        <td style="text-align: right;">Total HT :</td>
        <td style="text-align: right;">{{ number_format($invoice->total_ht ?? 0, 2, ',', ' ') }} €</td>
    </tr>

    @if (($options['show_tva'] ?? true) && $invoice->tx_tva)
        <tr>
            <td></td>
            <td style="text-align: right;">Montant TVA :</td>
            <td style="text-align: right;">{{ number_format($invoice->tva ?? 0, 2, ',', ' ') }} €</td>
        </tr>
        <tr>
            <td></td>
            <td style="text-align: right;"><strong>Total TTC :</strong></td>
            <td style="text-align: right;"><strong>{{ number_format($invoice->total_ttc ?? 0, 2, ',', ' ') }} €</strong></td>
        </tr>
    @endif
</table>
