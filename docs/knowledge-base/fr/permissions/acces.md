---
title: Ce que chaque permission ouvre
icon: heroicon-o-key
order: 40
---

# Ce que chaque permission ouvre

## Entrer dans un panel

Chaque panel (l'administration, l'aide…) a sa permission d'entrée : `panels.{panel}.access`, par exemple
`panels.admin.access`. Sans elle, la connexion à ce panel est refusée. `panels.*` ouvre tous les panels.

## Les listes (utilisateurs, rôles, fiches métier…)

Chaque liste a ses permissions `viewany`, `view`, `create`, `edit`, `delete`, et parfois des actions en plus. Voir
[Lire le nom d'une permission](#modal-permissions.comprendre).

Les boutons suivent : sans `create`, pas de bouton « Créer » ; sans `delete`, pas de « Supprimer ».

## Les rubriques du menu

Une rubrique (Administration…) n'a pas de permission à elle : elle apparaît dès qu'une de ses listes est ouverte à
l'utilisateur, et disparaît sinon. Inutile, donc, de donner un joker comme `administration.*` « pour voir la
rubrique » : il donnerait tous les droits sur toutes ses listes.

## Les pages et les widgets

Certaines pages et certains widgets du tableau de bord sont réservés : leur permission s'appelle
`pages.{page}.viewany` ou `widgets.{widget}.viewany`, par exemple `widgets.qontoapistatus.viewany` pour l'état de la
connexion à Qonto. Les autres sont visibles de tout utilisateur du panel.

## Ce qui reste ouvert à tout utilisateur du panel

- le **tableau de bord** : c'est la page d'arrivée après la connexion ;
- les widgets et les pages qui ne sont pas réservés ;
- l'**aide**, pour qui entre dans son panel ;
- son propre **profil** (ses options : nom, fuseau, langue…). Deux de ses rubriques sont réservées : les **Clés API**
  et la **double authentification**. Dans l'écran d'un rôle, elles sont dans la carte **Utilisateurs**, rubrique
  Administration, sous « Actions propres à cette liste » (« Profil (options) : … »).

## Et l'API ?

Une application appelée par l'API utilise le compte d'un utilisateur : elle a exactement ses permissions, avec les
mêmes noms que dans l'administration (`…view`, `…edit`…). Les jokers y valent aussi.
