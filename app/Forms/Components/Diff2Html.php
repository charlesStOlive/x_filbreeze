<?php

namespace App\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;

class Diff2Html extends Field
{
    protected string $view = 'forms.components.diff2-html';

    protected array|Closure $version1 = [];
    protected array|Closure $version2 = [];

    /**
     * Définit la version 1 pour la comparaison
     */
    public function version1(array|Closure $json): static
    {
        $this->version1 = $json;

        return $this;
    }

    /**
     * Définit la version 2 pour la comparaison
     */
    public function version2(array|Closure $json): static
    {
        $this->version2 = $json;

        return $this;
    }

    /**
     * Évalue et retourne la version 1
     */
    public function getVersion1()
    {
        $result = $this->evaluate($this->version1);
        return $result;
    }

    /**
     * Évalue et retourne la version 2
     */
    public function getVersion2()
    {
        $result = $this->evaluate($this->version2);
        return $result;
    }
}
