<?php

namespace App\Services\Formatters;

use App\Contracts\StateFormatterInterface;

class ArrayFormatter implements StateFormatterInterface
{
    public function format(array $parsedData, array $options = []): mixed
    {
        $includeMetadata = $options['include_metadata'] ?? true;
        $format = $options['format'] ?? 'structured'; // 'structured', 'flat', 'legacy'

        return match($format) {
            'structured' => $this->formatStructured($parsedData, $includeMetadata),
            'flat' => $this->formatFlat($parsedData),
            'legacy' => $this->formatLegacy($parsedData, $includeMetadata),
            default => $this->formatStructured($parsedData, $includeMetadata)
        };
    }

    public function getFormatName(): string
    {
        return 'array';
    }

    public function getMimeType(): ?string
    {
        return null; // Not applicable for PHP arrays
    }

    public function getFileExtension(): ?string
    {
        return 'php'; // For export purposes
    }

    public function validateOptions(array $options): bool
    {
        $validFormats = ['structured', 'flat', 'legacy'];
        
        if (isset($options['format']) && !in_array($options['format'], $validFormats)) {
            return false;
        }

        return true;
    }

    public function getDefaultOptions(): array
    {
        return [
            'include_metadata' => true,
            'format' => 'structured'
        ];
    }

    /**
     * Format as structured array (default)
     */
    protected function formatStructured(array $parsedData, bool $includeMetadata): array
    {
        $result = [
            'states' => $this->formatStates($parsedData['nodes'] ?? []),
            'transitions' => $this->formatTransitions($parsedData['edges'] ?? []),
            'summary' => [
                'total_states' => count($parsedData['nodes'] ?? []),
                'total_transitions' => count($parsedData['edges'] ?? []),
                'model' => $parsedData['metadata']['model_name'] ?? 'Unknown'
            ]
        ];

        if ($includeMetadata && isset($parsedData['metadata'])) {
            $result['metadata'] = $parsedData['metadata'];
        }

        return $result;
    }

    /**
     * Format as flat array
     */
    protected function formatFlat(array $parsedData): array
    {
        $states = [];
        $transitions = [];

        foreach ($parsedData['nodes'] ?? [] as $node) {
            $states[$node['id']] = $node['label'];
        }

        foreach ($parsedData['edges'] ?? [] as $edge) {
            $transitions[] = $edge['from'] . ' -> ' . $edge['to'] . ' (' . $edge['label'] . ')';
        }

        return [
            'states' => $states,
            'transitions' => $transitions
        ];
    }

    /**
     * Format as legacy array (compatible with old DocumentStatesCommand)
     */
    protected function formatLegacy(array $parsedData, bool $includeMetadata): array
    {
        $states = [];
        $transitions = [];

        foreach ($parsedData['nodes'] ?? [] as $node) {
            $states[] = [
                'name' => $node['id'],
                'label' => $node['label'],
                'description' => $node['description'] ?? '',
                'color' => $node['color'] ?? 'gray',
                'icon' => $node['icon'] ?? '',
                'class' => $node['class']
            ];
        }

        foreach ($parsedData['edges'] ?? [] as $edge) {
            $transitions[] = [
                'name' => $edge['name'] ?? $edge['id'],
                'label' => $edge['label'],
                'from' => $edge['from'],
                'to' => $edge['to'],
                'description' => $edge['description'] ?? '',
                'class' => $edge['class'],
                'style' => $edge['style'] ?? 'normal'
            ];
        }

        $result = [
            'model' => $parsedData['metadata']['model_name'] ?? 'Unknown',
            'states' => $states,
            'transitions' => $transitions
        ];

        if ($includeMetadata && isset($parsedData['metadata'])) {
            $result['metadata'] = $parsedData['metadata'];
        }

        return $result;
    }

    /**
     * Format states with full details
     */
    protected function formatStates(array $nodes): array
    {
        return array_map(function ($node) {
            return [
                'id' => $node['id'],
                'name' => $node['name'],
                'label' => $node['label'],
                'description' => $node['description'] ?? '',
                'color' => $node['color'] ?? 'gray',
                'icon' => $node['icon'] ?? '',
                'class' => $node['class'],
                'type' => $node['type'] ?? 'state'
            ];
        }, $nodes);
    }

    /**
     * Format transitions with full details
     */
    protected function formatTransitions(array $edges): array
    {
        return array_map(function ($edge) {
            return [
                'id' => $edge['id'],
                'name' => $edge['name'],
                'from' => $edge['from'],
                'to' => $edge['to'],
                'label' => $edge['label'],
                'description' => $edge['description'] ?? '',
                'style' => $edge['style'] ?? 'normal',
                'class' => $edge['class'],
                'type' => $edge['type'] ?? 'transition'
            ];
        }, $edges);
    }
}