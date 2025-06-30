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
        return match($this) {
            self::JOURS => 'Par jour',
            self::HEURES => 'Par heure',
            self::FORFAIT_U => 'Forfait unitaire',
            self::FORFAIT_M => 'Forfait mensuel',
            self::FORFAIT_A => 'Forfait annuel',
            self::REMISE => 'Remise',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn($case) => [$case->value => $case->label()])
            ->toArray();
    }
}