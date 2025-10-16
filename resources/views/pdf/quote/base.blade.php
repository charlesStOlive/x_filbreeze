@extends('pdf.layouts.main')

@section('title', 'Document #{{ $quote->name }}')

@section('content')
    <div class="overflow-hidden font-arial">
        <div class="pt-4 pb-8 -ml-2">
            <img class="object-cover h-16" src="{{ asset('images/logo.png') }}" />
        </div>
        <div class="grid grid-cols-3">
            <div class="col-span-1">
                <p class=" text-zinc-600 uppercase font-light text-xl">Devis :</p>
                <p>Codes : {{ $quote->code }}<span class="text-zinc-400">V{{ $quote->version }}</span></p>
                <p>Date {{ $quote->end_at }}</p>
                @if ($quote->submited_at)
                    <p>Fin validité : {{ $quote->end_at->format('d/m/Y') }}</p>
                @endif
            </div>
            <div>
                <p class=" text-zinc-600 uppercase font-light text-xl">Émetteur : </p>
                <p>Charles Saint olive - Notilac</p>
                <p>SIRET : 95388940900017</p>
                <p>N°TVA : FR 41953889409</p>
                <p">12 Chemin de la paserelle<br>69190 - TASSIN LA DEMI LUNE XXX</p>
            </div>
            <div>
                <p class=" text-zinc-600 uppercase font-light text-xl">Client : </p>
                <p>{{ $quote->company->title }}</p>
                <p>{{ $quote->company->address }}</p>
                <p>{{ $quote->company->cp }} {{ $quote->company->city }}</p>
                <p class=" text-zinc-600 uppercase font-light text-xl">Contact : </p>
                <p>{{ $quote->contact->full_name }}</p>
                <p>{{ $quote->contact->email }}</p>
            </div>

        </div>
        <div class="min-h-[950px]">
            <div class="pb-4">
                <div><span class="text-zinc-600 uppercase font-light text-xl"> TITRE : </span>{{ $quote->title }}</div>
                @if (!empty($quote->description))
                    <div class=" font-light text-xl text-zinc-600 uppercase pb-2">Description</div>
                    <div class="prose prose-li:m-0 prose-p:my-0 prose-ul:mt-0 max-w-none  bg-slate-200 p-2 rounded-md">
                        {!! str($quote->description)->markdown() !!}
                    </div>
                @endif
            </div>
            <div class="{{ $options['avoid_break'] ? 'avoid-page-break' : '' }}">
                <div
                    class="pb-4 grid grid-cols-6 px-2 w-full border-y bg-secondary-500 py-4  text-white uppercase text-right rounded-md">
                    <div class="col-span-4 text-left text-xl">Postes</div>
                    <div> - </div>
                    <div class="text-xl">Total</div>
                </div>
                @foreach ($quote->items as $item)
                    @includeIf("pdf.shared.items.{$item['type']}", ['item' => $item])
                @endforeach
            </div>
            <div @if ($options['avoid_amount_break']) style="page-break-inside: avoid;" @endif>
                @if ($quote->total_ht_br != $quote->total_ht)
                    <div class=" pt-4 w-full grid grid-cols-6 text-lg text-zinc-600 text-right">
                        <div class="col-span-3">
                            &nbsp;
                        </div>
                        <div class="col-span-2">
                            Total avant remise
                        </div>
                        <div class="col-span-1">
                            {{ number_format($quote->total_ht_br ?? 0, 2, ',', ' ') }} €
                        </div>
                    </div>
                @endif
                @if ($quote->total_options > 0)
                    <div class=" pt-4 w-full grid grid-cols-6  text-zinc-600 text-right">
                        <div class="col-span-3">
                            &nbsp;
                        </div>
                        <div class="col-span-2">
                            Total options
                        </div>
                        <div class="col-span-1">
                           <span class="bg-green-500 rounded text-white p-1">{{ number_format($quote->total_options ?? 0, 2, ',', ' ') }} €</span> 
                        </div>
                    </div>
                    <div class="pt-4 w-full grid grid-cols-6  text-lg text-right">
                        <div class="col-span-3">
                            &nbsp;
                        </div>
                        <div class="col-span-2">
                            Total HT (ss options)
                        </div>
                        <div class="col-span-1 ">
                            {{ number_format($quote->total_avant_options ?? 0, 2, ',', ' ') }} €
                        </div>
                    </div>
                    <div class="pt-1 w-full grid grid-cols-6  text-lm text-right">
                        <div class="col-span-3">
                            &nbsp;
                        </div>
                        <div class="col-span-2">
                            Total HT (av options)
                        </div>
                        <div class="col-span-1 ">
                            {{ number_format($quote->total_ht ?? 0, 2, ',', ' ') }} €
                        </div>
                    </div>
                @else
                <div class="pt-2 text-sm w-full grid grid-cols-6 text-right">
                    <div class="col-span-3">
                        &nbsp;
                    </div>
                    <div class="col-span-2">
                        Total HT
                    </div>
                    <div class="col-span-1">
                        {{ number_format($quote->total_ht ?? 0, 2, ',', ' ') }} €
                    </div>
                </div>
                @endif
            </div>
        </div>
        <div class="pt-8">
            <div class="border-t pt-2  border-zinc-300">
                <div>Veuillez retourner ce devis signé pour acceptation</div>
            </div>
        </div>
    </div>
@endsection
