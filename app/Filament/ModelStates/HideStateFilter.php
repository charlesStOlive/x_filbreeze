<?php

declare(strict_types=1);

namespace App\Filament\ModelStates;

use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use App\Filament\ModelStates\Concerns\HasAttribute;
use App\Filament\ModelStates\Concerns\HasDriver;
use App\Filament\ModelStates\Contracts\Config;
use App\Filament\ModelStates\Contracts\State;
use Override;

final class HideStateFilter extends Filter
{
    use HasAttribute;
    use HasDriver;

    private mixed $hiddenState;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->stateConfig(fn (Table $table): Config => new GenericConfig(
            $table->getModel(),
            $this->getAttribute(),
        ));

        $this->toggle();

        $this->label(fn (): string => __('model-states-for-filament::labels.hide_state', [
            'state' => Str::lower($this->getHiddenState()->label()),
        ]));

        $this->query(function (Builder $query): Builder {
            $this->getStateDriver()
                ->scope($this->getStateConfig(), $this->getHiddenState(), Operator::NotIn)
                ->apply($query, $query->getModel());

            return $query;
        });
    }

    #[Override]
    public static function getDefaultName(): ?string
    {
        return 'hide_state';
    }

    public function hiddenState(mixed $hiddenState): self
    {
        $this->hiddenState = $hiddenState;

        return $this;
    }

    private function getHiddenState(): State
    {
        return $this->getStateDriver()
            ->transformState($this->getStateConfig(), $this->evaluate($this->hiddenState));
    }
}
