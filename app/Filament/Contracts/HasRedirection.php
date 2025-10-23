<?php

namespace App\Filament\Contracts;

use Illuminate\Database\Eloquent\Model;

interface HasRedirection
{
    /**
     * Get the redirection URL after the state transition
     * 
     * @param Model $record The model that underwent the transition
     * @return string|null The URL to redirect to, or null to stay on current page
     */
    public function getRedirectUrl(Model $record): ?string;
}