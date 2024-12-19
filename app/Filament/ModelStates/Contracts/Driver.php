<?php

declare(strict_types=1);

namespace App\Filament\ModelStates\Contracts;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Collection;
use App\Filament\ModelStates\Operator;

/**
 * Manages state transitions and validation for models.
 */
interface Driver
{
    /**
     * Get the current state of the model.
     */
    public function currentState(Config $config): State;

    /**
     * Get the default state of the model.
     */
    public function defaultState(Config $config): ?State;

    /**
     * Get all possible states for the model.
     *
     * @return Collection<string, State>
     */
    public function allStates(Config $config): Collection;

    /**
     * Transform a mixed value into a state instance.
     */
    public function transformState(Config $config, mixed $state): State;

    /**
     * Get the transition instance for a given pending transition.
     */
    public function getTransition(Config $config, PendingTransition $pendingTransition): Transition;

    /**
     * Check if a pending transition is valid.
     */
    public function isValidPendingTransition(Config $config, PendingTransition $pendingTransition): bool;

    /**
     * Check if a pending transition is invalid.
     */
    public function isInvalidPendingTransition(Config $config, PendingTransition $pendingTransition): bool;

    /**
     * Execute a pending transition.
     */
    public function executePendingTransition(Config $config, PendingTransition $pendingTransition): void;

    /**
     * Apply a scope to a query based on the given states and operator.
     *
     * @param  array<array-key, State>|State  $states
     */
    public function scope(Config $config, State | array $states, Operator $operator): Scope;

    /**
     * Get the validation rule for a state attribute.
     */
    public function validationRule(Config $config, bool $required = true): ValidationRule;
}
