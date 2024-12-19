<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Contracts;

/**
 * Represents a pending transition between states in a model.
 */
interface PendingTransition
{
    /**
     * Get the initial state before the transition.
     */
    public function from(): State;

    /**
     * Get the target state after the transition.
     */
    public function to(): State;

    /**
     * Get the form data associated with the transition.
     *
     * @return array<string, mixed>
     */
    public function formData(): array;
}
