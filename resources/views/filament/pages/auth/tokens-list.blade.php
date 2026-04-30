@if(empty($tokens))
    <p class="text-sm text-gray-500 dark:text-gray-400 italic">Aucun token actif.</p>
@else
    <div>
        @foreach($tokens as $token)
            <div class="flex items-center justify-between py-2 border-b border-gray-200 dark:border-gray-700 last:border-0">
                <div class="flex flex-col">
                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $token['name'] }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $token['created_at'] }}</span>
                </div>
                <button
                    wire:click="revokeToken('{{ $token['id'] }}')"
                    wire:confirm="Révoquer le token '{{ $token['name'] }}' ?"
                    type="button"
                    class="text-danger-500 hover:text-danger-700 dark:text-danger-400 dark:hover:text-danger-300 transition-colors p-1 rounded"
                    title="Révoquer"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                    </svg>
                </button>
            </div>
        @endforeach
    </div>
@endif
