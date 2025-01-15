<div class="grid grid-cols-6 my-2 p-2 w-full border-b border-zinc-300 text-right">
    <div class="col-span-4 text-left">
        <div><span class="font-light text-lg">{{ $item['data']['title'] ?? 'Depuis devis' }}</span></div>
        @if (!empty($item['data']['description']))
            <div class="prose text-sm text-zinc-600 py-1 max-w-none">
                {!! str($item['data']['description'])->markdown() !!}
            </div>
        @endif
    </div>
    <div>
        {{ $item['data']['billing_percentage'] ?? 0 }}% € de {{ $item['data']['total_quote'] ?? 0 }} € HT
    </div>
    <div>
        {{ number_format($item['data']['total'] ?? 0, 2, ',', ' ') }} € HT
    </div>
</div>
