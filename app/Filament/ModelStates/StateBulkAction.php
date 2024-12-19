<?php

declare(strict_types=1);

namespace App\Filament\ModelStates;

use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use App\Filament\ModelStates\Concerns\HasAttribute;
use App\Filament\ModelStates\Concerns\HasDriver;
use App\Filament\ModelStates\Contracts\Config;
use App\Filament\ModelStates\Contracts\Transition;
use Override;

final class StateBulkAction extends BulkAction
{
    use HasAttribute;
    use HasDriver;

    private Transition $transition;

    private mixed $from;

    private mixed $to;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->stateConfig(fn (Table $table): Config => new GenericConfig($table->getModel(), $this->getAttribute()));
        $this->label(fn (): ?string => $this->getTransition()->getLabel());
        $this->successNotificationTitle(static fn (): string => __('model-states-for-filament::labels.transitioned'));
        $this->icon(fn (): ?string => $this->getTransition()->getIcon());
        $this->color(fn (): string | array | null => $this->getTransition()->getColor());
        $this->closeModalByClickingAway(false);
        $this->requiresConfirmation();

        $this->form(fn (): ?array => $this->evaluate($this->getTransition()->form()));
        $this->action(function (BulkAction $action, Collection $records, array $data): void {
            $records->ensure(Model::class)
                ->each(function (Model $record) use ($data): void {
                    $config = new GenericConfig($record, $this->getAttribute());

                    $driver = $this->getStateDriver();
                    $currentState = $driver->currentState($config);
                    $from = $driver->transformState($config, $this->from);

                    $pendingTransition = new GenericPendingTransition(
                        $currentState,
                        $driver->transformState($config, $this->to),
                        $data,
                    );

                    if ($currentState->equals($from) && $driver->isValidPendingTransition($config, $pendingTransition)) {
                        $driver->executePendingTransition($config, $pendingTransition);
                    }
                });

            $action->success();
        });
    }

    #[Override]
    public static function getDefaultName(): ?string
    {
        return 'bulk_transition';
    }

    public function transition(mixed $from, mixed $to): self
    {
        $this->from = $from;
        $this->to = $to;

        return $this;
    }

    private function getTransition(): Transition
    {
        if (isset($this->transition)) {
            return $this->transition;
        }

        $driver = $this->getStateDriver();
        $config = $this->getStateConfig();

        return $this->transition = $this->evaluate(fn (): Transition => $driver
            ->getTransition($config, new GenericPendingTransition(
                $driver->transformState($config, $this->from),
                $driver->transformState($config, $this->to),
            )));
    }
}
