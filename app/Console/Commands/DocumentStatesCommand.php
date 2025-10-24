<?php

namespace App\Console\Commands;

use App\Facades\StateAnalysis;
use Illuminate\Console\Command;

class DocumentStatesCommand extends Command
{
    protected $signature = 'states:analyze {model?} {--format=table : Format de sortie (table, json, markdown, mermaid-json, mermaid)}';
    protected $description = 'Analyser les états et transitions d\'un modèle avec le service unifié';

    public function handle()
    {
        $model = $this->argument('model');
        $format = $this->option('format');
        
        if (!$model) {
            return $this->listAvailableModels();
        }
        
        return $this->analyzeModel($model, $format);
    }
    
    /**
     * Méthode statique pour rétrocompatibilité (utilisée par les contrôleurs)
     */
    public static function getModelStatesData(string $modelName): array
    {
        $modelClass = "App\\Models\\{$modelName}";
        return StateAnalysis::toArray($modelClass, [
            'format' => 'legacy',
            'include_metadata' => true
        ]);
    }

    /**
     * Méthode statique pour rétrocompatibilité (utilisée par les contrôleurs)
     */
    public static function getMermaidJsonData(string $modelName): array
    {
        $modelClass = "App\\Models\\{$modelName}";
        $jsonData = StateAnalysis::toJson($modelClass, [
            'format' => 'mermaid',
            'pretty_print' => false,
            'include_metadata' => true
        ]);
        
        return json_decode($jsonData, true);
    }

    protected function listAvailableModels()
    {
        $this->info('📋 Modèles avec états disponibles :');
        
        $models = StateAnalysis::getModelsWithStates();
        
        foreach ($models as $model) {
            $stateIcon = $model['uses_states'] ? '✅' : '❌';
            $traitIcon = $model['has_trait'] ? '🎨' : '⚪';
            $this->line("  {$stateIcon} {$traitIcon} <options=bold>{$model['name']}</> ({$model['class']})");
        }
        
        $this->newLine();
        $this->info('💡 Utilisez: php artisan states:analyze {model} --format={format}');
        $this->comment('Formats disponibles: table, json, markdown, mermaid-json, mermaid');
        
        return 0;
    }

    protected function analyzeModel(string $modelName, string $format)
    {
        $modelClass = "App\\Models\\{$modelName}";
        
        if (!class_exists($modelClass)) {
            $this->error("❌ Modèle {$modelClass} introuvable");
            return 1;
        }

        try {
            switch ($format) {
                case 'json':
                    $this->outputJson($modelClass);
                    break;
                    
                case 'markdown':
                    $this->outputMarkdown($modelClass, $modelName);
                    break;
                    
                case 'mermaid-json':
                    $this->outputMermaidJson($modelClass);
                    break;
                    
                case 'mermaid':
                    $this->outputMermaid($modelClass);
                    break;
                    
                default:
                    $this->outputTable($modelClass, $modelName);
                    break;
            }
        } catch (\Exception $e) {
            $this->error("❌ Erreur lors de l'analyse: {$e->getMessage()}");
            return 1;
        }
        
        return 0;
    }

    protected function outputJson(string $modelClass): void
    {
        $json = StateAnalysis::toJson($modelClass, [
            'format' => 'raw',
            'pretty_print' => true,
            'include_metadata' => true
        ]);
        
        $this->line($json);
    }

    protected function outputMarkdown(string $modelClass, string $modelName): void
    {
        $markdown = StateAnalysis::analyze($modelClass, 'markdown', [
            'include_mermaid_diagram' => true,
            'include_metadata' => true
        ]);
        
        // Sauvegarder dans un fichier
        $docsPath = base_path('docs/states');
        if (!is_dir($docsPath)) {
            mkdir($docsPath, 0755, true);
        }
        
        $filename = $docsPath . '/' . strtoupper($modelName) . '_STATES.md';
        file_put_contents($filename, $markdown);
        
        $this->info("📄 Documentation Markdown générée : {$filename}");
        
        // Afficher aussi dans le terminal (tronqué)
        $lines = explode("\n", $markdown);
        if (count($lines) > 50) {
            $lines = array_merge(
                array_slice($lines, 0, 30),
                ['', '... (lignes tronquées, voir fichier) ...', ''],
                array_slice($lines, -10)
            );
        }
        
        $this->line('<fg=yellow>' . implode("\n", $lines) . '</fg=yellow>');
    }

    protected function outputMermaidJson(string $modelClass): void
    {
        $json = StateAnalysis::toJson($modelClass, [
            'format' => 'mermaid',
            'pretty_print' => true,
            'include_metadata' => true
        ]);
        
        $this->line($json);
    }

    protected function outputMermaid(string $modelClass): void
    {
        $mermaid = StateAnalysis::toMermaid($modelClass, [
            'include_comments' => true,
            'include_styles' => true
        ]);
        
        $this->line('<fg=cyan>' . $mermaid . '</fg=cyan>');
    }

    protected function outputTable(string $modelClass, string $modelName): void
    {
        $data = StateAnalysis::toArray($modelClass, [
            'format' => 'structured',
            'include_metadata' => true
        ]);
        
        $this->info("🔍 Analyse des états : {$modelName}");
        $this->line(str_repeat('=', 60));
        
        // Tableau des états
        if (!empty($data['states'])) {
            $this->newLine();
            $this->info('📊 ÉTATS DISPONIBLES');
            
            $statesTable = [];
            foreach ($data['states'] as $state) {
                $statesTable[] = [
                    $state['name'] ?? 'N/A',
                    $state['label'] ?? 'N/A',
                    $state['color'] ?? 'N/A',
                    $state['icon'] ?? 'N/A'
                ];
            }
            
            $this->table(['Nom', 'Label', 'Couleur', 'Icône'], $statesTable);
        }
        
        // Tableau des transitions
        if (!empty($data['transitions'])) {
            $this->newLine();
            $this->info('🔄 TRANSITIONS DISPONIBLES');
            
            $transitionsTable = [];
            foreach ($data['transitions'] as $transition) {
                $from = $transition['from_state'] ?? $transition['from'] ?? 'N/A';
                $to = $transition['to_state'] ?? $transition['to'] ?? 'N/A';
                
                $transitionsTable[] = [
                    $transition['name'] ?? 'N/A',
                    $transition['label'] ?? 'N/A',
                    $from,
                    $to
                ];
            }
            
            $this->table(['Nom', 'Label', 'De', 'Vers'], $transitionsTable);
        }
        
        // Métadonnées
        if (!empty($data['metadata'])) {
            $this->newLine();
            $this->info('ℹ️ MÉTADONNÉES');
            
            $metaTable = [];
            foreach ($data['metadata'] as $key => $value) {
                if (is_scalar($value)) {
                    $metaTable[] = [ucfirst(str_replace('_', ' ', $key)), $value];
                }
            }
            
            $this->table(['Propriété', 'Valeur'], $metaTable);
        }
    }
}