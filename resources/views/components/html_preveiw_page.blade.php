<x-filament-panels::page>
    <div class="flex justify-center items-center bg-gray-100 min-h-screen">
        <div class="pdf-container bg-white shadow rounded-lg overflow-hidden p-8" style="width: 1027px; height: 90vh;">
            <iframe
                srcdoc="{{ $htmlContent }}" {{-- Injecte le contenu HTML dans l'iframe --}}
                class="w-full h-full border-none">
            </iframe>
        </div>
    </div>
</x-filament-panels::page>
