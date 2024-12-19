<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \App\Filament\ModelStates\Contracts\Driver driver(?string $driver = null)
 * @method static \App\Filament\ModelStates\StateManager extend(string $driver, \Closure $callback)
 *
 * @see \App\Filament\ModelStates\StateManager
 */
final class StateManager extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \App\Filament\ModelStates\StateManager::class;
    }
}
