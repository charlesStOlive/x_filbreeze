<?php

declare(strict_types=1);

namespace App\Filament\ModelStates;

use App\Filament\ModelStates\Contracts\Config;
use App\Filament\ModelStates\Contracts\State;
use App\Filament\ModelStates\Contracts\StateSortingStrategy;
use Override;

final class AlphabeticallyStateSorting implements StateSortingStrategy
{
    #[Override]
    public function withConfig(Config $config): StateSortingStrategy
    {
        return $this;
    }

    #[Override]
    public function compare(State $a, State $b): int
    {
        return $a->label() <=> $b->label();
    }
}
