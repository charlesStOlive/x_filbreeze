<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devis - {{ $record->title }}</title>
    @if ($preview ?? false)
        @vite('resources/css/pdf/theme.css') {{-- Vite pour la prévisualisation (avec rechargement à chaud) --}}
    @else
        <link href="{{ $cssPath }}" rel="stylesheet"> {{-- CSS statique pour production --}}
    @endif
    <style>
        :root {
            --font-family: 'Inter';
        }
    </style>
    <!-- Inclure les styles CSS principaux -->
</head>

<body>
    <div class="overflow-hidden font-arial">
        <div class="pt-4 pb-8 -ml-2">
            <img class="object-cover h-16" src="{{ asset('images/logo.png') }}" />
        </div>
        <div class="grid grid-cols-3">
            <div class="col-span-1">
                <p class=" text-zinc-600 uppercase font-light text-xl">Devis :</p>
                <p>Codes : {{ $record->code }}<span class="text-zinc-400">V{{ $record->version }}</span></p>
                <p>Date {{ $record->created_at }}</p>
                <p>Fin validité : {{ $record->end_at->format('d/m/Y') }}</p>
            </div>
            <div>
                <p class=" text-zinc-600 uppercase font-light text-xl">Émetteur : </p>
                <p>Charles Saint olive - Notilac</p>
                <p>SIRET : 95388940900017</p>
                <p>12 Chemin de la paserelle<br>69190 - TASSIN LA DEMI LUNE</p>
            </div>
            <div>
                <p class=" text-zinc-600 uppercase font-light text-xl">Client : </p>
                <p>{{ $record->company->title }}</p>
                <p>{{ $record->company->address }}</p>
                <p>{{ $record->company->cp }} {{ $record->company->city }}</p>
                <p class=" text-zinc-600 uppercase font-light text-xl">Contact : </p>
                <p>{{ $record->contact->full_name }}</p>
                <p>{{ $record->contact->email }}</p>
            </div>

        </div>
        <div class="min-h-[950px]">
            <div class="pb-4">
                <div><span class="text-zinc-600 uppercase font-light text-xl"> TITRE : </span>{{ $record->title }}</div>
                @if (!empty($record->description))
                    <div class=" font-light text-xl text-zinc-600 uppercase pb-2">Description</div>
                    <div
                        class="prose prose-li:m-0 prose-p:my-0 prose-ul:mt-0 max-w-none  bg-slate-200 p-2 rounded-md">
                        {!! str($record->description)->markdown() !!}
                    </div>
                @endif
            </div>
            <div class="{{ $avoid_full_break ? 'avoid-page-break' : '' }}">
                <div
                    class="pb-4 grid grid-cols-6 px-2 w-full border-y bg-primary-base py-4  text-white uppercase text-right rounded-md">
                    <div class="col-span-4 text-left text-xl">Postes</div>
                    <div> - </div>
                    <div class="text-xl">Total</div>
                </div>
                @foreach ($record->items as $item)
                    @includeIf("pdf.shared.items.{$item['type']}", ['item' => $item])
                @endforeach
            </div>
            <div @if ($avoid_amount_break) style="page-break-inside: avoid;" @endif>
                @if ($record->total_ht_br != $record->total_ht)
                    <div class=" pt-4 w-full grid grid-cols-6 text-lg text-zinc-600 text-right">
                        <div class="col-span-4">
                            &nbsp;
                        </div>
                        <div class="col-span-1">
                            Total avant remise
                        </div>
                        <div class="col-span-1">
                            {{ number_format($record->total_ht_br ?? 0, 2, ',', ' ') }} €
                        </div>
                    </div>
                @endif
                <div class="pt-4 w-full grid grid-cols-6  text-lg text-right">
                    <div class="col-span-4">
                        &nbsp;
                    </div>
                    <div class="col-span-1">
                        Total HT
                    </div>
                    <div class="col-span-1">
                        {{ number_format($record->total_ht ?? 0, 2, ',', ' ') }} €
                    </div>
                </div>
            </div>
        </div>
        <div class="pt-8">
            <div class="border-t pt-2  border-zinc-300">
                <div>Veuillez retourner ce devis signé pour acceptation</div>
            </div>
        </div>
    </div>
</body>

</html>
