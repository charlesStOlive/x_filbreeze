<x-filament-panels::page>
    <div class="flex justify-center items-center bg-gray-100">
        <div class=" bg-white shadow rounded-lg overflow-hidden p-8" style="width: 1027px; height: 1456px;">
            {{-- Conteneur pour l'aperçu PDF --}}
            {{-- Utilisation d'un iframe pour afficher le contenu HTML --}}
            <iframe
                srcdoc="{{ $htmlContent }}" {{-- Injecte le contenu HTML dans l'iframe --}}
                class="w-full h-full border-none" style="width: 1027px; height: 1456px;">
            </iframe>
        </div>
    </div>
</x-filament-panels::page>
