<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Concerns;

use Filament\Actions\MountableAction;
use Filament\Tables\Actions\Action as TableAction;
use App\Filament\ModelStates\Contracts\PendingTransition;
use App\Filament\ModelStates\Contracts\Transition;
use App\Filament\ModelStates\GenericPendingTransition;
use Override;

/**
 * @internal
 */
trait TransitionsState
{
    use HasDriver;

    private mixed $state;

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn (): ?string => $this->getTransition()->getLabel());
        $this->successNotificationTitle(static fn (): string => __('model-states-for-filament::labels.transitioned'));
        $this->when(
            is_a($this, TableAction::class),
            fn (MountableAction $action): MountableAction => $action->icon(
                fn (): string => $this->getTransition()->getIcon() ?? 'heroicon-s-arrow-right-circle',
            ),
            fn (MountableAction $action): MountableAction => $action->icon(
                fn (): ?string => $this->getTransition()->getIcon(),
            ),
        );
        $this->color(fn (): string | array | null => $this->getTransition()->getColor());

        $this->visible(fn (): bool => $this->getStateDriver()
            ->isValidPendingTransition($this->getStateConfig(), $this->getPendingTransition()));

        $this->disabled(fn (): bool => $this->getStateDriver()
            ->isInvalidPendingTransition($this->getStateConfig(), $this->getPendingTransition()));

        $this->closeModalByClickingAway(false);
        $this->requiresConfirmation();

        $this->form(fn (): ?array => $this->evaluate($this->getTransition()->form()));

        $this->action(function (MountableAction $action, array $data): void {
            $this->getStateDriver()
                ->executePendingTransition($this->getStateConfig(), $this->getPendingTransition($data));

            $action->success();
        });
    }

    #[Override]
    public static function getDefaultName(): ?string
    {
        return 'transition';
    }

    public function transitionTo(mixed $state): self
    {
        $this->state = $state;

        return $this;
    }

    private function getTransition(): Transition
    {
        return $this->evaluate(function (): Transition {
            return $this->getStateDriver()
                ->getTransition($this->getStateConfig(), $this->getPendingTransition());
        });
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    private function getPendingTransition(array $formData = []): PendingTransition
    {
        return $this->evaluate(function () use ($formData): PendingTransition {
            $config = $this->getStateConfig();

            return new GenericPendingTransition(
                $this->getStateDriver()->currentState($config),
                $this->getStateDriver()->transformState($config, $this->state),
                $formData,
            );
        });
    }
}
