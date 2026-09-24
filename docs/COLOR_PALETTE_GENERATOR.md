# Couleurs de l'application

Les couleurs viennent du package `charlesstolive/filament-ui` (anciennement `filament-color-installer`) : voir son
README, `vendor/charlesstolive/filament-ui/README.md`.

## Où elles se règlent

`config/filament-ui.php`, bloc `colors` : primary, secondary, tertiary. La nuance 500 est la couleur saisie, les
autres (50 à 950) en sont déclinées. Les couleurs d'état (success, danger, warning, info) sont celles de Filament tant
qu'elles n'y figurent pas. Il n'y a pas de couleur `error` : c'est `danger`.

Le package les enregistre pour tous les panels : `AdminPanelProvider` n'a plus de `->colors()`. Changer une couleur
de la configuration suffit, sans rien régénérer ni recompiler.

## Commandes

```bash
./vendor/bin/sail artisan filament-ui:colors --mode=manuel --primary=#DB8E57 --secondary=#55CEC4 --tertiary=#737ACF --status=filament
./vendor/bin/sail artisan filament-ui:colors --primary=#DB8E57 --mode=split-comp   # secondary et tertiary contrastées
./vendor/bin/sail artisan filament-ui:colors --check                               # contrôler, sans rien écrire
./vendor/bin/sail artisan filament-ui:install                                      # rebrancher les @import
```

## Feuilles de style

- panel : `resources/css/filament/admin/filament.css` importe `filament-ui.css` (`panel_css`) ;
- PDF : `resources/css/pdf/pdf.css` importe `colors.css` (`front_css`), et `resources/views/pdf/layouts/main.blade.php`
  pose `<x-filament-ui::colors />` après la feuille ;
- front : `resources/css/front/front.css` importe `colors.css` ; une page qui la charge doit aussi poser
  `<x-filament-ui::colors />` dans son `<head>`, sinon `bg-primary-500`… ne donnent aucune couleur.
