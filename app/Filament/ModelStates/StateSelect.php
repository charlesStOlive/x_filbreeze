<?php

declare(strict_types=1);

namespace App\Filament\ModelStates;

use Filament\Forms\Components\Select;
use Illuminate\Contracts\Validation\ValidationRule;
use LogicException;
use App\Filament\ModelStates\Concerns\SelectsState;
use App\Filament\ModelStates\Contracts\Config;
use Override;

final class StateSelect extends Select
{
    use SelectsState {
        setUp as traitSetup;
    }

    #[Override]
    protected function setUp(): void
    {
        $this->traitSetup();

        $this->stateConfig(fn (): Config => new GenericConfig(
            $this->getRecord() ?? $this->getModelInstance() ?? throw new LogicException('Unable to retrieve model.'),
            $this->getName(),
        ));

        $this->rule(fn (): ValidationRule => $this->getStateDriver()
            ->validationRule($this->getStateConfig(), $this->isRequired()));

        $this->formatStateUsing(fn (mixed $state): string => $this->getStateDriver()
            ->transformState($this->getStateConfig(), $state)
            ->value());

        $this->selectablePlaceholder(fn (): bool => ! $this->isRequired());
    }
}
