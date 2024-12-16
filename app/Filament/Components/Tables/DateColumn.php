<?php

namespace App\Filament\Components\Tables;

use Filament\Tables\Columns\TextColumn;


class DateColumn extends TextColumn
{

    /**
     * Crée une nouvelle instance de la colonne avec les propriétés par défaut.
     *
     * @param string $name
     * @return static
     */
    public static function make(string $name): static
    {
        return parent::make($name)
            ->sortable() // Ajoute le tri par défaut
            ->dateTime('d/m/y'); // Définit le format par défaut
    }
   
}
