<?php

namespace App\Support;

use Filament\Forms\Components\DateTimePicker;
use Filament\Support\Facades\FilamentTimezone;

/**
 * Une date métier (émission ou paiement d'une facture, validation d'un devis) : le jour et l'heure
 * que l'utilisateur saisit, gardés tels quels, sans conversion de fuseau.
 *
 * Ce n'est pas un horodatage : c'est elle qui part chez Qonto (`issue_date`, date de paiement, voir
 * `CrmInvoiceQontoService`) et qui range un paiement dans une déclaration (`DeclarationCalculator`),
 * toujours par son jour (`toDateString()`). Convertie en UTC, une date saisie « 5 mars à 0 h 30 »
 * deviendrait le 4 mars.
 *
 * Les dates déjà enregistrées depuis le site le sont ainsi (à l'heure de l'utilisateur) : elles
 * restent justes, sans reprise de données.
 */
final class BusinessDate
{
    /** Le sélecteur d'une date métier : l'heure affichée est celle enregistrée. */
    public static function picker(DateTimePicker $picker): DateTimePicker
    {
        return $picker
            ->timezone((string) config('app.timezone'))
            ->default(fn (): string => self::now());
    }

    /** Maintenant, à l'heure de l'utilisateur, à enregistrer telle quelle. */
    public static function now(): string
    {
        return now(FilamentTimezone::get())->format('Y-m-d H:i:s');
    }
}
