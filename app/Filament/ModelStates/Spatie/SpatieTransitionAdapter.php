<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Spatie;

use Closure;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;
use App\Filament\ModelStates\Contracts\FilamentSpatieTransition;
use App\Filament\ModelStates\Contracts\PendingTransition;
use App\Filament\ModelStates\Contracts\Transition;
use Override;
use Spatie\ModelStates\Transition as SpatieTransition;

/**
 * @internal
 */
final class SpatieTransitionAdapter implements Transition
{
    public function __construct(
        private readonly SpatieTransition $transition,
        private readonly PendingTransition $pendingTransition,
    ) {}

    #[Override]
    public function form(): array | Closure | null
    {
        return is_a($this->transition, FilamentSpatieTransition::class) ? $this->transition->form() : null;
    }

    #[Override]
    public function getColor(): string | array | null
    {
        return is_a($this->transition, HasColor::class) ? $this->transition->getColor() : null;
    }

    #[Override]
    public function getIcon(): ?string
    {
        return is_a($this->transition, HasIcon::class) ? $this->transition->getIcon() : null;
    }

    #[Override]
    public function getLabel(): ?string
    {
        return is_a($this->transition, HasLabel::class)
            ? $this->transition->getLabel()
            : (string) __('labels.transition_to_state', [
                'state' => Str::lower($this->pendingTransition->to()->label()),
            ]);
    }

    #[Override]
    public function getDescription(): ?string
    {
        return is_a($this->transition, HasDescription::class) ? $this->transition->getDescription() : null;
    }

    public function unwrap(): SpatieTransition
    {
        return $this->transition;
    }
}
