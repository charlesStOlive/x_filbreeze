<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Spatie;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Filament\ModelStates\Contracts\State;
use Override;
use Spatie\ModelStates\State as SpatieState;

/**
 * @internal
 */
final class SpatieStateAdapter implements State
{
    /**
     * @param  SpatieState<Model>  $state
     */
    public function __construct(
        private readonly SpatieState $state,
    ) {}

    #[Override]
    public function value(): string
    {
        return $this->state::getMorphClass();
    }

    #[Override]
    public function label(): string
    {
        if (is_a($this->state, HasLabel::class)) {
            $label = $this->state->getLabel();

            if (\is_string($label)) {
                return $label;
            }
        }

        return __(
            Str::of($this->state::class)
                ->classBasename()
                ->replaceLast('State', '')
                ->snake(' ')
                ->title()
                ->toString(),
        );
    }

    #[Override]
    public function equals(State $state): bool
    {
        return $this->state->equals($state->value());
    }

    #[Override]
    public function notEquals(State $state): bool
    {
        return ! $this->equals($state);
    }

    #[Override]
    public function getLabel(): ?string
    {
        return $this->label();
    }

    #[Override]
    public function getColor(): string | array | null
    {
        return is_a($this->state, HasColor::class) ? $this->state->getColor() : null;
    }

    #[Override]
    public function getIcon(): ?string
    {
        return is_a($this->state, HasIcon::class) ? $this->state->getIcon() : null;
    }

    #[Override]
    public function getDescription(): ?string
    {
        return is_a($this->state, HasDescription::class) ? $this->state->getDescription() : null;
    }

    /**
     * @return SpatieState<Model>
     */
    public function unwrap(): SpatieState
    {
        return $this->state;
    }
}
