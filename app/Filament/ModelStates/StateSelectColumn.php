<?php

declare(strict_types=1);

namespace App\Filament\ModelStates;

use Filament\Tables\Columns\SelectColumn;
use LogicException;
use App\Filament\ModelStates\Concerns\SelectsState;
use App\Filament\ModelStates\Contracts\Config;
use Override;

final class StateSelectColumn extends SelectColumn
{
    use SelectsState {
        setUp as traitSetup;
    }

    #[Override]
    protected function setUp(): void
    {
        $this->traitSetup();

        $this->stateConfig(fn (): Config => new GenericConfig(
            $this->getRecord() ?? throw new LogicException('Unable to retrieve model.'),
            $this->getName(),
        ));

        $this->selectablePlaceholder(false);

        $this->rules(fn (): array => [
            $this->getStateDriver()
                ->validationRule($this->getStateConfig()),
        ]);

        $this->getStateUsing(fn (): string => $this->getStateDriver()
            ->currentState($this->getStateConfig())
            ->value());
    }
}
