<?php

namespace App\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;

use Filament\Forms\Components\Repeater;
use Illuminate\Contracts\Support\Htmlable;

class NotilacRepeater extends Repeater
{
    protected string $view = 'forms.components.notilac-repeater';

    protected string | Closure | null $itemColor = null;

    
    public function itemColor(string | Closure | null $color): static
    {
        $this->itemColor = $color;

        return $this;
    }

    
    public function getItemColor(string $uuid): string | Htmlable | null
    {
        $container = $this->getChildComponentContainer($uuid);

        return $this->evaluate($this->itemColor, [
            'container' => $container,
            'state' => $container->getRawState(),
            'uuid' => $uuid,
        ]);
    }
}
