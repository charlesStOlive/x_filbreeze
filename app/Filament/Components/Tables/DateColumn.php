<?php

namespace App\Filament\Components\Tables;

use Filament\Tables\Columns\TextColumn;


class DateColumn extends TextColumn
{

    /**
     * Crée une nouvelle instance de la colonne avec les propriétés par défaut.
     *
     * @param string|null $name
     * @return static
     */
    public static function make(?string $name = null): static
    {
        return parent::make($name)
            ->sortable() // Ajoute le tri par défaut
            ->dateTime('d/m/y'); // Définit le format par défaut
    }

    /**
     * Une date métier (émission, paiement, validation) : affichée telle qu'elle a été saisie, sans
     * passer au fuseau de l'utilisateur (voir App\Support\BusinessDate).
     */
    public function businessDate(): static
    {
        return $this->timezone((string) config('app.timezone'));
    }
   
}
