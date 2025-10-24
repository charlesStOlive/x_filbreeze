<?php

namespace App\Examples;

/**
 * Exemples d'utilisation du trait HasMermaidStateDiagram
 * 
 * Ce fichier montre comment utiliser le trait HasMermaidStateDiagram
 * avec différents modèles dans votre application.
 */

use App\Models\Invoice;
use App\Models\Quote; // Si Quote utilise aussi des states

class MermaidTraitExamples
{
    /**
     * Exemple 1: Utilisation basique avec Invoice
     */
    public function basicUsageExample()
    {
        $invoice = Invoice::first();
        
        // Récupérer les données Mermaid au format JSON
        $mermaidData = $invoice->getMermaidData();
        
        // Structure retournée:
        // [
        //     'type' => 'flowchart',
        //     'direction' => 'LR',
        //     'nodes' => [...],
        //     'edges' => [...],
        //     'metadata' => [...]
        // ]
        
        return $mermaidData;
    }

    /**
     * Exemple 2: Générer la syntaxe Mermaid directement
     */
    public function mermaidSyntaxExample()
    {
        $invoice = Invoice::first();
        
        // Syntaxe Mermaid complète
        $syntax = $invoice->toMermaidSyntax();
        
        // Avec options personnalisées
        $customSyntax = $invoice->toMermaidSyntax([
            'type' => 'flowchart',
            'direction' => 'TB'  // Direction verticale
        ]);
        
        return $customSyntax;
    }

    /**
     * Exemple 3: Afficher seulement l'état courant
     */
    public function currentStateExample()
    {
        $invoice = Invoice::first();
        
        // Diagramme simple avec l'état courant
        $currentState = $invoice->getCurrentStateMermaid();
        
        return $currentState;
    }

    /**
     * Exemple 4: Utilisation avec différentes options
     */
    public function advancedOptionsExample()
    {
        $invoice = Invoice::first();
        
        // Diagramme en graphe avec direction de droite à gauche
        $graphData = $invoice->getMermaidData([
            'type' => 'graph',
            'direction' => 'RL'
        ]);
        
        // Diagramme d'état
        $stateData = $invoice->getMermaidData([
            'type' => 'stateDiagram-v2',
            'direction' => 'TB'
        ]);
        
        return [
            'graph' => $graphData,
            'state' => $stateData
        ];
    }

    /**
     * Exemple 5: Intégration dans un contrôleur
     */
    public function controllerExample()
    {
        // Dans un contrôleur
        $invoice = Invoice::find(1);
        
        if (!$invoice) {
            return response()->json(['error' => 'Invoice not found']);
        }
        
        return response()->json([
            'success' => true,
            'data' => $invoice->getMermaidData()
        ]);
    }

    /**
     * Exemple 6: Utilisation dans une vue Blade
     */
    public function bladeViewExample()
    {
        $invoice = Invoice::first();
        $mermaidSyntax = $invoice->toMermaidSyntax();
        
        // Dans votre vue Blade:
        /*
        <div class="mermaid">
            {!! $mermaidSyntax !!}
        </div>
        
        <script>
            mermaid.initialize({startOnLoad:true});
        </script>
        */
        
        return $mermaidSyntax;
    }

    /**
     * Exemple 7: Utilisation avec cache personnalisé
     */
    public function cacheExample()
    {
        $invoice = Invoice::first();
        
        // Le trait utilise automatiquement le cache pendant 1 heure
        // Pour forcer le rafraîchissement, vous pouvez vider le cache:
        
        $cacheKey = 'mermaid_data_' . Invoice::class . '_' . md5(serialize([]));
        \Cache::forget($cacheKey);
        
        // Ensuite récupérer les nouvelles données
        $freshData = $invoice->getMermaidData();
        
        return $freshData;
    }

    /**
     * Exemple 8: Ajouter le trait à un nouveau modèle
     */
    public function addTraitToNewModelExample()
    {
        /*
        // 1. Dans votre modèle (ex: app/Models/Order.php)
        
        use App\Traits\HasMermaidStateDiagram;
        use Spatie\ModelStates\HasStates;
        
        class Order extends Model
        {
            use HasMermaidStateDiagram, HasStates;
            
            protected $casts = [
                'state' => OrderState::class,
            ];
        }
        
        // 2. Utilisation
        $order = Order::first();
        $diagram = $order->getMermaidData();
        */
    }

    /**
     * Exemple 9: Utilisation avec Filament InfoList
     */
    public function filamentInfoListExample()
    {
        /*
        // Dans votre Resource ou Page Filament:
        
        use App\Filament\Infolists\Components\MermaidDiagramEntry;
        
        public static function infolist(Infolist $infolist): Infolist
        {
            return $infolist
                ->schema([
                    MermaidDiagramEntry::make('state_diagram')
                        ->label('États et Transitions')
                        ->apiEndpoint(function ($record) {
                            return route('api.states.mermaid-json-from-trait', [
                                'model' => 'Invoice',
                                'id' => $record->id ?? 'new'
                            ]);
                        })
                        ->type('flowchart')
                        ->direction('TB')
                        ->height('500px'),
                ]);
        }
        */
    }

    /**
     * Exemple 10: Comparaison avec l'API FilamentStateFusion
     */
    public function comparisonExample()
    {
        $invoice = Invoice::first();
        
        // Avec le trait (plus rapide, cache intégré)
        $traitData = $invoice->getMermaidData();
        
        // Avec l'API FilamentStateFusion (plus de métadonnées)
        // Route: /api/states/Invoice/mermaid-json
        
        return [
            'trait_method' => $traitData,
            'api_method' => 'Utilisez l\'API /api/states/Invoice/mermaid-json'
        ];
    }
}