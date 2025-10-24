<?php

namespace App\Services\Formatters;

use App\Contracts\StateFormatterInterface;

class MarkdownFormatter implements StateFormatterInterface
{
    public function getFormatName(): string
    {
        return 'markdown';
    }

    public function getMimeType(): ?string
    {
        return 'text/markdown';
    }

    public function getFileExtension(): ?string
    {
        return 'md';
    }

    public function format(array $data, array $options = []): string
    {
        $options = array_merge($this->getDefaultOptions(), $options);
        
        $modelName = $data['metadata']['model_name'] ?? 'Unknown Model';
        $generatedAt = $data['metadata']['generated_at'] ?? now()->format('d/m/Y à H:i');
        
        $markdown = "# États et Transitions - {$modelName}\n\n";
        $markdown .= "> Documentation générée automatiquement le {$generatedAt}\n\n";
        
        // Section États
        $markdown .= "## 📊 États disponibles\n\n";
        
        if (!empty($data['nodes'])) {
            foreach ($data['nodes'] as $node) {
                $markdown .= $this->formatStateSection($node, $options);
            }
        } else {
            $markdown .= "Aucun état trouvé.\n\n";
        }
        
        // Section Transitions
        $markdown .= "## 🔄 Transitions disponibles\n\n";
        
        if (!empty($data['edges'])) {
            foreach ($data['edges'] as $edge) {
                $markdown .= $this->formatTransitionSection($edge, $options);
            }
        } else {
            $markdown .= "Aucune transition trouvée.\n\n";
        }
        
        // Section Diagramme Mermaid (si demandé)
        if ($options['include_mermaid_diagram']) {
            $markdown .= $this->formatMermaidSection($data, $options);
        }
        
        // Section Métadonnées (si demandé)
        if ($options['include_metadata'] && !empty($data['metadata'])) {
            $markdown .= $this->formatMetadataSection($data['metadata'], $options);
        }
        
        // Footer
        $markdown .= "---\n\n";
        $markdown .= "*Documentation générée par StateAnalysisService*\n";
        
        return $markdown;
    }

    protected function formatStateSection(array $node, array $options): string
    {
        $markdown = "### {$node['label']}\n\n";
        
        if (!empty($node['description']) && $node['description'] !== $node['label']) {
            $markdown .= "{$node['description']}\n\n";
        }
        
        $markdown .= "| Propriété | Valeur |\n";
        $markdown .= "|-----------|--------|\n";
        $markdown .= "| 📛 Nom | `{$node['name']}` |\n";
        $markdown .= "| 🏷️ Label | {$node['label']} |\n";
        
        if (!empty($node['color'])) {
            $colorDisplay = is_string($node['color']) ? $node['color'] : 'Personnalisée';
            $markdown .= "| 🎨 Couleur | {$colorDisplay} |\n";
        }
        
        if (!empty($node['icon'])) {
            $markdown .= "| 🎯 Icône | `{$node['icon']}` |\n";
        }
        
        if (!empty($node['class'])) {
            $markdown .= "| 📦 Classe | `{$node['class']}` |\n";
        }
        
        $markdown .= "\n";
        
        return $markdown;
    }

    protected function formatTransitionSection(array $edge, array $options): string
    {
        $fromLabel = $this->getNodeLabel($edge['from'], $options['_parsed_data'] ?? []);
        $toLabel = $this->getNodeLabel($edge['to'], $options['_parsed_data'] ?? []);
        
        $markdown = "### {$edge['label']}\n\n";
        $markdown .= "**{$fromLabel}** → **{$toLabel}**\n\n";
        
        if (!empty($edge['description']) && $edge['description'] !== $edge['label']) {
            $markdown .= "{$edge['description']}\n\n";
        }
        
        $markdown .= "| Propriété | Valeur |\n";
        $markdown .= "|-----------|--------|\n";
        $markdown .= "| 📛 Nom | `{$edge['name']}` |\n";
        $markdown .= "| 🏷️ Label | {$edge['label']} |\n";
        $markdown .= "| ⬅️ État source | {$fromLabel} (`{$edge['from']}`) |\n";
        $markdown .= "| ➡️ État cible | {$toLabel} (`{$edge['to']}`) |\n";
        
        if (!empty($edge['class'])) {
            $markdown .= "| 📦 Classe | `{$edge['class']}` |\n";
        }
        
        if (!empty($edge['style']) && $edge['style'] !== 'normal') {
            $markdown .= "| 🎨 Style | {$edge['style']} |\n";
        }
        
        $markdown .= "\n";
        
        return $markdown;
    }

    protected function formatMermaidSection(array $data, array $options): string
    {
        $markdown = "## 📈 Diagramme Mermaid\n\n";
        $markdown .= "```mermaid\n";
        
        // Générer la syntaxe Mermaid basique
        $type = $data['metadata']['type'] ?? 'flowchart';
        $direction = $data['metadata']['direction'] ?? 'LR';
        
        $markdown .= "{$type} {$direction}\n";
        
        // Ajouter les nœuds
        foreach ($data['nodes'] ?? [] as $node) {
            $label = $node['label'];
            if (!empty($node['description']) && $node['description'] !== $label) {
                $label .= "<br/>" . $node['description'];
            }
            $markdown .= "    {$node['id']}[\"{$label}\"]\n";
        }
        
        $markdown .= "\n";
        
        // Ajouter les edges
        foreach ($data['edges'] ?? [] as $edge) {
            $label = $edge['label'] ? "|\"{$edge['label']}\"" : "";
            $markdown .= "    {$edge['from']} -->{$label}| {$edge['to']}\n";
        }
        
        $markdown .= "```\n\n";
        
        return $markdown;
    }

    protected function formatMetadataSection(array $metadata, array $options): string
    {
        $markdown = "## ℹ️ Métadonnées\n\n";
        $markdown .= "| Propriété | Valeur |\n";
        $markdown .= "|-----------|--------|\n";
        
        foreach ($metadata as $key => $value) {
            if (is_scalar($value)) {
                $displayKey = ucfirst(str_replace('_', ' ', $key));
                $markdown .= "| {$displayKey} | `{$value}` |\n";
            }
        }
        
        $markdown .= "\n";
        
        return $markdown;
    }

    protected function getNodeLabel(string $nodeId, array $parsedData): string
    {
        foreach ($parsedData['nodes'] ?? [] as $node) {
            if ($node['id'] === $nodeId) {
                return $node['label'];
            }
        }
        return ucfirst($nodeId);
    }

    public function validateOptions(array $options): bool
    {
        if (isset($options['include_mermaid_diagram']) && !is_bool($options['include_mermaid_diagram'])) {
            return false;
        }
        
        if (isset($options['include_metadata']) && !is_bool($options['include_metadata'])) {
            return false;
        }
        
        return true;
    }

    public function getDefaultOptions(): array
    {
        return [
            'include_mermaid_diagram' => true,
            'include_metadata' => true,
            'include_state_details' => true,
            'include_transition_details' => true,
        ];
    }
}