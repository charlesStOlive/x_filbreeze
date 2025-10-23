<?php

namespace App\Http\Controllers\Api;

use App\Console\Commands\DocumentStatesCommand;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StatesAnalysisController extends Controller
{
    /**
     * Lister tous les modèles avec états disponibles
     */
    public function index(): JsonResponse
    {
        try {
            $models = DocumentStatesCommand::getAvailableModels();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'models' => $models,
                    'count' => count($models)
                ],
                'message' => 'Modèles avec états récupérés avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des modèles',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Analyser les états et transitions d'un modèle spécifique
     */
    public function show(string $model): JsonResponse
    {
        try {
            $data = DocumentStatesCommand::getModelStatesData($model);
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => "Analyse des états du modèle {$model} réalisée avec succès"
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Modèle introuvable',
                'error' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'analyse du modèle',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtenir uniquement les états d'un modèle
     */
    public function states(string $model): JsonResponse
    {
        try {
            $data = DocumentStatesCommand::getModelStatesData($model);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'model' => $model,
                    'states' => $data['states'] ?? []
                ],
                'message' => "États du modèle {$model} récupérés avec succès"
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Modèle introuvable',
                'error' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des états',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtenir uniquement les transitions d'un modèle
     */
    public function transitions(string $model): JsonResponse
    {
        try {
            $data = DocumentStatesCommand::getModelStatesData($model);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'model' => $model,
                    'transitions' => $data['transitions'] ?? []
                ],
                'message' => "Transitions du modèle {$model} récupérées avec succès"
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Modèle introuvable',
                'error' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des transitions',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Générer la documentation markdown pour un modèle
     */
    public function generateDocs(string $model): JsonResponse
    {
        try {
            // Utiliser la méthode statique pour obtenir les données
            $data = DocumentStatesCommand::getModelStatesData($model);
            
            // Générer le contenu markdown
            $markdown = $this->generateMarkdownContent($model, $data);
            
            // Créer le répertoire s'il n'existe pas
            $docsPath = base_path('docs/states');
            if (!is_dir($docsPath)) {
                mkdir($docsPath, 0755, true);
            }
            
            // Écrire le fichier
            $filename = strtoupper($model) . '_STATES.md';
            $filepath = $docsPath . '/' . $filename;
            file_put_contents($filepath, $markdown);
            
            return response()->json([
                'success' => true,
                'message' => "Documentation markdown générée avec succès pour le modèle {$model}",
                'file_path' => "docs/states/{$filename}",
                'file_size' => filesize($filepath) . ' bytes'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération de la documentation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Générer le contenu markdown
     */
    private function generateMarkdownContent(string $model, array $data): string
    {
        $markdown = "# États et Transitions - Modèle {$model}\n\n";
        $markdown .= "*Documentation générée automatiquement le " . now()->format('d/m/Y à H:i:s') . "*\n\n";
        $markdown .= "## Classe d'État\n\n";
        $markdown .= "**Classe :** `{$data['state_class']}`\n\n";
        
        // États
        if (!empty($data['states'])) {
            $markdown .= "## États Disponibles\n\n";
            $markdown .= "| État | Classe | Label | Couleur | Icône | Description |\n";
            $markdown .= "|------|--------|-------|---------|-------|-------------|\n";
            
            foreach ($data['states'] as $state) {
                $colorDisplay = is_array($state['color']) ? 'Palette personnalisée' : ($state['color'] ?? 'N/A');
                $markdown .= "| {$state['name']} | `{$state['class']}` | {$state['label']} | {$colorDisplay} | {$state['icon']} | {$state['description']} |\n";
            }
            $markdown .= "\n";
        }
        
        // Transitions
        if (!empty($data['transitions'])) {
            $markdown .= "## Transitions Disponibles\n\n";
            $markdown .= "| Transition | Classe | Label | Icône | Formulaire | Redirection |\n";
            $markdown .= "|------------|--------|-------|-------|------------|-------------|\n";
            
            foreach ($data['transitions'] as $transition) {
                $hasForm = $transition['form'] ? 'Oui' : 'Non';
                $hasRedirect = $transition['has_redirection'] ? 'Oui' : 'Non';
                $markdown .= "| {$transition['name']} | `{$transition['class']}` | {$transition['label']} | {$transition['icon']} | {$hasForm} | {$hasRedirect} |\n";
            }
            $markdown .= "\n";
            
            // Détails des transitions avec formulaires
            $transitionsWithForms = array_filter($data['transitions'], fn($t) => $t['form']);
            if (!empty($transitionsWithForms)) {
                $markdown .= "### Détails des Transitions avec Formulaires\n\n";
                foreach ($transitionsWithForms as $transition) {
                    $markdown .= "#### {$transition['label']} (`{$transition['name']}`)\n\n";
                    if (!empty($transition['form_fields'])) {
                        $markdown .= "**Champs du formulaire :**\n";
                        foreach ($transition['form_fields'] as $field) {
                            $markdown .= "- **{$field['name']}** : {$field['label']}\n";
                        }
                        $markdown .= "\n";
                    }
                    if ($transition['has_redirection'] && $transition['redirect_url']) {
                        $markdown .= "**Redirection :** {$transition['redirect_url']}\n\n";
                    }
                }
            }
        }
        
        return $markdown;
    }
    
    /**
     * Obtenir les statistiques globales des états
     */
    public function statistics(): JsonResponse
    {
        try {
            $models = DocumentStatesCommand::getAvailableModels();
            $stats = [
                'total_models' => count($models),
                'models_details' => []
            ];
            
            foreach ($models as $model) {
                try {
                    $data = DocumentStatesCommand::getModelStatesData($model);
                    $stats['models_details'][$model] = [
                        'states_count' => count($data['states'] ?? []),
                        'transitions_count' => count($data['transitions'] ?? [])
                    ];
                } catch (\Exception $e) {
                    $stats['models_details'][$model] = [
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Statistiques globales récupérées avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtenir les données au format Mermaid JSON
     */
    public function mermaidJson(string $model): JsonResponse
    {
        try {
            $data = DocumentStatesCommand::getMermaidJsonData($model);
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => "Données Mermaid pour le modèle {$model} générées avec succès"
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Modèle introuvable',
                'error' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération des données Mermaid',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}