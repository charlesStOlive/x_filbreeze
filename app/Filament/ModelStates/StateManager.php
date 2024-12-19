<?php

declare(strict_types=1);

namespace App\Filament\ModelStates;

use Illuminate\Support\Manager;
use App\Filament\ModelStates\Contracts\Driver;
use App\Filament\ModelStates\Contracts\StateSortingStrategy;
use App\Filament\ModelStates\Spatie\SpatieDriver;
use Override;
use Webmozart\Assert\Assert;

final class StateManager extends Manager
{
    /**
     * @todo Use `$this->config->string(...)` once laravel 10 reached end of life...
     */
    #[Override]
    public function getDefaultDriver(): string
    {
        $driver = $this->config->get('model-states-for-filament.driver', 'spatie');
        Assert::string($driver);

        return $driver;
    }

    /**
     * @param string $driver
     */
    #[Override]
    protected function createDriver(mixed $driver): Driver
    {
        $instance = parent::createDriver($driver);
        Assert::isInstanceOf($instance, Driver::class);

        return new DecoratedDriver(
            $instance,
            $this->getSortingStrategy($driver),
        );
    }

    protected function createSpatieDriver(): Driver
    {
        return new SpatieDriver();
    }

    /**
     * @todo Use `$this->config->string(...)` once laravel 10 reached end of life...
     */
    private function getSortingStrategy(string $driver): StateSortingStrategy
    {
        $strategy = $this->config->get(
            "model-states-for-filament.{$driver}.state_sorting_strategy",
            AlphabeticallyStateSorting::class,
        );

        Assert::string($strategy);
        Assert::isAOf($strategy, StateSortingStrategy::class);

        return new $strategy();
    }
}
