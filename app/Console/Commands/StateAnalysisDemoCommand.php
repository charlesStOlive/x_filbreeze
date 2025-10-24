<?php

namespace App\Console\Commands;

use App\Facades\StateAnalysis;
use Illuminate\Console\Command;

class StateAnalysisDemoCommand extends Command
{
    protected $signature = 'state:demo {model?} {--format=all}';
    protected $description = 'Démonstration du système d\'analyse d\'états unifié';

    public function handle()
    {
        $this->info('🎯 Démonstration du système d\'analyse d\'états unifié');
        $this->line(str_repeat('=', 60));

        if ($this->argument('model')) {
            $this->demonstrateModelAnalysis($this->argument('model'));
        } else {
            $this->demonstrateSystemOverview();
        }

        return 0;
    }

    protected function demonstrateSystemOverview()
    {
        $this->newLine();
        $this->info('📊 Vue d\'ensemble du système');
        $this->line(str_repeat('-', 40));

        $stats = StateAnalysis::getStatistics();

        $this->table(['Statistique', 'Valeur'], [
            ['Modèles total', $stats['models']['total']],
            ['Modèles avec états', $stats['models']['with_states']],
            ['Modèles avec trait Mermaid', $stats['models']['with_mermaid_trait']],
            ['Formatters disponibles', $stats['formatters']['total']],
        ]);

        $this->newLine();
        $this->info('🔧 Formatters disponibles');
        foreach ($stats['formatters']['details'] as $name => $info) {
            $mimeType = $info['mime_type'] ?? 'N/A';
            $this->line("  • <options=bold>{$name}</> ({$mimeType}) - {$info['class']}");
        }

        $this->newLine();
        $this->info('📋 Modèles analysables');
        foreach ($stats['models']['models'] as $model) {
            $stateIcon = $model['uses_states'] ? '✅' : '❌';
            $traitIcon = $model['has_trait'] ? '🎨' : '⚪';
            $this->line("  {$stateIcon} {$traitIcon} <options=bold>{$model['name']}</> ({$model['class']})");
        }

        $this->newLine();
        $this->comment('💡 Utilisez: php artisan state:demo {model} pour analyser un modèle spécifique');
        $this->comment('💡 Exemples: php artisan state:demo Invoice --format=mermaid');
    }

    protected function demonstrateModelAnalysis(string $modelName)
    {
        $modelClass = "App\\Models\\{$modelName}";

        if (!class_exists($modelClass)) {
            $this->error("❌ Modèle {$modelClass} introuvable");
            return;
        }

        $this->info("🔍 Analyse du modèle: {$modelName}");
        $this->line(str_repeat('-', 40));

        $format = $this->option('format');

        if ($format === 'all' || $format === 'mermaid') {
            $this->demonstrateMermaidFormat($modelClass);
        }

        if ($format === 'all' || $format === 'json') {
            $this->demonstrateJsonFormat($modelClass);
        }

        if ($format === 'all' || $format === 'array') {
            $this->demonstrateArrayFormat($modelClass);
        }

        if ($format === 'all') {
            $this->demonstrateBatchAnalysis();
        }
    }

    protected function demonstrateMermaidFormat(string $modelClass)
    {
        $this->newLine();
        $this->info('🎨 Format Mermaid');
        $this->line(str_repeat('-', 30));

        try {
            $mermaid = StateAnalysis::toMermaid($modelClass, [
                'include_comments' => true,
                'include_styles' => true
            ]);

            $this->line('<fg=cyan>' . $mermaid . '</fg=cyan>');
        } catch (\Exception $e) {
            $this->error("Erreur: {$e->getMessage()}");
        }
    }

    protected function demonstrateJsonFormat(string $modelClass)
    {
        $this->newLine();
        $this->info('📄 Format JSON');
        $this->line(str_repeat('-', 30));

        try {
            $json = StateAnalysis::toJson($modelClass, [
                'format' => 'mermaid',
                'pretty_print' => true
            ]);

            // Tronquer si trop long
            $lines = explode("\n", $json);
            if (count($lines) > 20) {
                $lines = array_merge(
                    array_slice($lines, 0, 15),
                    ['    ... (lignes tronquées) ...'],
                    array_slice($lines, -3)
                );
            }

            $this->line('<fg=yellow>' . implode("\n", $lines) . '</fg=yellow>');
        } catch (\Exception $e) {
            $this->error("Erreur: {$e->getMessage()}");
        }
    }

    protected function demonstrateArrayFormat(string $modelClass)
    {
        $this->newLine();
        $this->info('🔢 Format Array (structure)');
        $this->line(str_repeat('-', 30));

        try {
            $array = StateAnalysis::toArray($modelClass, [
                'format' => 'structured',
                'include_metadata' => true
            ]);

            $this->table(['Propriété', 'Valeur'], [
                ['États', count($array['states'] ?? [])],
                ['Transitions', count($array['transitions'] ?? [])],
                ['État initial', $array['initial_state'] ?? 'N/A'],
                ['Métadonnées', isset($array['metadata']) ? 'Oui' : 'Non'],
            ]);

            if (!empty($array['states'])) {
                $this->newLine();
                $this->info('États détectés:');
                foreach ($array['states'] as $state) {
                    $this->line("  • <options=bold>{$state['name']}</> ({$state['label']})");
                }
            }

            if (!empty($array['transitions'])) {
                $this->newLine();
                $this->info('Transitions détectées:');
                foreach ($array['transitions'] as $transition) {
                    $from = $transition['from_state'] ?? '?';
                    $to = $transition['to_state'] ?? '?';
                    $this->line("  • {$from} → {$to} ({$transition['label']})");
                }
            }
        } catch (\Exception $e) {
            $this->error("Erreur: {$e->getMessage()}");
        }
    }

    protected function demonstrateBatchAnalysis()
    {
        $this->newLine();
        $this->info('🚀 Analyse en lot');
        $this->line(str_repeat('-', 30));

        $models = StateAnalysis::getModelsWithStates();
        $modelClasses = array_column(
            array_filter($models, fn($model) => $model['uses_states']),
            'class'
        );

        if (empty($modelClasses)) {
            $this->warn('Aucun modèle avec états trouvé');
            return;
        }

        try {
            $results = StateAnalysis::batchAnalyze($modelClasses, 'array', [
                'format' => 'structured'
            ]);

            $this->table(['Modèle', 'États', 'Transitions', 'Statut'], 
                collect($results)->map(function ($result, $modelClass) {
                    if (isset($result['error'])) {
                        return [
                            class_basename($modelClass),
                            '-',
                            '-',
                            "❌ Erreur"
                        ];
                    }

                    return [
                        class_basename($modelClass),
                        count($result['states'] ?? []),
                        count($result['transitions'] ?? []),
                        "✅ OK"
                    ];
                })->toArray()
            );
        } catch (\Exception $e) {
            $this->error("Erreur lors de l'analyse en lot: {$e->getMessage()}");
        }
    }
}