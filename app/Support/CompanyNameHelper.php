<?php

namespace App\Support;

class CompanyNameHelper
{
    /**
     * Supprime les formes juridiques du nom de l'entreprise
     */
    public static function removeCompanyLegalForms(string $name): string
    {
        // Liste exhaustive des formes juridiques internationales
        $legalForms = [
            // France
            'SARL',
            'S.A.R.L.',
            'SAS',
            'S.A.S.',
            'SASU',
            'S.A.S.U.',
            'EURL',
            'E.U.R.L.',
            'SA',
            'S.A.',
            'SCI',
            'S.C.I.',
            'SNC',
            'S.N.C.',
            'SELARL',
            'SELAS',
            'SEL',
            'SELAFA',
            'EI',
            'EIRL',
            'GAEC',
            'EARL',
            'GIE',
            'SCM',
            'SCP',

            // UK / International anglophone
            'LTD',
            'LIMITED',
            'PLC',
            'LLP',
            'LP',

            // USA
            'INC',
            'INCORPORATED',
            'LLC',
            'L.L.C.',
            'CORP',
            'CORPORATION',
            'CO',
            'COMPANY',

            // Allemagne
            'GMBH',
            'G.M.B.H.',
            'AG',
            'KG',
            'OHG',

            // Espagne
            'SL',
            'S.L.',
            'SRL',
            'S.R.L.',

            // Italie
            'SPA',
            'S.P.A.',
            'SRL',
            'S.R.L.',

            // Pays-Bas / Belgique
            'BV',
            'B.V.',
            'NV',
            'N.V.',
            'CV',
            'VOF',

            // Suisse
            'SAGL',
            'SUARL',

            // Scandinavie
            'AB',
            'AS',
            'A/S',
            'OY',
            'ASA',

            // Portugal
            'LDA',
            'L.DA',
        ];

        $pattern = '/\b(' . implode('|', array_map(fn($f) => preg_quote($f, '/'), $legalForms)) . ')\b\.?/i';
        $cleaned = trim(preg_replace($pattern, '', $name));

        // Nettoyer les espaces multiples et la ponctuation finale
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        $cleaned = trim($cleaned, ' ,-.');

        return $cleaned;
    }

    /**
     * Extrait les mots-clés significatifs en ignorant les déterminants
     */
    public static function extractSignificantKeywords(string $name): array
    {
        // Déterminants et mots génériques à ignorer
        $stopWords = [
            // Français
            'LE',
            'LA',
            'LES',
            'L\'',
            'UN',
            'UNE',
            'DES',
            'DU',
            'DE',
            'ET',
            'AU',
            'AUX',
            // Anglais
            'THE',
            'A',
            'AN',
            'AND',
            'OF',
            'FOR',
            'IN',
            'TO',
            'AT',
            // Espagnol
            'EL',
            'LA',
            'LOS',
            'LAS',
            'UN',
            'UNA',
            'DEL',
            'DE',
            'Y',
            // Allemand
            'DER',
            'DIE',
            'DAS',
            'DEN',
            'UND',
            // Mots génériques
            'SOCIETE',
            'ENTREPRISE',
            'GROUP',
            'GROUPE',
            'COMPANY',
            'COMPAGNIE',
        ];

        // Nettoyer et découper
        $cleanedName = self::removeCompanyLegalForms($name);
        $words = preg_split('/[\s\-,\.]+/', strtoupper($cleanedName), -1, PREG_SPLIT_NO_EMPTY);

        // Filtrer les mots non significatifs
        $keywords = array_filter($words, function ($word) use ($stopWords) {
            return !in_array($word, $stopWords) && strlen($word) >= 3;
        });

        return array_values($keywords);
    }
}
