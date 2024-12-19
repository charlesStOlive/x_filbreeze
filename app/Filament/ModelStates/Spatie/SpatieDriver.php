<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Spatie;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Collection;
use App\Filament\ModelStates\Contracts\Config;
use App\Filament\ModelStates\Contracts\Driver;
use App\Filament\ModelStates\Contracts\PendingTransition;
use App\Filament\ModelStates\Contracts\State;
use App\Filament\ModelStates\Contracts\Transition;
use App\Filament\ModelStates\Operator;
use Override;
use Spatie\ModelStates\Exceptions\ClassDoesNotExtendBaseClass;
use Spatie\ModelStates\Exceptions\InvalidConfig;
use Spatie\ModelStates\Validation\ValidStateRule;

final class SpatieDriver implements Driver
{
    /**
     * @throws InvalidConfig
     */
    #[Override]
    public function currentState(Config $config): State
    {
        return $this->getStateRepository($config)->current();
    }

    /**
     * @throws InvalidConfig
     */
    #[Override]
    public function defaultState(Config $config): ?State
    {
        return $this->getStateRepository($config)->default();
    }

    #[Override]
    public function allStates(Config $config): Collection
    {
        return $this->getStateRepository($config)->all();
    }

    #[Override]
    public function transformState(Config $config, mixed $state): State
    {
        return $this->getStateTransformer($config)->transform($state);
    }

    #[Override]
    public function getTransition(Config $config, PendingTransition $pendingTransition): Transition
    {
        $transformer = new SpatieTransitionTransformer($this->getStateConfig($config));

        return $transformer->transform($pendingTransition);
    }

    #[Override]
    public function isValidPendingTransition(Config $config, PendingTransition $pendingTransition): bool
    {
        return $this->transitionSpecification($config)->isSatisfiedBy($pendingTransition);
    }

    #[Override]
    public function isInvalidPendingTransition(Config $config, PendingTransition $pendingTransition): bool
    {
        return $this->transitionSpecification($config)->not()->isSatisfiedBy($pendingTransition);
    }

    /**
     * @throws ClassDoesNotExtendBaseClass
     */
    #[Override]
    public function executePendingTransition(Config $config, PendingTransition $pendingTransition): void
    {
        $spatieConfig = $this->getStateConfig($config);
        $executor = new SpatiePendingTransitionExecutor($spatieConfig, new SpatieTransitionTransformer($spatieConfig));

        $executor->execute($pendingTransition);
    }

    #[Override]
    public function scope(Config $config, State | array $states, Operator $operator): Scope
    {
        return new SpatieStateScope($states, $operator, $this->getStateConfig($config));
    }

    #[Override]
    public function validationRule(Config $config, bool $required = true): ValidationRule
    {
        return new SpatieStateValidationRule(
            new ValidStateRule($this->getStateConfig($config)->stateConfig()->baseStateClass),
            $required,
        );
    }

    private function getStateConfig(Config $config): SpatieConfig
    {
        return new SpatieConfig($config);
    }

    private function getStateRepository(Config $config): SpatieStateRepository
    {
        return new SpatieStateRepository($this->getStateConfig($config), $this->getStateTransformer($config));
    }

    private function getStateTransformer(Config $config): SpatieStateTransformer
    {
        return new SpatieStateTransformer($this->getStateConfig($config));
    }

    private function transitionSpecification(Config $config): SpatieValidPendingTransitionSpecification
    {
        return new SpatieValidPendingTransitionSpecification($this->getStateConfig($config));
    }
}
