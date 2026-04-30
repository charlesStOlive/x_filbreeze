<div
    x-data="{ copied: false }"
    data-token="{{ $token }}"
    class="flex items-center gap-3 p-3 rounded-lg bg-warning-50 dark:bg-warning-900/20 border border-warning-300 dark:border-warning-700"
>
    <code class="flex-1 text-xs font-mono break-all text-warning-900 dark:text-warning-100">{{ $token }}</code>
    <button
        type="button"
        @click="navigator.clipboard.writeText($el.closest('[data-token]').dataset.token); copied = true; setTimeout(() => copied = false, 2500)"
        class="shrink-0 p-1.5 rounded transition-colors"
        :class="copied ? 'text-success-600 dark:text-success-400' : 'text-warning-600 hover:text-warning-800 dark:text-warning-400 dark:hover:text-warning-200'"
        :title="copied ? 'Copié !' : 'Copier dans le presse-papiers'"
    >
        <template x-if="!copied">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
            </svg>
        </template>
        <template x-if="copied">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
        </template>
    </button>
</div>
