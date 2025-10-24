<?php

namespace App\Services\Formatters;

class MermaidFormatter
{

    /**
     * Generate Mermaid diagram from model data
     */
    public function format(array $data, array $options = []): string
    {
        $options = array_merge($this->getDefaultOptions(), $options);
        
        $type = $options['type'] ?? 'flowchart';
        $direction = $options['direction'] ?? 'LR';
        
        $mermaid = "{$type} {$direction}\n";
        
        // Add header comments if enabled
        if ($options['include_comments']) {
            $modelName = $data['metadata']['model_name'] ?? 'Unknown';
            $totalStates = count($data['states'] ?? []);
            $totalTransitions = count($data['transitions'] ?? []);
            $generatedAt = $data['metadata']['generated_at'] ?? now()->toISOString();
            
            $mermaid .= "%% Generated for {$modelName} - {$totalStates} states, {$totalTransitions} transitions\n";
            $mermaid .= "%% Generated at {$generatedAt}\n";
            $mermaid .= "%% Parser: StateParserService v1.0.0\n\n";
        }
        
        // Add states as nodes
        foreach ($data['states'] ?? [] as $state) {
            $mermaid .= $this->formatState($state, $options);
        }
        
        $mermaid .= "\n";
        
        // Add transitions as edges  
        foreach ($data['transitions'] ?? [] as $transition) {
            $mermaid .= $this->formatTransition($transition, $options);
        }
        
        // Add styles if enabled
        if ($options['include_styles']) {
            $mermaid .= "\n";
            foreach ($data['states'] ?? [] as $state) {
                if (!empty($state['color'])) {
                    $color = $this->processColor($state['color']);
                    $mermaid .= "    style {$state['id']} fill:{$color}\n";
                }
            }
        }
        
        return $mermaid;
    }



    /**
     * Format a single state as Mermaid node
     */
    protected function formatState(array $state, array $options): string
    {
        $stateId = $state['id'];
        $label = $state['label'] ?? $state['name'];
        
        // Add description if available and different from label
        if (!empty($state['description']) && $state['description'] !== $label && $options['include_descriptions']) {
            $description = $state['description'];
            
            // Break long descriptions
            if ($options['max_line_length'] && strlen($description) > $options['max_line_length']) {
                $description = substr($description, 0, $options['max_line_length']) . '...';
            }
            
            $label .= "<br/><small>{$description}</small>";
        }
        
        // Choose node shape based on options
        $shape = $this->getNodeShape($state, $options);
        
        return "    {$stateId}{$shape[0]}\"{$label}\"{$shape[1]}\n";
    }

    /**
     * Format a single transition as Mermaid edge
     */
    protected function formatTransition(array $transition, array $options): string
    {
        $from = $transition['from'];
        $to = $transition['to'];
        $label = $transition['label'] ?? '';
        
        // Choose arrow style
        $arrow = $this->getArrowStyle($transition, $options);
        
        if ($label && $options['include_transition_labels']) {
            return "    {$from} {$arrow}|\"{$label}\"| {$to}\n";
        }
        
        return "    {$from} {$arrow} {$to}\n";
    }

    /**
     * Get node shape based on state type and options
     */
    protected function getNodeShape(array $state, array $options): array
    {
        if ($options['node_shape'] === 'auto') {
            // Auto-detect shape based on state properties
            if (str_contains(strtolower($state['name']), 'start') || str_contains(strtolower($state['id']), 'draft')) {
                return ['((', '))'];  // Circle for start states
            }
            if (str_contains(strtolower($state['name']), 'end') || str_contains(strtolower($state['id']), 'final')) {
                return ['[', ']'];    // Rectangle for end states
            }
            return ['[', ']'];        // Default rectangle
        }
        
        return match($options['node_shape']) {
            'circle' => ['((', '))'],
            'rounded' => ['(', ')'],
            'diamond' => ['{', '}'],
            'hexagon' => ['{{', '}}'],
            default => ['[', ']'],  // rectangle
        };
    }

    /**
     * Get arrow style based on transition type
     */
    protected function getArrowStyle(array $transition, array $options): string
    {
        if ($options['arrow_style'] === 'auto') {
            // Auto-detect arrow style based on transition properties
            if (str_contains(strtolower($transition['name']), 'cancel') || str_contains(strtolower($transition['name']), 'reject')) {
                return '-.->'; // Dotted for negative transitions
            }
            return '-->'; // Default solid arrow
        }
        
        return match($options['arrow_style']) {
            'dotted' => '-.->',
            'thick' => '==>',
            'double' => '<-->',
            default => '-->',  // solid
        };
    }

    /**
     * Process color value for Mermaid
     */
    protected function processColor($color): string
    {
        if (is_array($color)) {
            return $color['500'] ?? $color[0] ?? '#6B7280';
        }
        
        if (is_string($color)) {
            // Ensure hex color format
            if (!str_starts_with($color, '#')) {
                return "#{$color}";
            }
            return $color;
        }
        
        return '#6B7280'; // Default gray
    }

    /**
     * Get default options
     */
    public function getDefaultOptions(): array
    {
        return [
            'type' => 'flowchart',
            'direction' => 'LR',
            'include_comments' => true,
            'include_styles' => true,
            'include_descriptions' => true,
            'include_transition_labels' => true,
            'max_line_length' => 30,
            'node_shape' => 'auto', // auto, rectangle, circle, rounded, diamond, hexagon
            'arrow_style' => 'auto', // auto, solid, dotted, thick, double
        ];
    }

    /**
     * Generate Mermaid with specific type (stateDiagram vs flowchart)
     */
    public function asStateDiagram($modelInstance, array $options = []): string
    {
        $options['type'] = 'stateDiagram-v2';
        $options['node_shape'] = 'rectangle'; // State diagrams use rectangles
        return $this->fromModel($modelInstance, $options);
    }

    /**
     * Generate Mermaid as flowchart (default)
     */
    public function asFlowchart($modelInstance, array $options = []): string
    {
        $options['type'] = 'flowchart';
        return $this->fromModel($modelInstance, $options);
    }
}