<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Contracts;

/**
 * Defines a strategy for sorting model states.
 */
interface StateSortingStrategy
{
    /**
     * Applies configuration to the strategy.
     */
    public function withConfig(Config $config): self;

    /**
     * Compares two states for sorting.
     */
    public function compare(State $a, State $b): int;
}
