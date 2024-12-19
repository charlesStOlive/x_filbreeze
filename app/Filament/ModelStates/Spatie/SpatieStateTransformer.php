<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Spatie;

use InvalidArgumentException;
use App\Filament\ModelStates\Contracts\State;
use Spatie\ModelStates\Exceptions\InvalidConfig;
use Spatie\ModelStates\State as SpatieState;

/**
 * @internal
 */
final class SpatieStateTransformer
{
    public function __construct(
        private readonly SpatieConfig $config,
    ) {}

    public function transform(mixed $state): State
    {
        try {
            return match (true) {
                \is_object($state) && is_a($state, SpatieState::class) => new SpatieStateAdapter($state),
                \is_object($state) && is_a($state, State::class) => $state,
                \is_string($state) => new SpatieStateAdapter(
                    $this->config->stateConfig()->baseStateClass::make($state, $this->config->model()),
                ),
                default => throw new InvalidArgumentException('Unable to transform state.'),
            };
        } catch (InvalidConfig $exception) {
            throw new InvalidArgumentException('Unable to transform state.', previous: $exception);
        }
    }
}
