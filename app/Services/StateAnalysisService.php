<?php

namespace App\Services;

use App\Contracts\StateFormatterInterface;
use Illuminate\Database\Eloquent\Model;

class StateAnalysisService
{
    protected StateParserService $parser;
    protected StateFormatterService $formatter;

    public function __construct(StateParserService $parser, StateFormatterService $formatter)
    {
        $this->parser = $parser;
        $this->formatter = $formatter;
    }

    /**
     * Analyze a model and return formatted output
     */
    public function analyze(string $modelClass, string $formatName = 'array', array $options = []): mixed
    {
        // Parse the model states
        $parsedData = $this->parser->parseModel($modelClass, $options);
        
        // Format the output
        return $this->formatter->format($parsedData, $formatName, $options);
    }

    /**
     * Analyze a model instance and return formatted output
     */
    public function analyzeInstance(Model $model, string $formatName = 'array', array $options = []): mixed
    {
        $parsedData = $this->parser->parseModelInstance($model, $options);
        return $this->formatter->format($parsedData, $formatName, $options);
    }

    /**
     * Get all models with states
     */
    public function getModelsWithStates(): array
    {
        return $this->parser->getModelsWithStates();
    }

    /**
     * Register a new formatter
     */
    public function registerFormatter(StateFormatterInterface $formatter): void
    {
        $this->formatter->registerFormatter($formatter);
    }

    /**
     * Get available formats
     */
    public function getAvailableFormats(): array
    {
        return $this->formatter->getAvailableFormats();
    }

    /**
     * Get formatter information
     */
    public function getFormattersInfo(): array
    {
        return $this->formatter->getAllFormattersInfo();
    }

    /**
     * Clear cache for a model
     */
    public function clearCache(string $modelClass): void
    {
        $this->parser->clearCache($modelClass);
    }

    /**
     * Get statistics about models with states
     */
    public function getStatistics(): array
    {
        $models = $this->getModelsWithStates();
        $totalModels = count($models);
        $modelsWithTrait = count(array_filter($models, fn($model) => $model['has_trait']));
        $modelsWithStates = count(array_filter($models, fn($model) => $model['uses_states']));

        $formatters = $this->getFormattersInfo();

        return [
            'models' => [
                'total' => $totalModels,
                'with_states' => $modelsWithStates,
                'with_mermaid_trait' => $modelsWithTrait,
                'models' => $models
            ],
            'formatters' => [
                'total' => count($formatters),
                'available' => array_keys($formatters),
                'details' => $formatters
            ],
            'generated_at' => now()->toISOString()
        ];
    }

    /**
     * Batch analyze multiple models
     */
    public function batchAnalyze(array $modelClasses, string $formatName = 'array', array $options = []): array
    {
        $results = [];

        foreach ($modelClasses as $modelClass) {
            try {
                $results[$modelClass] = $this->analyze($modelClass, $formatName, $options);
            } catch (\Exception $e) {
                $results[$modelClass] = [
                    'error' => $e->getMessage(),
                    'success' => false
                ];
            }
        }

        return $results;
    }

    /**
     * Quick Mermaid generation (convenience method)
     */
    public function toMermaid(string $modelClass, array $options = []): string
    {
        return $this->analyze($modelClass, 'mermaid', $options);
    }

    /**
     * Quick JSON generation (convenience method) 
     */
    public function toJson(string $modelClass, array $options = []): string
    {
        return $this->analyze($modelClass, 'json', $options);
    }

    /**
     * Quick array generation (convenience method)
     */
    public function toArray(string $modelClass, array $options = []): array
    {
        return $this->analyze($modelClass, 'array', $options);
    }
}