<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Contracts;

use Closure;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Represents a transition between states with additional properties.
 */
interface Transition extends HasColor, HasDescription, HasIcon, HasLabel
{
    /**
     * Get the form components or a closure that returns them for this transition.
     *
     * @return null|array<\Filament\Schemas\Components\Component>|Closure
     */
    public function form(): array | Closure | null;
}
