<?php

namespace App\Filament\Utils;


use Filament\Actions\Action;
use Filament\Forms;


class StateUtils
{
    /**
     * Crée une action de sauvegarde d'état.
     *
     * @param  string  $resource  La classe de la ressource utilisée
     * @return Action
     */
    public static function getStateSaveButton(): Action
    {
        return Action::make('save')
            ->label(__('Sauver'))
            ->submit('save')
            ->keyBindings(['mod+s'])->icon('far-floppy-disk')->hidden(fn($record) => $record?->state?->isSaveHidden);
    }
}
