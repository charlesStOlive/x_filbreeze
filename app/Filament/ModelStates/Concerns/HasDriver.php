<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Concerns;

use Closure;
use App\Filament\ModelStates\Contracts\Config;
use App\Filament\ModelStates\Contracts\Driver;
use App\Filament\ModelStates\Facades\StateManager;

/**
 * @internal
 */
trait HasDriver
{
    private Config | Closure $stateConfig;

    private ?string $stateDriver = null;

    public function stateDriver(string $stateDriver): self
    {
        $this->stateDriver = $stateDriver;

        return $this;
    }

    private function getStateDriver(): Driver
    {
        return StateManager::driver($this->stateDriver);
    }

    private function stateConfig(Config | Closure $stateConfig): self
    {
        $this->stateConfig = $stateConfig;

        return $this;
    }

    private function getStateConfig(): Config
    {
        return $this->evaluate($this->stateConfig);
    }
}
