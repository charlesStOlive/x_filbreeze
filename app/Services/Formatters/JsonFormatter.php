<?php

namespace App\Services\Formatters;

use App\Contracts\StateFormatterInterface;

class JsonFormatter implements StateFormatterInterface
{
    public function format(array $parsedData, array $options = []): mixed
    {
        $pretty = $options['pretty'] ?? true;
        $includeMetadata = $options['include_metadata'] ?? true;
        $format = $options['format'] ?? 'mermaid'; // 'mermaid', 'raw', 'simple'

        return match($format) {
            'mermaid' => $this->formatForMermaid($parsedData, $options, $pretty, $includeMetadata),
            'raw' => $this->formatRaw($parsedData, $pretty, $includeMetadata),
            'simple' => $this->formatSimple($parsedData, $pretty),
            default => $this->formatForMermaid($parsedData, $options, $pretty, $includeMetadata)
        };
    }

    public function getFormatName(): string
    {
        return 'json';
    }

    public function getMimeType(): ?string
    {
        return 'application/json';
    }

    public function getFileExtension(): ?string
    {
        return 'json';
    }

    public function validateOptions(array $options): bool
    {
        $validFormats = ['mermaid', 'raw', 'simple'];
        
        if (isset($options['format']) && !in_array($options['format'], $validFormats)) {
            return false;
        }

        return true;
    }

    public function getDefaultOptions(): array
    {
        return [
            'pretty' => true,
            'include_metadata' => true,
            'format' => 'mermaid',
            'type' => 'flowchart',
            'direction' => 'LR'
        ];
    }

    /**
     * Format for Mermaid consumption (compatible with existing MermaidDiagramEntry)
     */
    protected function formatForMermaid(array $parsedData, array $options, bool $pretty, bool $includeMetadata): string
    {
        $nodes = $parsedData['nodes'] ?? [];
        $edges = $parsedData['edges'] ?? [];
        $metadata = $parsedData['metadata'] ?? [];

        // Transform nodes for Mermaid format
        $mermaidNodes = array_map(function ($node) {
            return [
                'id' => $node['id'],
                'label' => $node['label'],
                'description' => $node['description'] ?? '',
                'color' => $this->mapColorToHex($node['color'] ?? 'gray'),
                'icon' => $node['icon'] ?? '',
                'class' => $node['class'],
                'type' => 'state'
            ];
        }, $nodes);

        // Transform edges for Mermaid format
        $mermaidEdges = array_map(function ($edge) {
            return [
                'from' => $edge['from'],
                'to' => $edge['to'],
                'label' => $edge['label'],
                'description' => $edge['description'] ?? null,
                'class' => $edge['class'],
                'style' => $edge['style'] ?? 'normal',
                'type' => 'transition'
            ];
        }, $edges);

        $result = [
            'type' => $options['type'] ?? 'flowchart',
            'direction' => $options['direction'] ?? 'LR',
            'nodes' => $mermaidNodes,
            'edges' => $mermaidEdges
        ];

        if ($includeMetadata && !empty($metadata)) {
            $result['metadata'] = [
                'model' => $metadata['model'],
                'total_states' => count($mermaidNodes),
                'total_transitions' => count($mermaidEdges),
                'generated_at' => $metadata['generated_at'] ?? now()->toISOString(),
                'diagram_type' => 'state_machine',
                'parser' => $metadata['parser'] ?? 'StateParserService',
                'version' => $metadata['version'] ?? '1.0.0'
            ];
        }

        $flags = $pretty ? JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE : JSON_UNESCAPED_UNICODE;
        return json_encode($result, $flags);
    }

    /**
     * Format raw parsed data
     */
    protected function formatRaw(array $parsedData, bool $pretty, bool $includeMetadata): string
    {
        $result = $parsedData;
        
        if (!$includeMetadata) {
            unset($result['metadata']);
        }

        $flags = $pretty ? JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE : JSON_UNESCAPED_UNICODE;
        return json_encode($result, $flags);
    }

    /**
     * Format simplified version
     */
    protected function formatSimple(array $parsedData, bool $pretty): string
    {
        $nodes = $parsedData['nodes'] ?? [];
        $edges = $parsedData['edges'] ?? [];

        $result = [
            'states' => array_map(function ($node) {
                return [
                    'id' => $node['id'],
                    'label' => $node['label'],
                    'color' => $node['color'] ?? 'gray'
                ];
            }, $nodes),
            'transitions' => array_map(function ($edge) {
                return [
                    'from' => $edge['from'],
                    'to' => $edge['to'],
                    'label' => $edge['label']
                ];
            }, $edges)
        ];

        $flags = $pretty ? JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE : JSON_UNESCAPED_UNICODE;
        return json_encode($result, $flags);
    }

    /**
     * Map Filament colors to hex
     */
    protected function mapColorToHex(string $color): string
    {
        $colorMap = [
            'gray' => '#6B7280',
            'success' => '#10B981', 
            'danger' => '#EF4444',
            'warning' => '#F59E0B',
            'info' => '#3B82F6',
            'primary' => '#8B5CF6',
            'secondary' => '#6B7280',
        ];

        return $colorMap[$color] ?? $colorMap['gray'];
    }
}