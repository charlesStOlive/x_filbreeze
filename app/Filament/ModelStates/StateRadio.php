<?php

declare(strict_types=1);

namespace App\Filament\ModelStates;

use Filament\Forms\Components\Radio;
use Illuminate\Contracts\Validation\ValidationRule;
use LogicException;
use App\Filament\ModelStates\Concerns\SelectsState;
use App\Filament\ModelStates\Contracts\Config;
use App\Filament\ModelStates\Contracts\State;
use Override;

final class StateRadio extends Radio
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

        $this->descriptions(fn (): array => $this->getStateDriver()
            ->allStates($this->getStateConfig())
            ->map(static fn (State $state): ?string => $state->getDescription())
            ->all());
    }
}
