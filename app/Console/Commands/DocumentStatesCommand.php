<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use App\Filament\Contracts\HasRedirection;

class DocumentStatesCommand extends Command
{
    protected $signature = 'states:analyze {model?} {--format=table : Format de sortie (table, json, markdown, mermaid-json)}';
    protected $description = 'Analyser les métadonnées des états et transitions d\'un modèle';

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
     * Méthode statique pour utiliser l'analyse depuis un contrôleur
     */
    public static function getModelStatesData(string $modelName): array
    {
        $instance = new self();
        $stateClass = "App\\Models\\States\\{$modelName}\\{$modelName}State";
        
        if (!class_exists($stateClass)) {
            throw new \InvalidArgumentException("Classe d'état {$stateClass} introuvable");
        }
        
        return $instance->collectModelData($modelName, $stateClass);
    }
    
    /**
     * Méthode statique pour générer du JSON Mermaid
     */
    public static function getMermaidJsonData(string $modelName): array
    {
        $instance = new self();
        $data = static::getModelStatesData($modelName);
        return $instance->generateMermaidJson($data, $modelName);
    }
    
    /**
     * Lister tous les modèles avec états disponibles
     */
    public static function getAvailableModels(): array
    {
        $statesPath = app_path('Models/States');
        $models = [];
        
        if (is_dir($statesPath)) {
            $directories = array_filter(scandir($statesPath), function($item) use ($statesPath) {
                return $item !== '.' && $item !== '..' && is_dir($statesPath . '/' . $item);
            });
            
            foreach ($directories as $dir) {
                $models[] = $dir;
            }
        }
        
        return $models;
    }
    
    private function listAvailableModels()
    {
        $statesPath = app_path('Models/States');
        $models = [];
        
        if (is_dir($statesPath)) {
            $directories = array_filter(scandir($statesPath), function($item) use ($statesPath) {
                return $item !== '.' && $item !== '..' && is_dir($statesPath . '/' . $item);
            });
            
            foreach ($directories as $dir) {
                $models[] = $dir;
            }
        }
        
        $this->info('📋 Modèles avec états disponibles :');
        foreach ($models as $model) {
            $this->line("  • {$model}");
        }
        
        $this->newLine();
        $this->info('💡 Utilisez: php artisan states:analyze {model}');
        
        return 0;
    }
    
    private function analyzeModel(string $modelName, string $format = 'table')
    {
        $stateClass = "App\\Models\\States\\{$modelName}\\{$modelName}State";
        
        if (!class_exists($stateClass)) {
            $this->error("❌ Classe d'état {$stateClass} introuvable");
            return 1;
        }
        
        // Collecter toutes les données
        $data = $this->collectModelData($modelName, $stateClass);
        
        // Sortir selon le format demandé
        switch ($format) {
            case 'json':
                $this->outputJson($data);
                break;
                
            case 'markdown':
                $this->outputMarkdown($data, $modelName);
                break;
                
            case 'mermaid-json':
                $this->outputMermaidJson($data, $modelName);
                break;
                
            default:
                $this->outputTable($data, $modelName);
                break;
        }
        
        return 0;
    }
    
    private function collectModelData(string $modelName, string $stateClass): array
    {
        return [
            'model' => $modelName,
            'state_class' => $stateClass,
            'states' => $this->collectStatesData($modelName),
            'transitions' => $this->collectTransitionsData($modelName),
            'generated_at' => now()->toISOString(),
        ];
    }
    
    private function collectStatesData(string $modelName): array
    {
        $statesPath = app_path("Models/States/{$modelName}");
        if (!is_dir($statesPath)) {
            return [];
        }
        
        $stateFiles = glob($statesPath . '/*.php');
        $states = [];
        
        foreach ($stateFiles as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);
            
            // Ignorer la classe abstraite et les transitions
            if (str_ends_with($className, 'State') || str_contains($className, 'To')) {
                continue;
            }
            
            $fullClassName = "App\\Models\\States\\{$modelName}\\{$className}";
            
            if (class_exists($fullClassName)) {
                $states[] = $this->analyzeStateClass($fullClassName, $className);
            }
        }
        
        return $states;
    }
    
    private function analyzeStateClass(string $className, string $shortName): array
    {
        $stateInfo = [
            'name' => $shortName,
            'class' => $className,
            'label' => null,
            'color' => null,
            'icon' => null,
            'description' => null,
            'error' => null,
        ];
        
        try {
            // Créer une instance temporaire avec un modèle Eloquent mock générique
            $mockModel = new class extends \Illuminate\Database\Eloquent\Model {
                public $id = 1;
                protected $fillable = ['*'];
            };
            $instance = new $className($mockModel);
            
            // Analyser les métadonnées
            if (method_exists($instance, 'getLabel')) {
                $stateInfo['label'] = $instance->getLabel();
            }
            
            if (method_exists($instance, 'getColor')) {
                $stateInfo['color'] = $instance->getColor();
            }
            
            if (method_exists($instance, 'getIcon')) {
                $stateInfo['icon'] = $instance->getIcon();
            }
            
            if (method_exists($instance, 'getDescription')) {
                $stateInfo['description'] = $instance->getDescription();
            }
            
        } catch (\Exception $e) {
            $stateInfo['error'] = $e->getMessage();
        }
        
        return $stateInfo;
    }
    
    private function displayStateInfo(array $state)
    {
        $this->newLine();
        $this->line("🔸 <fg=yellow>{$state['name']}</fg=yellow>");
        
        if (isset($state['error'])) {
            $this->line("   ❌ Erreur: {$state['error']}");
            return;
        }
        
        if ($state['label']) {
            $this->line("   � Label: {$state['label']}");
        }
        
        if ($state['color']) {
            $color = is_array($state['color']) ? 'Filament Color Array' : $state['color'];
            $this->line("   🎨 Couleur: {$color}");
        }
        
        if ($state['icon']) {
            $this->line("   🔣 Icône: {$state['icon']}");
        }
        
        if ($state['description']) {
            $this->line("   📄 Description: {$state['description']}");
        }
    }
    
    private function collectTransitionsData(string $modelName): array
    {
        $transitionsPath = app_path("Models/States/{$modelName}");
        $transitionFiles = glob($transitionsPath . '/*To*.php');
        $transitions = [];
        
        foreach ($transitionFiles as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);
            $fullClassName = "App\\Models\\States\\{$modelName}\\{$className}";
            
            if (class_exists($fullClassName)) {
                $transitions[] = $this->analyzeTransitionClass($fullClassName, $className, $modelName);
            }
        }
        
        return $transitions;
    }
    
    private function analyzeTransitionClass(string $className, string $shortName, string $modelName): array
    {
        $transitionInfo = [
            'name' => $shortName,
            'class' => $className,
            'label' => null,
            'color' => null,
            'icon' => null,
            'form' => null,
            'form_fields' => [],
            'has_redirection' => false,
            'redirect_url' => null,
            'error' => null,
        ];
        
        try {
            // Essayer de créer le bon type de modèle
            $modelClass = "App\\Models\\{$modelName}";
            if (class_exists($modelClass)) {
                $mockModel = new $modelClass();
                $mockModel->id = 1;
            } else {
                // Fallback vers un modèle générique
                $mockModel = new class extends \Illuminate\Database\Eloquent\Model {
                    public $id = 1;
                };
            }
            
            $instance = new $className($mockModel);
            
            // Label
            if (method_exists($instance, 'getLabel')) {
                $transitionInfo['label'] = $instance->getLabel();
            }
            
            // Couleur
            if (method_exists($instance, 'getColor')) {
                $color = $instance->getColor();
                $stateInfo['color'] = $color; // Garder la valeur brute pour JSON
            }
            
            // Icône
            if (method_exists($instance, 'getIcon')) {
                $transitionInfo['icon'] = $instance->getIcon();
            }
            
            // Formulaire
            if (method_exists($instance, 'form')) {
                $form = $instance->form();
                if ($form && is_array($form) && !empty($form)) {
                    $transitionInfo['form'] = true;
                    foreach ($form as $field) {
                        if (method_exists($field, 'getName') && method_exists($field, 'getLabel')) {
                            $transitionInfo['form_fields'][] = [
                                'name' => $field->getName(),
                                'label' => $field->getLabel(),
                            ];
                        }
                    }
                } else {
                    $transitionInfo['form'] = false;
                }
            }
            
            // Redirection
            if ($instance instanceof HasRedirection) {
                $transitionInfo['has_redirection'] = true;
                
                // Essayer d'obtenir l'URL de redirection
                try {
                    $redirectUrl = $instance->getRedirectUrl($mockModel);
                    $transitionInfo['redirect_url'] = $redirectUrl;
                } catch (\Exception $e) {
                    $transitionInfo['redirect_url'] = 'error';
                }
            }
            
        } catch (\Exception $e) {
            $transitionInfo['error'] = $e->getMessage();
        }
        
        return $transitionInfo;
    }
    
    private function outputJson(array $data): void
    {
        $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    private function outputMarkdown(array $data, string $modelName): void
    {
        $docsPath = base_path('docs/states');
        if (!is_dir($docsPath)) {
            mkdir($docsPath, 0755, true);
        }
        
        $filename = $docsPath . '/' . strtoupper($modelName) . '_STATES.md';
        $markdown = $this->generateMarkdown($data);
        
        file_put_contents($filename, $markdown);
        $this->info("📄 Documentation Markdown générée : {$filename}");
    }
    
    private function generateMarkdown(array $data): string
    {
        $markdown = "# États et Transitions - {$data['model']}\n\n";
        $markdown .= "> Documentation générée automatiquement le " . now()->format('d/m/Y à H:i') . "\n\n";
        
        // États
        $markdown .= "## 📊 États disponibles\n\n";
        foreach ($data['states'] as $state) {
            $markdown .= "### {$state['name']}\n\n";
            
            if ($state['error']) {
                $markdown .= "❌ **Erreur:** {$state['error']}\n\n";
                continue;
            }
            
            $markdown .= "| Propriété | Valeur |\n";
            $markdown .= "|-----------|--------|\n";
            
            if ($state['label']) {
                $markdown .= "| 📝 Label | {$state['label']} |\n";
            }
            
            if ($state['color']) {
                $colorDisplay = is_array($state['color']) ? 'Filament Color Array' : $state['color'];
                $markdown .= "| 🎨 Couleur | `{$colorDisplay}` |\n";
            }
            
            if ($state['icon']) {
                $markdown .= "| 🔣 Icône | `{$state['icon']}` |\n";
            }
            
            if ($state['description']) {
                $markdown .= "| 📄 Description | {$state['description']} |\n";
            }
            
            $markdown .= "\n";
        }
        
        // Transitions
        $markdown .= "## 🔄 Transitions disponibles\n\n";
        foreach ($data['transitions'] as $transition) {
            $markdown .= "### {$transition['name']}\n\n";
            
            if ($transition['error']) {
                $markdown .= "❌ **Erreur:** {$transition['error']}\n\n";
                continue;
            }
            
            $markdown .= "| Propriété | Valeur |\n";
            $markdown .= "|-----------|--------|\n";
            
            if ($transition['label']) {
                $markdown .= "| 📝 Label | {$transition['label']} |\n";
            }
            
            if ($transition['color']) {
                $colorDisplay = is_array($transition['color']) ? 'Filament Color Array' : $transition['color'];
                $markdown .= "| 🎨 Couleur | `{$colorDisplay}` |\n";
            }
            
            if ($transition['icon']) {
                $markdown .= "| 🔣 Icône | `{$transition['icon']}` |\n";
            }
            
            if ($transition['form'] !== null) {
                $formStatus = $transition['form'] ? 'OUI' : 'NON';
                $markdown .= "| 📋 Formulaire | {$formStatus} |\n";
                
                if ($transition['form'] && !empty($transition['form_fields'])) {
                    $markdown .= "| 📝 Champs | ";
                    $fields = [];
                    foreach ($transition['form_fields'] as $field) {
                        $fields[] = "`{$field['name']}`: {$field['label']}";
                    }
                    $markdown .= implode('<br>', $fields) . " |\n";
                }
            }
            
            $redirectStatus = $transition['has_redirection'] ? 'OUI' : 'NON';
            $markdown .= "| 🔀 Redirection | {$redirectStatus} |\n";
            
            if ($transition['has_redirection'] && $transition['redirect_url']) {
                if ($transition['redirect_url'] !== 'error') {
                    $markdown .= "| 🔗 URL | `{$transition['redirect_url']}` |\n";
                } else {
                    $markdown .= "| 🔗 URL | Erreur lors de l'évaluation |\n";
                }
            }
            
            $markdown .= "\n";
        }
        
        $markdown .= "---\n\n";
        $markdown .= "*Documentation générée par `php artisan states:analyze {$data['model']} --format=markdown`*\n";
        
        return $markdown;
    }
    
    private function outputTable(array $data, string $modelName): void
    {
        $this->info("🔍 Analyse des métadonnées : {$modelName}");
        $this->line(str_repeat('=', 60));
        
        // États
        $this->newLine();
        $this->info('📊 ÉTATS DISPONIBLES');
        $this->line(str_repeat('-', 40));
        
        foreach ($data['states'] as $state) {
            $this->displayStateInfo($state);
        }
        
        // Transitions
        $this->newLine();
        $this->info('🔄 TRANSITIONS DISPONIBLES');
        $this->line(str_repeat('-', 40));
        
        foreach ($data['transitions'] as $transition) {
            $this->displayTransitionInfo($transition);
        }
    }
    
    private function displayTransitionInfo(array $transition): void
    {
        $this->newLine();
        $this->line("🔄 <fg=cyan>{$transition['name']}</fg=cyan>");
        
        if ($transition['error']) {
            $this->line("   ❌ Erreur d'analyse: {$transition['error']}");
            return;
        }
        
        if ($transition['label']) {
            $this->line("   📝 Label: {$transition['label']}");
        }
        
        if ($transition['color']) {
            $colorDisplay = is_array($transition['color']) ? 'Filament Color Array' : $transition['color'];
            $this->line("   🎨 Couleur: {$colorDisplay}");
        }
        
        if ($transition['icon']) {
            $this->line("   🔣 Icône: {$transition['icon']}");
        }
        
        if ($transition['form'] !== null) {
            $formStatus = $transition['form'] ? 'OUI' : 'NON';
            $this->line("   📋 Formulaire: {$formStatus}");
            
            if ($transition['form'] && !empty($transition['form_fields'])) {
                foreach ($transition['form_fields'] as $field) {
                    $this->line("      • {$field['name']}: {$field['label']}");
                }
            }
        }
        
        $redirectStatus = $transition['has_redirection'] ? 'OUI (implémente HasRedirection)' : 'NON';
        $this->line("   🔀 Redirection: {$redirectStatus}");
        
        if ($transition['has_redirection'] && $transition['redirect_url']) {
            if ($transition['redirect_url'] !== 'error') {
                $this->line("      URL: {$transition['redirect_url']}");
            } else {
                $this->line("      URL: Erreur lors de l'évaluation");
            }
        }
    }
    
    private function outputMermaidJson(array $data, string $modelName): void
    {
        $mermaidData = $this->generateMermaidJson($data, $modelName);
        echo json_encode($mermaidData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    private function generateMermaidJson(array $data, string $modelName): array
    {
        $nodes = [];
        $edges = [];
        $styles = [];
        
        // Générer les nœuds à partir des états
        foreach ($data['states'] as $state) {
            $node = [
                'id' => $state['name'],
                'label' => $state['label']
            ];
            
            // Ajouter la couleur si disponible
            $color = $this->extractColor($state['color']);
            if ($color) {
                $node['color'] = $color;
            }
            
            // Ajouter l'icône si disponible
            if (!empty($state['icon'])) {
                $node['icon'] = $state['icon'];
            }
            
            // Description enrichie
            $description = $state['description'];
            if ($color && $color !== 'N/A') {
                $description .= " (Couleur: {$color})";
            }
            if (!empty($state['icon'])) {
                $description .= " (Icône: {$state['icon']})";
            }
            $node['description'] = $description;
            
            $nodes[] = $node;
        }
        
        // Générer les arêtes à partir des transitions
        foreach ($data['transitions'] as $transition) {
            // Déterminer les états source et destination
            $fromState = $this->extractStateFromTransition($transition, $data['states']);
            $toState = $this->extractTargetStateFromTransition($transition, $data['states']);
            
            if ($fromState && $toState) {
                $edge = [
                    'from' => $fromState,
                    'to' => $toState,
                    'label' => $transition['label']
                ];
                
                // Ajouter la couleur si disponible
                $color = $this->extractColor($transition['color']);
                if ($color) {
                    $edge['color'] = $color;
                }
                
                // Description enrichie de la transition
                $description = $transition['label'];
                
                if ($transition['form']) {
                    $description .= " (Avec formulaire)";
                    if (!empty($transition['form_fields'])) {
                        $fields = array_map(fn($field) => $field['name'], $transition['form_fields']);
                        $description .= " [Champs: " . implode(', ', $fields) . "]";
                    }
                }
                
                if ($transition['has_redirection']) {
                    $description .= " (Avec redirection)";
                    if ($transition['redirect_url'] && $transition['redirect_url'] !== 'error') {
                        $description .= " [URL: {$transition['redirect_url']}]";
                    }
                }
                
                $edge['description'] = $description;
                
                // Style de ligne selon le type
                if ($transition['form']) {
                    $edge['style'] = 'thick'; // Ligne épaisse pour les transitions avec formulaire
                } elseif ($transition['has_redirection']) {
                    $edge['style'] = 'dotted'; // Ligne pointillée pour les redirections
                }
                
                $edges[] = $edge;
            }
        }
        
        return [
            'type' => 'flowchart',
            'direction' => 'LR',
            'nodes' => $nodes,
            'edges' => $edges,
            'metadata' => [
                'model' => $modelName,
                'generated_at' => now()->toISOString(),
                'total_states' => count($nodes),
                'total_transitions' => count($edges)
            ]
        ];
    }
    
    /**
     * Extraire la couleur utilisable pour Mermaid
     */
    private function extractColor($color): ?string
    {
        if (is_array($color)) {
            // Prendre la couleur 500 si c'est un array de couleurs
            return $color['500'] ?? null;
        }
        
        if (is_string($color) && $color !== 'N/A') {
            // Convertir les noms de couleurs Filament en couleurs CSS
            return $this->convertFilamentColorToCss($color);
        }
        
        return null;
    }
    
    /**
     * Convertir les couleurs Filament en couleurs CSS
     */
    private function convertFilamentColorToCss(string $color): string
    {
        $colorMap = [
            'gray' => '#6b7280',
            'grey' => '#6b7280',
            'success' => '#10b981',
            'danger' => '#ef4444',
            'warning' => '#f59e0b',
            'info' => '#3b82f6',
            'primary' => '#6366f1',
            'secondary' => '#64748b',
        ];
        
        return $colorMap[strtolower($color)] ?? $color;
    }
    
    private function extractStateFromTransition(array $transition, array $states): ?string
    {
        $transitionName = $transition['name'];
        
        // Patterns communs pour déterminer l'état source
        if (str_contains($transitionName, 'To') && !str_starts_with($transitionName, 'To')) {
            // Pour les transitions "CanceledToDraft", etc.
            $parts = explode('To', $transitionName);
            if (count($parts) === 2) {
                return $parts[0];
            }
        } elseif (str_starts_with($transitionName, 'To')) {
            // Pour les transitions "ToValidated", "ToCanceled", etc.
            // Ces transitions peuvent partir de plusieurs états, utilisons une logique simple
            $targetState = substr($transitionName, 2);
            
            // Mapping logique basé sur les conventions métier
            if ($targetState === 'Canceled') {
                return 'Draft'; // Draft -> Canceled
            } elseif ($targetState === 'Validated') {
                return 'Draft'; // Draft -> Validated
            } elseif ($targetState === 'Draft') {
                return 'Validated'; // Validated -> Draft (annuler validation)
            } elseif ($targetState === 'Payed') {
                return 'Submited'; // Submited -> Payed
            } elseif ($targetState === 'Submited') {
                return 'Draft'; // Draft -> Submited
            }
        }
        
        return null;
    }
    
    private function extractTargetStateFromTransition(array $transition, array $states): ?string
    {
        $transitionName = $transition['name'];
        
        // Patterns communs pour déterminer l'état cible
        if (str_starts_with($transitionName, 'To')) {
            // Pour les transitions "ToValidated", "ToCanceled", etc.
            return substr($transitionName, 2);
        } elseif (str_contains($transitionName, 'To')) {
            // Pour les transitions "CanceledToDraft", etc.
            $parts = explode('To', $transitionName);
            if (count($parts) === 2) {
                return $parts[1];
            }
        }
        
        return null;
    }
}