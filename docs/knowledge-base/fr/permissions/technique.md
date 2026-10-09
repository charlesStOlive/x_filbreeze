---
title: Pour l'équipe technique
icon: heroicon-o-wrench-screwdriver
order: 60
---

# Pour l'équipe technique

Les commandes se lancent depuis le serveur (`php artisan …`, ou `sail artisan …` en développement).

## Mettre à jour les permissions

`permissions:sync --force` parcourt les panels et crée les permissions de chaque liste, page ou widget réservé et
panel, et, pour les listes qui les déclarent, celles des auteurs, des états et des passages (`…viewall`,
`…viewstate.{état}`, `…transition.{clé}`), puis les donne au Super Admin (`permissions:grant-super-admin`). Ajouter un
état ou un passage dans le code demande de relancer la synchronisation pour que sa case apparaisse. À lancer après chaque déploiement qui ajoute
une liste, une page ou un widget.

`permissions:sync --force --dry-run` montre en plus les permissions devenues **obsolètes** (plus aucun écran ne les
vérifie), avec le nombre de rôles qui les ont encore. `--cleanup` les retire des rôles puis les supprime.

## Voir qui voit quoi

`permissions:audit` affiche, pour chaque panel, un tableau : les listes, rubriques, pages et widgets en lignes, les
rôles en colonnes (✓ ouvert, · fermé), et ce qui contrôle chaque élément. Rien n'est modifié.

`--role=manager` ou `--panel=admin` limitent le tableau. Une ligne « AUCUN CONTRÔLE » est une liste ouverte à tout
utilisateur du panel ; « ⚠ modèle partagé » signale une liste qui vérifie les permissions d'une autre.

À lancer avant et après un changement de rôles ou une mise à jour : chaque ✓ devenu · doit être voulu.

## Ce qui se règle dans la configuration

Dans `config/filament-permission-manager.php`, rubrique `authorization` :

- `excluded_resources` : listes laissées ouvertes à tout utilisateur du panel, sans permission (par exemple l'aide) ;
- `fallback_policy` et `declared_permissions` : à `false`, une application pas encore adaptée garde l'ancien
  comportement, où ce qui n'avait pas de contrôle propre restait ouvert.

Le détail, et la marche à suivre pour adapter une application, sont dans
`docs/controle-des-acces.md` du package filament-permission-manager.
