<?php

namespace App\Enums;

enum CompanyType
{
    // Entreprises privées
    case AUTO_ENTREPRENEUR;
    case EI;
    case EURL;
    case SARL;
    case SAS;
    case SASU;
    case SA;
    case SNC;
    case SCOP;
    case SELARL;

    // Secteur associatif et fondations
    case ASSOCIATION;
    case FONDATION;
    case ONG;

    // Secteur public & parapublic
    case COMMUNE;
    case DEPARTEMENT;
    case REGION;
    case EPCI;
    case ETABLISSEMENT_PUBLIC;

    // Autres
    case COOPERATIVE;
    case AUTRE;
    case INC;

    public function label(): string
    {
        return match ($this) {
            self::AUTO_ENTREPRENEUR => 'Auto-entrepreneur',
            self::EI => 'Entreprise individuelle',
            self::EURL => 'E.U.R.L',
            self::SARL => 'S.A.R.L',
            self::SAS => 'S.A.S',
            self::SASU => 'S.A.S.U',
            self::SA => 'S.A',
            self::SNC => 'S.N.C',
            self::SCOP => 'SCOP',
            self::SELARL => 'SELARL',

            self::ASSOCIATION => 'Association',
            self::FONDATION => 'Fondation',
            self::ONG => 'ONG / Organisation internationale',

            self::COMMUNE => 'Commune',
            self::DEPARTEMENT => 'Département',
            self::REGION => 'Région',
            self::EPCI => 'EPCI (intercommunalité)',
            self::ETABLISSEMENT_PUBLIC => 'Établissement public',

            self::COOPERATIVE => 'Coopérative',
            self::AUTRE => 'Autre',
            self::INC => 'Inconnu',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::AUTO_ENTREPRENEUR,
            self::EI,
            self::EURL,
            self::SARL,
            self::SAS,
            self::SASU,
            self::SA,
            self::SNC,
            self::SCOP,
            self::SELARL => 'Entreprise privée',

            self::ASSOCIATION,
            self::FONDATION,
            self::ONG => 'Secteur associatif',

            self::COMMUNE,
            self::DEPARTEMENT,
            self::REGION,
            self::EPCI,
            self::ETABLISSEMENT_PUBLIC => 'Collectivité / Public',

            self::COOPERATIVE,
            self::AUTRE => 'Autre',

            self::INC=> 'Non renseigné',
        };
    }
}
