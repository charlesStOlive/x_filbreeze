<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Provides a contract for configuring model states.
 */
interface Config
{
    /**
     * Get the model associated with the state.
     */
    public function model(): Model;

    /**
     * Get the attribute name used to store the state.
     */
    public function attribute(): string;
}
