<?php

declare(strict_types=1);

namespace App\Filament\ModelStates;

use Closure;
use Filament\Tables\Grouping\Group;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use App\Filament\ModelStates\Concerns\HasDriver;
use App\Filament\ModelStates\Contracts\Config;
use Override;
use Webmozart\Assert\Assert;

final class StateGroup extends Group
{
    use HasDriver;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->getTitleFromRecordUsing(function (Model $record): ?string {
            $state = Arr::get($record, $this->getColumn());

            if ($state === null) {
                return null;
            }

            return $this->stateConfig($this->stateConfigClosure($record))
                ->getStateDriver()
                ->transformState($this->getStateConfig(), $state)
                ->label();
        });

        $this->getDescriptionFromRecordUsing(function (Model $record): ?string {
            $state = Arr::get($record, $this->getColumn());

            if ($state === null) {
                return null;
            }

            return $this->stateConfig($this->stateConfigClosure($record))
                ->getStateDriver()
                ->transformState($this->getStateConfig(), $state)
                ->getDescription();
        });
    }

    private function stateConfigClosure(Model $record): Closure
    {
        return function () use ($record): Config {
            if ($this->getRelationship($record) === null) {
                return new GenericConfig($record, $this->getColumn());
            }

            $relationshipName = $this->getRelationshipName();
            Assert::string($relationshipName);

            $model = $record->newQuery()->getRelation($relationshipName)->getQuery()->getModel();

            return new GenericConfig($model, $this->getRelationshipAttribute());
        };
    }
}
