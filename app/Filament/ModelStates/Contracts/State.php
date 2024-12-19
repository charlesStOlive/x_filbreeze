<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Contracts;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Represents a state with additional properties.
 */
interface State extends HasColor, HasDescription, HasIcon, HasLabel
{
    /**
     * Get the value of the state.
     */
    public function value(): string;

    /**
     * Get the label of the state.
     */
    public function label(): string;

    /**
     * Determine if this state is equal to another state.
     */
    public function equals(self $state): bool;

    /**
     * Determine if this state is not equal to another state.
     */
    public function notEquals(self $state): bool;
}
