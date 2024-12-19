<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Concerns;

use Illuminate\Database\Eloquent\Model;
use App\Filament\ModelStates\Contracts\Config;
use App\Filament\ModelStates\GenericConfig;
use Webmozart\Assert\Assert;

/**
 * @internal
 */
trait DisplaysState
{
    use HasDriver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stateConfig(function (Model $record): Config {
            if (! $this->hasRelationship($record)) {
                return new GenericConfig($record, $this->getName());
            }

            $relationshipName = $this->getRelationshipName();
            Assert::string($relationshipName);

            $model = $record->getRelationValue($relationshipName);
            Assert::isInstanceOf($model, Model::class);

            return new GenericConfig($model, $this->getRelationshipAttribute());
        });

        $this->formatStateUsing(function (mixed $state): ?string {
            return $state === null ? null : $this->getStateDriver()
                ->transformState($this->getStateConfig(), $state)
                ->label();
        });
    }
}
