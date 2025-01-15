<div class="grid grid-cols-6 my-2 p-2 w-full border-b border-zinc-300 text-right">
    <div class="col-span-4 text-left">
        <div><span class="font-light text-lg">{{ $item['data']['title'] ?? 'N/A' }}</span></div>
        <div class="prose text-sm text-zinc-600 py-1 max-w-none">Période : {{ $item['data']['start_at'] ?? 'N/A' }} -
            {{ $item['data']['end_at'] ?? 'N/A' }}
            <ul>
                <li>Nombre de tickets : <b>{{ $item['data']['qty_total'] ?? 'N/A' }}</b></li>
                <li>Nombre de tickets facturables : <b>{{ $item['data']['qty_facturable'] ?? 'N/A' }}</b></li>
                <li>Nombre d'heures facturables : <b>{{ $item['data']['qty'] ?? 'N/A' }}h</b></li>
            </ul>
        </div>
    </div>
    <div>
        {{ $item['data']['qty'] }} hr x {{ number_format($item['data']['cu'], 2, ',', ' ') }} € HT
    </div>
    <div>
        {{ number_format($item['data']['total'] ?? 0, 2, ',', ' ') }} € HT
    </div>
</div>
