<?php

namespace App\Filament\Infolists\Components;

use Closure;
use Filament\Infolists\Components\Entry;

class MermaidDiagramEntry extends Entry
{
    protected string $view = 'filament.infolists.components.mermaid-diagram-entry';

    protected string | Closure | null $modelClass = null;
    
    protected string | Closure | null $height = '400px';
    
    protected string | Closure | null $theme = 'default';
    
    protected string | Closure | null $type = 'flowchart';
    
    protected string | Closure | null $direction = 'LR';
    
    protected bool | Closure $lazy = true;

    /**
     * Set the model class for state analysis
     */
    public function modelClass(string | Closure | null $modelClass): static
    {
        $this->modelClass = $modelClass;

        return $this;
    }

    /**
     * Get the model class
     */
    public function getModelClass(): ?string
    {
        return $this->evaluate($this->modelClass);
    }

    /**
     * Set the diagram height
     */
    public function height(string | Closure | null $height): static
    {
        $this->height = $height;

        return $this;
    }

    /**
     * Get the diagram height
     */
    public function getHeight(): ?string
    {
        return $this->evaluate($this->height);
    }

    /**
     * Set the Mermaid theme
     */
    public function theme(string | Closure | null $theme): static
    {
        $this->theme = $theme;

        return $this;
    }

    /**
     * Get the Mermaid theme
     */
    public function getTheme(): ?string
    {
        return $this->evaluate($this->theme);
    }

    /**
     * Set the diagram type
     */
    public function type(string | Closure | null $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get the diagram type
     */
    public function getType(): ?string
    {
        return $this->evaluate($this->type);
    }

    /**
     * Set the diagram direction
     */
    public function direction(string | Closure | null $direction): static
    {
        $this->direction = $direction;

        return $this;
    }

    /**
     * Get the diagram direction
     */
    public function getDirection(): ?string
    {
        return $this->evaluate($this->direction);
    }

    /**
     * Enable/disable lazy loading
     */
    public function lazy(bool | Closure $lazy = true): static
    {
        $this->lazy = $lazy;

        return $this;
    }

    /**
     * Check if lazy loading is enabled
     */
    public function isLazy(): bool
    {
        return $this->evaluate($this->lazy);
    }

    /**
     * Get the API endpoint for Mermaid JSON data
     */
    public function getApiEndpoint(): string
    {
        $modelClass = $this->getModelClass();
        
        if (!$modelClass) {
            return '';
        }

        // Extract model name from full class path
        $modelName = class_basename($modelClass);
        
        try {
            return route('api.states.mermaid-json', ['model' => $modelName]);
        } catch (\Exception $e) {
            // Fallback URL construction if route is not available
            return url("/api/states/{$modelName}/mermaid-json");
        }
    }

    /**
     * Get the unique ID for this diagram instance
     */
    public function getDiagramId(): string
    {
        return 'mermaid-diagram-' . md5($this->getStatePath() . ($this->getModelClass() ?? 'default'));
    }

    /**
     * Get state value (can be used to pass context or data)
     */
    public function getState(): mixed
    {
        // Return model class or any other state data needed
        return $this->getModelClass();
    }
}
