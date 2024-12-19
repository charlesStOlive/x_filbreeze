<?php

namespace App\Filament\Utils;


use Filament\Actions\Action;
use Filament\Forms;


class StateUtils
{
    /**
     * Crée une action pour corriger les textes via Mistral IA.
     *
     * @param  string  $resource  La classe de la ressource utilisée
     * @return Action
     */
    public static function getStateSaveButton(): Action
    {
        return Action::make('save')
            ->label(__('filament-panels::pages/tenancy/edit-tenant-profile.form.actions.save.label'))
            ->submit('save')
            ->keyBindings(['mod+s'])->icon('far-floppy-disk')->hidden(fn($record) => $record->state->isSaveHidden);
    }
    

    

    

    
}
