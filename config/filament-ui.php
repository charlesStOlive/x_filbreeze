<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Couleurs
    |--------------------------------------------------------------------------
    |
    | La seule source des couleurs de l'application : les panels Filament les
    | reçoivent au démarrage (un panel peut encore en surcharger une avec son
    | propre ->colors()), et les pages front par <x-filament-ui::colors />.
    | Chaque couleur devient la nuance 500 de sa palette ; les nuances 50 à 400
    | et 600 à 950 s'étagent autour, les mêmes des deux côtés. Idéalement, une
    | luminosité entre 40 et 85 % (filament-ui:colors --check le vérifie).
    |
    | Les couleurs d'état (success, danger, warning, info) absentes d'ici sont
    | celles de Filament, dans les panels comme sur le front. Les déclarer ici
    | les remplace (`filament-ui:colors --status=accorder` les tire de primary).
    |
    | `php artisan filament-ui:colors` réécrit ce bloc (entre les deux marqueurs) :
    | il peut tirer de primary secondary, tertiary et les couleurs d'état.
    |
    */
    // filament-ui:colors:start
    'colors' => [
        'primary' => '#3B82F6',
        'secondary' => '#8B5CF6',
        'tertiary' => '#F59E0B',
    ],
    // filament-ui:colors:end

    /*
    |--------------------------------------------------------------------------
    | Feuilles de style
    |--------------------------------------------------------------------------
    |
    | Où `php artisan filament-ui:install` ajoute les @import du package : le
    | thème des panels reçoit filament-ui.css (couleurs, macros, volet
    | latéral), les feuilles du front seulement colors.css.
    |
    */
    'panel_css' => [
        'resources/css/filament/admin/filament.css',
    ],

    'front_css' => [
        'resources/css/front/front.css',
        'resources/css/pdf/pdf.css',
    ],
];
