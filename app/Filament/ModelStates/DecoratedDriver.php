<?php

declare(strict_types=1);

namespace App\Filament\ModelStates;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Collection;
use App\Filament\ModelStates\Contracts\Config;
use App\Filament\ModelStates\Contracts\Driver;
use App\Filament\ModelStates\Contracts\PendingTransition;
use App\Filament\ModelStates\Contracts\State;
use App\Filament\ModelStates\Contracts\StateSortingStrategy;
use App\Filament\ModelStates\Contracts\Transition;
use Override;

final class DecoratedDriver implements Driver
{
    public function __construct(
        private readonly Driver $origin,
        private readonly StateSortingStrategy $stateSortingStrategy,
    ) {}

    #[Override]
    public function currentState(Config $config): State
    {
        return $this->origin->currentState($config);
    }

    #[Override]
    public function defaultState(Config $config): ?State
    {
        return $this->origin->defaultState($config);
    }

    #[Override]
    public function allStates(Config $config): Collection
    {
        return $this->origin->allStates($config)->sort(
            $this->stateSortingStrategy->withConfig($config)->compare(...),
        );
    }

    #[Override]
    public function transformState(Config $config, mixed $state): State
    {
        return $this->origin->transformState($config, $state);
    }

    #[Override]
    public function getTransition(Config $config, PendingTransition $pendingTransition): Transition
    {
        return $this->origin->getTransition($config, $pendingTransition);
    }

    #[Override]
    public function isValidPendingTransition(Config $config, PendingTransition $pendingTransition): bool
    {
        return $this->origin->isValidPendingTransition($config, $pendingTransition);
    }

    #[Override]
    public function isInvalidPendingTransition(Config $config, PendingTransition $pendingTransition): bool
    {
        return $this->origin->isInvalidPendingTransition($config, $pendingTransition);
    }

    #[Override]
    public function executePendingTransition(Config $config, PendingTransition $pendingTransition): void
    {
        $this->origin->executePendingTransition($config, $pendingTransition);
    }

    #[Override]
    public function scope(Config $config, State | array $states, Operator $operator): Scope
    {
        return $this->origin->scope($config, $states, $operator);
    }

    #[Override]
    public function validationRule(Config $config, bool $required = true): ValidationRule
    {
        return $this->origin->validationRule($config, $required);
    }

    public function unwrap(): Driver
    {
        return $this->origin;
    }
}
