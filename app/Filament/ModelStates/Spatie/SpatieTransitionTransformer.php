<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Spatie;

use InvalidArgumentException;
use App\Filament\ModelStates\Contracts\FilamentSpatieTransition;
use App\Filament\ModelStates\Contracts\PendingTransition;
use Spatie\ModelStates\DefaultTransition;
use Spatie\ModelStates\Transition as SpatieTransition;
use Webmozart\Assert\Assert;

/**
 * @internal
 */
final class SpatieTransitionTransformer
{
    public function __construct(
        private readonly SpatieConfig $config,
    ) {}

    public function transform(mixed $transition): SpatieTransitionAdapter
    {
        Assert::isInstanceOf($transition, PendingTransition::class);

        $transition = new SpatiePendingTransition($transition);

        $fromMorphClass = $transition->from()->value();
        $toMorphClass = $transition->to()->value();

        if (! $this->config->stateConfig()->isTransitionAllowed($fromMorphClass, $toMorphClass)) {
            throw new InvalidArgumentException("Transition form state [{$fromMorphClass}] to state [{$toMorphClass}] is not allowed.");
        }

        $defaultTransitionClass = config('model-states.default_transition', DefaultTransition::class);
        $customTransitionClass = $this->config->stateConfig()->resolveTransitionClass($fromMorphClass, $toMorphClass);
        $transitionClass = \is_string($customTransitionClass) ? $customTransitionClass : $defaultTransitionClass;

        if (\is_string($transitionClass) && is_a($transitionClass, DefaultTransition::class, true)) {
            return new SpatieTransitionAdapter(
                new DefaultTransition(
                    $this->config->model(),
                    $this->config->attribute(),
                    $transition->to()->unwrap(),
                ),
                $transition,
            );
        }
        if (\is_string($transitionClass) && is_a($transitionClass, FilamentSpatieTransition::class, true)) {
            return new SpatieTransitionAdapter(
                $transitionClass::fill(
                    $this->config->model(),
                    $transition->formData(),
                ),
                $transition,
            );
        }
        if (\is_string($transitionClass) && is_a($transitionClass, SpatieTransition::class, true)) {
            return new SpatieTransitionAdapter(
                new $transitionClass($this->config->model()),
                $transition,
            );
        }

        throw new InvalidArgumentException('Unable to transform transition.');
    }
}
