<?php

declare(strict_types=1);

namespace App\Filament\ModelStates;

use Filament\Actions\Action;
use LogicException;
use App\Filament\ModelStates\Concerns\HasAttribute;
use App\Filament\ModelStates\Concerns\TransitionsState;
use App\Filament\ModelStates\Contracts\Config;
use Override;

final class StateAction extends Action
{
    use HasAttribute;
    use TransitionsState {
        setUp as traitSetUp;
    }

    #[Override]
    protected function setUp(): void
    {
        $this->traitSetUp();

        $this->stateConfig(fn (): Config => new GenericConfig(
            $this->getRecord() ?? $this->getModel() ?? throw new LogicException('Unable to retrieve model.'),
            $this->getAttribute(),
        ));
    }
}
