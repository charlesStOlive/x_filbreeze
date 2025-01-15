<div class="grid grid-cols-6 my-2 p-2 w-full border-b border-zinc-300 text-right">
    <div class="col-span-4 text-left">
        <div><span class="font-light text-lg">{{ $item['data']['title'] ?? 'N/A' }}</span></div>
        @if (!empty($item['data']['description']))
            <div class="prose text-sm text-zinc-600 py-1 max-w-none">
                {!! str($item['data']['description'])->markdown() !!}
            </div>
        @endif
    </div>
    <div class="col-span-2">
        {{ number_format($item['data']['total'] ?? 0, 2, ',', ' ') }} € HT
    </div>
</div>
