<?php

namespace App\Services\Formatters;

use App\Contracts\StateFormatterInterface;

class MermaidFormatter implements StateFormatterInterface
{
    public function format(array $parsedData, array $options = []): mixed
    {
        $type = $options['type'] ?? 'flowchart';
        $direction = $options['direction'] ?? 'LR';
        $includeComments = $options['include_comments'] ?? true;
        $includeStyles = $options['include_styles'] ?? true;
        $includeTooltips = $options['include_tooltips'] ?? false;

        $nodes = $parsedData['nodes'] ?? [];
        $edges = $parsedData['edges'] ?? [];
        $metadata = $parsedData['metadata'] ?? [];

        $mermaid = "{$type} {$direction}\n";
        
        // Add header comments
        if ($includeComments && !empty($metadata)) {
            $modelName = $metadata['model_name'] ?? 'Unknown';
            $nodeCount = count($nodes);
            $edgeCount = count($edges);
            $generatedAt = $metadata['generated_at'] ?? now()->toISOString();
            
            $mermaid .= "%% Generated for {$modelName} - {$nodeCount} states, {$edgeCount} transitions\n";
            $mermaid .= "%% Generated at {$generatedAt}\n";
            $mermaid .= "%% Parser: StateParserService v1.0.0\n\n";
        }

        // Add nodes
        foreach ($nodes as $node) {
            $nodeId = $node['id'];
            $nodeContent = $this->formatNodeContent($node, $options);
            $mermaid .= "    {$nodeId}[{$nodeContent}]\n";
        }

        $mermaid .= "\n";

        // Add edges
        foreach ($edges as $edge) {
            $from = $edge['from'];
            $to = $edge['to'];
            $label = $edge['label'] ?? '';
            $style = $this->getEdgeStyle($edge);

            if ($label) {
                $mermaid .= "    {$from} {$style}|\"{$label}\"| {$to}\n";
            } else {
                $mermaid .= "    {$from} {$style} {$to}\n";
            }
        }

        $mermaid .= "\n";

        // Add styles
        if ($includeStyles) {
            foreach ($nodes as $node) {
                $nodeId = $node['id'];
                $color = $this->mapColorToHex($node['color'] ?? 'gray');
                $mermaid .= "    style {$nodeId} fill:{$color}\n";
            }
        }

        // Add tooltips/click events
        if ($includeTooltips) {
            $mermaid .= "\n%% Tooltips and interactions\n";
            foreach ($nodes as $node) {
                $nodeId = $node['id'];
                $tooltip = $this->createTooltip($node);
                if ($tooltip) {
                    $mermaid .= "    click {$nodeId} callback \"{$tooltip}\"\n";
                }
            }
        }

        return $mermaid;
    }

    public function getFormatName(): string
    {
        return 'mermaid';
    }

    public function getMimeType(): ?string
    {
        return 'text/plain';
    }

    public function getFileExtension(): ?string
    {
        return 'mmd';
    }

    public function validateOptions(array $options): bool
    {
        $validTypes = ['flowchart', 'graph', 'stateDiagram-v2', 'journey', 'gantt'];
        $validDirections = ['LR', 'RL', 'TB', 'BT', 'TD'];

        if (isset($options['type']) && !in_array($options['type'], $validTypes)) {
            return false;
        }

        if (isset($options['direction']) && !in_array($options['direction'], $validDirections)) {
            return false;
        }

        return true;
    }

    public function getDefaultOptions(): array
    {
        return [
            'type' => 'flowchart',
            'direction' => 'LR',
            'include_comments' => true,
            'include_styles' => true,
            'include_tooltips' => false,
            'max_line_length' => 30
        ];
    }

    /**
     * Format node content with line breaks
     */
    protected function formatNodeContent(array $node, array $options): string
    {
        $label = $node['label'] ?? $node['name'];
        $description = $node['description'] ?? '';
        $maxLineLength = $options['max_line_length'] ?? 30;

        $lines = [$label];

        if ($description && $description !== $label) {
            // Clean description
            $cleanDesc = $this->cleanDescription($description);
            
            if ($cleanDesc && $cleanDesc !== $label) {
                // Split long descriptions
                if (strlen($cleanDesc) > $maxLineLength) {
                    $words = explode(' ', $cleanDesc);
                    $currentLine = '';
                    
                    foreach ($words as $word) {
                        if (strlen($currentLine . ' ' . $word) > $maxLineLength) {
                            if ($currentLine) {
                                $lines[] = $currentLine;
                                $currentLine = $word;
                            } else {
                                $lines[] = $word;
                            }
                        } else {
                            $currentLine = $currentLine ? $currentLine . ' ' . $word : $word;
                        }
                    }
                    
                    if ($currentLine) {
                        $lines[] = $currentLine;
                    }
                } else {
                    $lines[] = $cleanDesc;
                }
            }
        }

        $content = implode('<br/>', $lines);
        return "\"{$content}\"";
    }

    /**
     * Get edge style based on transition properties
     */
    protected function getEdgeStyle(array $edge): string
    {
        $style = $edge['style'] ?? 'normal';
        
        return match($style) {
            'thick' => '==>',
            'dotted' => '-.->',
            default => '-->'
        };
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

    /**
     * Clean description text
     */
    protected function cleanDescription(string $description): string
    {
        return trim(
            preg_replace([
                '/\(Couleur:.*?\)/',
                '/\(Icône:.*?\)/',
                '/\s+/'
            ], [
                '',
                '',
                ' '
            ], $description)
        );
    }

    /**
     * Create tooltip for node
     */
    protected function createTooltip(array $node): ?string
    {
        $parts = [];
        
        if (!empty($node['description'])) {
            $parts[] = $node['description'];
        }
        
        if (!empty($node['icon'])) {
            $parts[] = "Icon: {$node['icon']}";
        }
        
        if (!empty($node['color'])) {
            $parts[] = "Color: {$node['color']}";
        }

        return !empty($parts) ? implode(' | ', $parts) : null;
    }
}