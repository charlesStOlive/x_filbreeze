<?php

namespace App\Enums;

enum ProductType: string
{
    case JOURS = 'jours';
    case HEURES = 'heures';
    case FORFAIT_U = 'forfait_u';
    case FORFAIT_M = 'forfait_m';
    case FORFAIT_A = 'forfait_a';
    case REMISE = 'remise';

    public function label(): string
    {
        return match ($this) {
            self::JOURS => 'Par jour',
            self::HEURES => 'Par heure',
            self::FORFAIT_U => 'Forfait unitaire',
            self::FORFAIT_M => 'Forfait mensuel',
            self::FORFAIT_A => 'Forfait annuel',
            self::REMISE => 'Remise',
        };
    }

    public function unitLabel(): ?string
    {
        return match ($this) {
            self::HEURES => 'heures',
            self::JOURS => 'jours',
            self::FORFAIT_M => 'forfait mensuel',
            self::FORFAIT_A => 'forfait annuel',
            self::FORFAIT_U => 'forfait',
            default => null,
        };
    }

    public function formQtyLabel(): ?string
    {
        return match ($this) {
            self::HEURES => 'Nombre d’heures',
            self::JOURS => 'Nombre de jours',
            default => null,
        };
    }

    public function formCuLabel(): ?string
    {
        return match ($this) {
            self::HEURES => 'Coût par heure',
            self::JOURS => 'Coût par jour',
            self::FORFAIT_M, self::FORFAIT_A, self::FORFAIT_U => 'Montant forfaitaire',
            default => null,
        };
    }

    public function suffix(): ?string
    {
        return match ($this) {
            self::HEURES => 'h',
            self::JOURS => 'j',
            self::FORFAIT_M => '/mois',
            self::FORFAIT_A => '/an',
            default => null,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
