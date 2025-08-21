{{-- resources/views/pdf/templates/invoice/summary.blade.php --}}

@extends('pdf.layouts.main')

@section('title', 'Facture #' . $invoice->code)

@section('content')

    <div class="overflow-hidden font-arial ">
        <div class="pt-4 pb-8 -ml-2">
            <img class="object-cover h-16" src="{{ asset('images/logo.png') }}" />
        </div>
        <div class="grid grid-cols-3">
            <div class="col-span-1">
                <p class="font-light text-xl text-zinc-600 uppercase">Facture :</p>
                <p>N° : {{ $invoice->code }}</p>
                @if ($invoice->submited_at)
                    <p>Date : {{ $invoice->submited_at->format('d/m/Y') }}</p>
                @else
                    <p class="bg-red-500 text-white">FACTURE NON VALIDE : BROUILLON</p>
                @endif
            </div>
            <div>
                <p class="font-light text-xl text-zinc-600 uppercase">Émetteur : </p>
                <p>Charles Saint olive - Notilac</p>
                <p>SIRET : 95388940900017</p>
                <p>N°TVA : FR 41953889409</p>
                <p>12 Chemin de la paserelle<br>69190 - TASSIN LA DEMI LUNE</p>
            </div>
            <div>
                <p class="font-light text-xl text-zinc-600 uppercase">Client : </p>
                <p>{{ $invoice->company->title }}</p>
                <p>{{ $invoice->company->address }}</p>
                <p>{{ $invoice->company->cp }} {{ $invoice->company->city }}</p>
                <p class="font-light text-xl text-zinc-600 uppercase">Contact : </p>
                <p>{{ $invoice->contact->full_name }}</p>
                <p>{{ $invoice->contact->email }}</p>
            </div>

        </div>
        <div class="min-h-[920px]">
            <div class="py-4">
                <div><span class=" font-light text-xl text-zinc-600 uppercase"> TITRE : </span>{{ $invoice->title }}
                </div>
                @if (!empty($invoice->description))
                    <div class=" font-light text-xl text-zinc-600 uppercase pb-2 ">Description</div>
                    <div class="prose prose-li:m-0 prose-p:my-0 prose-ul:mt-0 max-w-none  bg-slate-100 p-2 rounded-md">
                        {!! str($invoice->description)->markdown() !!}
                    </div>
                @endif
            </div>
            <div style="{{ $options['avoid_full_break'] ? 'page-break-inside: avoid;' : '' }}">
                <div
                    class="pb-4 grid grid-cols-6 px-2 w-full border-y bg-black py-4  text-white uppercase text-right rounded-md">
                    <div class="col-span-4 text-left text-xl">Postes</div>
                    <div></div>
                    <div class="text-xl">Total</div>
                </div>
                @foreach ($invoice->items as $item)
                    <div style="{{ $options['avoid_inside_break'] ? 'page-break-inside: avoid;' : '' }}">
                        @includeIf("pdf.shared.items.{$item['type']}", ['item' => $item])
                    </div>
                @endforeach
            </div>
            <div style="{{ $options['avoid_amount_break'] ? 'page-break-inside: avoid;' : '' }}">
                @if ($invoice->total_ht_br != $invoice->total_ht)
                    <div class="pt-4 w-full grid grid-cols-6 text-lg  text-right text-zinc-600">
                        <div class="col-span-4">
                            &nbsp;
                        </div>
                        <div class="col-span-1">
                            Total avant remise
                        </div>
                        <div class="col-span-1">
                            {{ number_format($invoice->total_ht_br ?? 0, 2, ',', ' ') }} €
                        </div>
                    </div>
                @endif
                <div class="pt-4 w-full grid grid-cols-6 text-lg  text-right text-zinc-600">
                    <div class="col-span-4">
                        &nbsp;
                    </div>
                    <div class="col-span-1">
                        Total HT
                    </div>
                    <div class="col-span-1">
                        {{ number_format($invoice->total_ht ?? 0, 2, ',', ' ') }} €
                    </div>
                </div>
                @if ($invoice->tx_tva)
                    <div class="pt-4 w-full grid grid-cols-6  text-lg  text-right text-zinc-600 font-light">
                        <div class="col-span-4">
                            &nbsp;
                        </div>
                        <div class="col-span-1">
                            Montant TVA
                        </div>
                        <div class="col-span-1">
                            {{ number_format($invoice->tva ?? 0, 2, ',', ' ') }} €
                        </div>
                    </div>
                    <div class="pt-4 w-full grid grid-cols-6  text-lg  text-right font-light">
                        <div class="col-span-4">
                            &nbsp;
                        </div>
                        <div class="col-span-1">
                            Total TTC
                        </div>
                        <div class="col-span-1">
                            {{ number_format($invoice->total_ttc ?? 0, 2, ',', ' ') }} €
                        </div>
                    </div>
                @endif
            </div>
        </div>

    </div>
    <div class="py-4 text-sm">
        <p>Règlement par virement : {{ $invoice->modalite }} </p>
        <div class=" border-y py-2  border-zinc-300 grid grid-cols-2 text-sm">
            <div class="">
                <p class=" ">Etablissement :</p>
                <p>Charles Saint olive - Notilac</p>
                <p>SIRET : 95388940900017</p>
                <p>12 Chemin de la passerelle 69190 - TASSIN LA DEMI LUNE</p>
            </div>
            <div class="">
                <p class="">Coordonnées bancaires : </p>
                <p>QUONTO : FR76 1695 8000 0159 5505 2105 793</p>
                <p>BIC/SWIFT : QNTOFRP1XXX</p>
                <p>Merci de préciser en référence du règlement le N° : <b>{{ $invoice->code }}</b></p>
            </div>
        </div>
        <p class="text-xs pt-1 text-center">Conformément à l’article L.441-3 et 6 du code de commerce (loi n° 2008-776),
            des pénalités de retard au taux d'intérêt
            légal majoré en vigueur à la date de la facture ; et une indemnité forfaitaire de 40€ pour frais de
            recouvrement sont dues.</p>

    </div>

@endsection
