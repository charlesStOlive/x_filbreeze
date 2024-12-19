<?php

declare(strict_types=1);

namespace App\Filament\ModelStates;

use Filament\Tables\Columns\TextColumn;
use App\Filament\ModelStates\Concerns\DisplaysState;
use Override;

final class StateColumn extends TextColumn
{
    use DisplaysState {
        setUp as traitSetup;
    }

    #[Override]
    protected function setUp(): void
    {
        $this->traitSetup();

        $this->tooltip(function (mixed $state): ?string {
            return $state === null ? null : $this->getStateDriver()
                ->transformState($this->getStateConfig(), $state)
                ->getDescription();
        });
    }
}
