<div class="grid grid-cols-6 my-2 p-2 w-full border-b border-zinc-300 text-right text-green-500">
    <div class="col-span-4 text-left">
        <div><span class="font-light text-lg">{{ $item['data']['title'] ?? 'N/A' }}</span></div>
    </div>
    <div></div>
    <div>
        -{{ number_format($item['data']['total'] ?? 0, 2, ',', ' ') }} €
    </div>
</div>
