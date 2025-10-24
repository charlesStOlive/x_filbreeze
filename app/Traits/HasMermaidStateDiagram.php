<?php

namespace App\Traits;

use App\Facades\StateAnalysis;

trait HasMermaidStateDiagram
{
    /**
     * Générer les données Mermaid pour ce modèle (structure array)
     */
    public function getMermaidData(array $options = []): array
    {
        return StateAnalysis::analyze(static::class, 'json', array_merge([
            'format' => 'mermaid',
            'pretty_print' => false,
            'include_metadata' => true
        ], $options));
    }

    /**
     * Générer directement le diagramme Mermaid (syntaxe string)
     */
    public function getMermaidDiagram(array $options = []): string
    {
        return StateAnalysis::toMermaid(static::class, $options);
    }
}