<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-primary-50 dark:bg-primary-900/20 p-6 rounded-lg border border-primary-200 dark:border-primary-800">
            <h2 class="text-lg font-semibold text-primary-900 dark:text-primary-100 mb-2">
                🎯 Trait HasMermaidStateDiagram
            </h2>
            <p class="text-primary-800 dark:text-primary-200 text-sm">
                Cette page démontre l'utilisation du trait <code class="bg-primary-100 dark:bg-primary-800 px-2 py-1 rounded">HasMermaidStateDiagram</code> 
                qui permet à n'importe quel modèle d'afficher ses states et transitions sous format Mermaid.
            </p>
        </div>

        {{ $this->mermaidDiagramInfolist }}

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
                <h3 class="text-lg font-semibold mb-4">✨ Fonctionnalités</h3>
                <ul class="space-y-2 text-sm">
                    <li class="flex items-center">
                        <x-heroicon-o-check class="w-4 h-4 text-green-500 mr-2"/>
                        Génération automatique des diagrammes depuis les states
                    </li>
                    <li class="flex items-center">
                        <x-heroicon-o-check class="w-4 h-4 text-green-500 mr-2"/>
                        Support des couleurs et icônes Filament
                    </li>
                    <li class="flex items-center">
                        <x-heroicon-o-check class="w-4 h-4 text-green-500 mr-2"/>
                        Cache intelligent pour les performances
                    </li>
                    <li class="flex items-center">
                        <x-heroicon-o-check class="w-4 h-4 text-green-500 mr-2"/>
                        Multiple formats de sortie (JSON, Mermaid syntax)
                    </li>
                    <li class="flex items-center">
                        <x-heroicon-o-check class="w-4 h-4 text-green-500 mr-2"/>
                        Compatible avec FilamentStateFusion
                    </li>
                </ul>
            </div>

            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
                <h3 class="text-lg font-semibold mb-4">🚀 Comment l'utiliser</h3>
                <ol class="space-y-2 text-sm">
                    <li class="flex items-start">
                        <span class="bg-primary-100 dark:bg-primary-800 text-primary-800 dark:text-primary-200 px-2 py-1 rounded text-xs mr-2 mt-0.5">1</span>
                        Ajouter le trait à votre modèle
                    </li>
                    <li class="flex items-start">
                        <span class="bg-primary-100 dark:bg-primary-800 text-primary-800 dark:text-primary-200 px-2 py-1 rounded text-xs mr-2 mt-0.5">2</span>
                        Appeler les méthodes comme <code>getMermaidData()</code>
                    </li>
                    <li class="flex items-start">
                        <span class="bg-primary-100 dark:bg-primary-800 text-primary-800 dark:text-primary-200 px-2 py-1 rounded text-xs mr-2 mt-0.5">3</span>
                        Utiliser le composant MermaidDiagramEntry dans vos InfoLists
                    </li>
                </ol>
            </div>
        </div>
    </div>
</x-filament-panels::page>