<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\ModelStates\StateConfig;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

class StateParserService
{
    /**
     * Parse states and transitions from a model
     */
    public function parseModel(string $modelClass, array $options = []): array
    {
        if (!class_exists($modelClass)) {
            throw new \InvalidArgumentException("Model class {$modelClass} does not exist");
        }

        $cacheKey = 'state_parser_' . $modelClass . '_' . md5(serialize($options));
        $cacheDuration = $options['cache_duration'] ?? 3600; // 1 hour default

        return Cache::remember($cacheKey, $cacheDuration, function () use ($modelClass, $options) {
            return $this->parseModelStates($modelClass, $options);
        });
    }

    /**
     * Parse states and transitions from a model instance
     */
    public function parseModelInstance(Model $model, array $options = []): array
    {
        return $this->parseModel(get_class($model), $options);
    }

    /**
     * Clear cache for a specific model
     */
    public function clearCache(string $modelClass): void
    {
        $pattern = 'state_parser_' . $modelClass . '_*';
        // Note: Laravel doesn't have native pattern cache clearing, 
        // so we'll use tags or implement custom clearing if needed
        Cache::flush(); // For now, flush all cache (not ideal for production)
    }

    /**
     * Get all models that have states
     */
    public function getModelsWithStates(): array
    {
        $statesPath = app_path('Models/States');
        $models = [];

        if (!is_dir($statesPath)) {
            return $models;
        }

        $directories = array_filter(scandir($statesPath), function($item) use ($statesPath) {
            return $item !== '.' && $item !== '..' && is_dir($statesPath . '/' . $item);
        });

        foreach ($directories as $dir) {
            $modelClass = "App\\Models\\{$dir}";
            if (class_exists($modelClass)) {
                $models[] = [
                    'name' => $dir,
                    'class' => $modelClass,
                    'has_trait' => in_array('App\\Traits\\HasMermaidStateDiagram', class_uses_recursive($modelClass)),
                    'uses_states' => in_array('Spatie\\ModelStates\\HasStates', class_uses_recursive($modelClass))
                ];
            }
        }

        return $models;
    }

    /**
     * Parse the actual states and transitions
     */
    protected function parseModelStates(string $modelClass, array $options): array
    {
        $stateField = $options['state_field'] ?? 'state';
        
        // Try to get state config from model
        $model = new $modelClass;
        $casts = $model->getCasts();
        $stateClass = $casts[$stateField] ?? null;

        if (!$stateClass || !class_exists($stateClass)) {
            throw new \InvalidArgumentException("No valid state class found for {$modelClass}");
        }

        $stateConfig = $stateClass::config();
        
        return [
            'model' => [
                'class' => $modelClass,
                'name' => class_basename($modelClass),
                'state_field' => $stateField,
                'state_class' => $stateClass
            ],
            'nodes' => $this->parseNodes($stateConfig),
            'edges' => $this->parseEdges($stateConfig),
            'metadata' => $this->generateMetadata($modelClass, $stateConfig),
            'parsed_at' => now()->toISOString(),
            'parser_version' => '1.0.0'
        ];
    }

    /**
     * Parse nodes (states) from state config
     */
    protected function parseNodes(StateConfig $stateConfig): array
    {
        $nodes = [];
        $stateClasses = $this->discoverStateClasses($stateConfig);

        foreach ($stateClasses as $stateClass) {
            $node = $this->parseStateClass($stateClass);
            if ($node) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * Parse edges (transitions) from state config
     */
    protected function parseEdges(StateConfig $stateConfig): array
    {
        $edges = [];
        
        try {
            $reflection = new \ReflectionClass($stateConfig);
            $allowedTransitionsProperty = $reflection->getProperty('allowedTransitions');
            $allowedTransitionsProperty->setAccessible(true);
            $allowedTransitions = $allowedTransitionsProperty->getValue($stateConfig);

            foreach ($allowedTransitions as $transitionKey => $transitionClass) {
                $edge = $this->parseTransition($transitionKey, $transitionClass);
                if ($edge) {
                    $edges[] = $edge;
                }
            }
        } catch (\Exception $e) {
            // Handle any reflection errors gracefully
        }

        return $edges;
    }

    /**
     * Parse a single state class
     */
    protected function parseStateClass(string $stateClass): ?array
    {
        if (!class_exists($stateClass)) {
            return null;
        }

        try {
            $reflectionClass = new \ReflectionClass($stateClass);
            $stateInstance = $reflectionClass->newInstanceWithoutConstructor();

            return [
                'id' => $this->getStateId($stateClass),
                'name' => class_basename($stateClass),
                'class' => $stateClass,
                'label' => $this->getStateLabel($stateInstance, $stateClass),
                'description' => $this->getStateDescription($stateInstance),
                'color' => $this->getStateColor($stateInstance),
                'icon' => $this->getStateIcon($stateInstance),
                'type' => 'state'
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Parse a single transition
     */
    protected function parseTransition(string $transitionKey, string $transitionClass): ?array
    {
        $parts = explode('-', $transitionKey);
        if (count($parts) < 2) {
            return null;
        }

        $fromState = $parts[0];
        $toState = implode('-', array_slice($parts, 1));

        $transitionInstance = null;
        if (class_exists($transitionClass)) {
            try {
                $reflectionClass = new \ReflectionClass($transitionClass);
                $transitionInstance = $reflectionClass->newInstanceWithoutConstructor();
            } catch (\Exception $e) {
                // Ignore instantiation errors
            }
        }

        return [
            'id' => $transitionKey,
            'name' => class_basename($transitionClass),
            'class' => $transitionClass,
            'from' => strtolower($fromState),
            'to' => strtolower($toState),
            'label' => $this->getTransitionLabel($transitionInstance, $transitionClass),
            'description' => $this->getTransitionDescription($transitionInstance),
            'style' => $this->getTransitionStyle($transitionInstance),
            'type' => 'transition'
        ];
    }

    /**
     * Discover state classes from config
     */
    protected function discoverStateClasses(StateConfig $stateConfig): array
    {
        $stateClasses = [];

        try {
            // Try to get registered states first
            $reflection = new \ReflectionClass($stateConfig);
            $registeredStatesProperty = $reflection->getProperty('registeredStates');
            $registeredStatesProperty->setAccessible(true);
            $registeredStates = $registeredStatesProperty->getValue($stateConfig);

            if (!empty($registeredStates)) {
                return $registeredStates;
            }

            // Fallback: discover from base state class
            $baseStateClassProperty = $reflection->getProperty('baseStateClass');
            $baseStateClassProperty->setAccessible(true);
            $baseStateClass = $baseStateClassProperty->getValue($stateConfig);

            if ($baseStateClass && class_exists($baseStateClass)) {
                $stateClasses = $this->discoverStatesFromDirectory($baseStateClass);
            }
        } catch (\Exception $e) {
            // Handle reflection errors gracefully
        }

        return $stateClasses;
    }

    /**
     * Discover states from directory
     */
    protected function discoverStatesFromDirectory(string $baseStateClass): array
    {
        $stateClasses = [];
        $baseReflection = new \ReflectionClass($baseStateClass);
        $namespace = $baseReflection->getNamespaceName();
        $directory = dirname($baseReflection->getFileName());

        if (is_dir($directory)) {
            $files = glob($directory . '/*.php');
            foreach ($files as $file) {
                $className = pathinfo($file, PATHINFO_FILENAME);
                $fullClassName = $namespace . '\\' . $className;

                if (class_exists($fullClassName) && 
                    is_subclass_of($fullClassName, $baseStateClass)) {
                    $classReflection = new \ReflectionClass($fullClassName);
                    if (!$classReflection->isAbstract()) {
                        $stateClasses[] = $fullClassName;
                    }
                }
            }
        }

        return $stateClasses;
    }

    /**
     * Generate metadata
     */
    protected function generateMetadata(string $modelClass, StateConfig $stateConfig): array
    {
        return [
            'model' => $modelClass,
            'model_name' => class_basename($modelClass),
            'parser' => 'StateParserService',
            'version' => '1.0.0',
            'generated_at' => now()->toISOString()
        ];
    }

    // Helper methods for extracting state information
    protected function getStateId(string $stateClass): string
    {
        return strtolower(class_basename($stateClass));
    }

    protected function getStateLabel($stateInstance, string $stateClass): string
    {
        if ($stateInstance instanceof HasLabel) {
            return $stateInstance->getLabel();
        }
        return class_basename($stateClass);
    }

    protected function getStateDescription($stateInstance): ?string
    {
        if ($stateInstance instanceof HasDescription) {
            return $stateInstance->getDescription();
        }
        return null;
    }

    protected function getStateColor($stateInstance): ?string
    {
        if ($stateInstance instanceof HasColor) {
            $color = $stateInstance->getColor();
            
            // Handle array colors (Filament color arrays like ['500' => '#color'])
            if (is_array($color)) {
                return $color['500'] ?? $color[0] ?? '#gray';
            }
            
            // Handle string colors
            if (is_string($color)) {
                return $this->convertFilamentColorToCss($color);
            }
            
            return $color;
        }
        return 'gray';
    }

    /**
     * Convert Filament color names to CSS colors
     */
    protected function convertFilamentColorToCss(string $color): string
    {
        $colorMap = [
            'gray' => '#6B7280',
            'grey' => '#6B7280',
            'red' => '#EF4444',
            'orange' => '#F97316',
            'amber' => '#F59E0B',
            'yellow' => '#EAB308',
            'lime' => '#84CC16',
            'green' => '#10B981',
            'emerald' => '#059669',
            'teal' => '#14B8A6',
            'cyan' => '#06B6D4',
            'sky' => '#0EA5E9',
            'blue' => '#3B82F6',
            'indigo' => '#6366F1',
            'violet' => '#8B5CF6',
            'purple' => '#A855F7',
            'fuchsia' => '#D946EF',
            'pink' => '#EC4899',
            'rose' => '#F43F5E',
            'success' => '#10B981',
            'warning' => '#F59E0B',
            'danger' => '#EF4444',
            'info' => '#3B82F6',
            'primary' => '#6366F1',
            'secondary' => '#64748B',
        ];
        
        return $colorMap[strtolower($color)] ?? $color;
    }

    protected function getStateIcon($stateInstance): ?string
    {
        if ($stateInstance instanceof HasIcon) {
            return $stateInstance->getIcon();
        }
        return null;
    }

    protected function getTransitionLabel($transitionInstance, string $transitionClass): string
    {
        if ($transitionInstance && $transitionInstance instanceof HasLabel) {
            return $transitionInstance->getLabel();
        }
        return class_basename($transitionClass);
    }

    protected function getTransitionDescription($transitionInstance): ?string
    {
        if ($transitionInstance && $transitionInstance instanceof HasDescription) {
            return $transitionInstance->getDescription();
        }
        return null;
    }

    protected function getTransitionStyle($transitionInstance): string
    {
        return 'normal'; // Default style
    }
}