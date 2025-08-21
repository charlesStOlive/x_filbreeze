<?php

namespace App\Services;

class LocaleService
{
    /**
     * Liste des fuseaux horaires disponibles
     */
    public static function getTimezones(): array
    {
        $timezones = [];

        foreach (timezone_identifiers_list() as $timezone) {
            // Grouper par continent/région
            $parts = explode('/', $timezone);
            if (count($parts) >= 2) {
                $continent = $parts[0];
                $city = str_replace('_', ' ', $parts[count($parts) - 1]);

                // Filtrer les fuseaux horaires les plus courants
                if (in_array($continent, ['Europe', 'America', 'Asia', 'Africa', 'Australia', 'Pacific'])) {
                    $timezones[$timezone] = $continent . ' - ' . $city;
                }
            }
        }

        // Ajouter UTC en premier
        return array_merge(['UTC' => 'UTC'], $timezones);
    }

    /**
     * Liste des locales disponibles avec pays et langues
     */
    public static function getLocales(): array
    {
        return [
            'fr_FR' => '🇫🇷 Français (France)',
            'en_US' => '🇺🇸 English (United States)',
            'en_GB' => '🇬🇧 English (United Kingdom)',
            'es_ES' => '🇪🇸 Español (España)',
            'es_MX' => '🇲🇽 Español (México)',
            'it_IT' => '🇮🇹 Italiano (Italia)',
            'de_DE' => '🇩🇪 Deutsch (Deutschland)',
            'pt_PT' => '🇵🇹 Português (Portugal)',
            'pt_BR' => '🇧🇷 Português (Brasil)',
            'nl_NL' => '🇳🇱 Nederlands (Nederland)',
            'ru_RU' => '🇷🇺 Русский (Россия)',
            'zh_CN' => '🇨🇳 中文 (中国)',
            'ja_JP' => '🇯🇵 日本語 (日本)',
            'ko_KR' => '🇰🇷 한국어 (대한민국)',
            'ar_SA' => '🇸🇦 العربية (السعودية)',
            'hi_IN' => '🇮🇳 हिन्दी (भारत)',
            'th_TH' => '🇹🇭 ไทย (ประเทศไทย)',
            'vi_VN' => '🇻🇳 Tiếng Việt (Việt Nam)',
            'pl_PL' => '🇵🇱 Polski (Polska)',
            'tr_TR' => '🇹🇷 Türkçe (Türkiye)',
            'sv_SE' => '🇸🇪 Svenska (Sverige)',
            'no_NO' => '🇳🇴 Norsk (Norge)',
            'da_DK' => '🇩🇰 Dansk (Danmark)',
            'fi_FI' => '🇫🇮 Suomi (Suomi)',
            'cs_CZ' => '🇨🇿 Čeština (Česká republika)',
            'sk_SK' => '🇸🇰 Slovenčina (Slovensko)',
            'hu_HU' => '🇭🇺 Magyar (Magyarország)',
            'ro_RO' => '🇷🇴 Română (România)',
            'bg_BG' => '🇧🇬 Български (България)',
            'hr_HR' => '🇭🇷 Hrvatski (Hrvatska)',
            'sr_RS' => '🇷🇸 Српски (Србија)',
            'sl_SI' => '🇸🇮 Slovenščina (Slovenija)',
            'et_EE' => '🇪🇪 Eesti (Eesti)',
            'lv_LV' => '🇱🇻 Latviešu (Latvija)',
            'lt_LT' => '🇱🇹 Lietuvių (Lietuva)',
            'uk_UA' => '🇺🇦 Українська (Україна)',
            'be_BY' => '🇧🇾 Беларуская (Беларусь)',
            'mk_MK' => '🇲🇰 Македонски (Македонија)',
            'sq_AL' => '🇦🇱 Shqip (Shqipëria)',
            'mt_MT' => '🇲🇹 Malti (Malta)',
            'is_IS' => '🇮🇸 Íslenska (Ísland)',
            'ga_IE' => '🇮🇪 Gaeilge (Éire)',
            'cy_GB' => '🏴󠁧󠁢󠁷󠁬󠁳󠁿 Cymraeg (Cymru)',
            'eu_ES' => '🇪🇸 Euskera (Euskadi)',
            'ca_ES' => '🇪🇸 Català (Catalunya)',
            'gl_ES' => '🇪🇸 Galego (Galicia)',
        ];
    }

    /**
     * Obtenir le nom de la timezone en français
     */
    public static function getTimezoneDisplayName(string $timezone): string
    {
        $timezones = self::getTimezones();
        return $timezones[$timezone] ?? $timezone;
    }

    /**
     * Obtenir le nom de la locale
     */
    public static function getLocaleDisplayName(string $locale): string
    {
        $locales = self::getLocales();
        return $locales[$locale] ?? $locale;
    }

    /**
     * Obtenir les fuseaux horaires les plus populaires en Europe
     */
    public static function getPopularEuropeanTimezones(): array
    {
        return [
            'Europe/Paris' => 'Europe - Paris (CET/CEST)',
            'Europe/London' => 'Europe - London (GMT/BST)',
            'Europe/Berlin' => 'Europe - Berlin (CET/CEST)',
            'Europe/Rome' => 'Europe - Rome (CET/CEST)',
            'Europe/Madrid' => 'Europe - Madrid (CET/CEST)',
            'Europe/Amsterdam' => 'Europe - Amsterdam (CET/CEST)',
            'Europe/Brussels' => 'Europe - Brussels (CET/CEST)',
            'Europe/Zurich' => 'Europe - Zurich (CET/CEST)',
            'Europe/Vienna' => 'Europe - Vienna (CET/CEST)',
            'Europe/Prague' => 'Europe - Prague (CET/CEST)',
            'Europe/Warsaw' => 'Europe - Warsaw (CET/CEST)',
            'Europe/Stockholm' => 'Europe - Stockholm (CET/CEST)',
            'Europe/Oslo' => 'Europe - Oslo (CET/CEST)',
            'Europe/Copenhagen' => 'Europe - Copenhagen (CET/CEST)',
            'Europe/Helsinki' => 'Europe - Helsinki (EET/EEST)',
            'Europe/Athens' => 'Europe - Athens (EET/EEST)',
            'Europe/Bucharest' => 'Europe - Bucharest (EET/EEST)',
            'Europe/Sofia' => 'Europe - Sofia (EET/EEST)',
            'Europe/Zagreb' => 'Europe - Zagreb (CET/CEST)',
            'Europe/Belgrade' => 'Europe - Belgrade (CET/CEST)',
            'Europe/Ljubljana' => 'Europe - Ljubljana (CET/CEST)',
            'Europe/Tallinn' => 'Europe - Tallinn (EET/EEST)',
            'Europe/Riga' => 'Europe - Riga (EET/EEST)',
            'Europe/Vilnius' => 'Europe - Vilnius (EET/EEST)',
            'Europe/Kiev' => 'Europe - Kiev (EET/EEST)',
            'Europe/Minsk' => 'Europe - Minsk (MSK)',
            'Europe/Moscow' => 'Europe - Moscow (MSK)',
            'UTC' => 'UTC'
        ];
    }
}
