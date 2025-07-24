<?php

namespace App\Enums;

enum Country: string
{
    case France = 'FR';
    case Algeria = 'DZ';
    case Australia = 'AU';
    case Belgium = 'BE';
    case Cambodia = 'KH';
    case Cameroon = 'CM';
    case Canada = 'CA';
    case China = 'CN';
    case Germany = 'DE';
    case IvoryCoast = 'CI';
    case Italy = 'IT';
    case Japan = 'JP';
    case Luxembourg = 'LU';
    case Madagascar = 'MG';
    case Morocco = 'MA';
    case Netherlands = 'NL';
    case Portugal = 'PT';
    case Senegal = 'SN';
    case Spain = 'ES';
    case Switzerland = 'CH';
    case Thailand = 'TH';
    case Tunisia = 'TN';
    case UnitedKingdom = 'GB';
    case UnitedStates = 'US';

    public function label(): string
    {
        return match ($this) {
            self::France => 'France',
            self::Belgium => 'Belgique',
            self::Switzerland => 'Suisse',
            self::Canada => 'Canada',
            self::UnitedStates => 'États-Unis',
            self::UnitedKingdom => 'Royaume-Uni',
            self::Germany => 'Allemagne',
            self::Spain => 'Espagne',
            self::Italy => 'Italie',
            self::Netherlands => 'Pays-Bas',
            self::Portugal => 'Portugal',
            self::Luxembourg => 'Luxembourg',
            self::Morocco => 'Maroc',
            self::Algeria => 'Algérie',
            self::Tunisia => 'Tunisie',
            self::Senegal => 'Sénégal',
            self::IvoryCoast => 'Côte d’Ivoire',
            self::Cameroon => 'Cameroun',
            self::Madagascar => 'Madagascar',
            self::Australia => 'Australie',
            self::Japan => 'Japon',
            self::China => 'Chine',
            self::Cambodia => 'Cambodge',
            self::Thailand => 'Thaïlande',
        };
    }

    public static function options(): array
    {
        $cases = collect(self::cases())
            ->sortBy(fn(self $country) => $country === self::France ? '0' : $country->label())
            ->values();

        return $cases->mapWithKeys(fn(self $country) => [
            $country->value => $country->label(),
        ])->all();
    }
}
