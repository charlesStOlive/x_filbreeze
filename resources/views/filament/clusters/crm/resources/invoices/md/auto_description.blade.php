* Libellé du devis : **{{ $quote->title }}**
* Montant devis : **{{ $quote->total_ht }} € HT**
* % facturé : **{{ $facturation['billing_percentage'] }} % **
* Déjà facturé: **{{$quote->total_ht - $facturation['total_quote_left'] ?? 0 }} € HT**
* Reste à facturer : **{{ $facturation['total_quote_left'] - $facturation['total'] }} € HT**