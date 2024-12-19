<?php

declare(strict_types=1);

namespace App\Filament\ModelStates;

use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use App\Filament\ModelStates\Concerns\HasDriver;
use App\Filament\ModelStates\Contracts\Config;
use App\Filament\ModelStates\Contracts\State;
use Override;

final class StateSelectFilter extends SelectFilter
{
    use HasDriver;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->stateConfig(fn (Table $table): Config => new GenericConfig(
            $table->getModel(),
            $this->getAttribute(),
        ));

        $this->options(fn (): Collection => $this->getStateDriver()
            ->allStates($this->getStateConfig())
            ->map(static fn (State $state): string => $state->label()));

        $this->getOptionLabelFromRecordUsing(fn (Model $model): string => $this->getStateDriver()
            ->transformState($this->getStateConfig(), Arr::get($model, $this->getRelationshipTitleAttribute()))
            ->label());
    }
}
