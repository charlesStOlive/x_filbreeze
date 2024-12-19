<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Concerns;

use Illuminate\Support\Collection;
use App\Filament\ModelStates\Contracts\State;
use App\Filament\ModelStates\GenericPendingTransition;

/**
 * @internal
 */
trait SelectsState
{
    use HasDriver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->default(fn (): ?State => $this->getStateDriver()
            ->defaultState($this->getStateConfig()));

        $this->options(fn (): Collection => $this->getStateDriver()
            ->allStates($this->getStateConfig())
            ->map(static fn (State $state): string => $state->label()));

        $this->disableOptionWhen(function (string $value): bool {
            $config = $this->getStateConfig();
            $subject = $this->getStateDriver()->transformState($config, $value);

            if ($this->getRecord() === null) {
                return (bool) $this->getStateDriver()
                    ->defaultState($config)
                    ?->notEquals($subject);
            }

            $state = $this->getStateDriver()
                ->transformState($config, $this->getRecord()->getAttributeValue($this->getName()));

            if ($state->equals($subject)) {
                return false;
            }

            return $this->getStateDriver()
                ->isInvalidPendingTransition($config, new GenericPendingTransition($state, $subject));
        });
    }
}
