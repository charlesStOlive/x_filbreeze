---
title: Comprendre les droits d'accès
icon: heroicon-o-light-bulb
order: 10
---

# Comprendre les droits d'accès

Ce qu'un utilisateur voit et peut faire dépend de ses **permissions**. On ne les donne presque jamais une par une : on
les regroupe dans des **rôles**, et on donne des rôles aux utilisateurs.

## Utilisateur, rôle, permission

- Une **permission** autorise une seule chose : voir la liste des utilisateurs, modifier une fiche, entrer dans un
  panel…
- Un **rôle** est un ensemble de permissions, par exemple « manager » ou « éditeur ». Voir
  [Créer et modifier un rôle](#modal-permissions.roles).
- Un **utilisateur** a un ou plusieurs rôles : il a toutes les permissions de tous ses rôles, et seulement celles-là.
  Voir [Gérer les utilisateurs](#modal-permissions.utilisateurs).

Un droit n'existe que par une permission : aucun rôle n'est « spécial » en dehors de ce qu'il contient.

## Lire le nom d'une permission

Le nom d'une permission dit ce qu'elle ouvre, du plus général au plus précis, séparé par des points :

`administration.user.edit` = dans la rubrique **Administration**, les **utilisateurs**, **modifier**.

La dernière partie est l'action :

| Action | Ce qu'elle permet |
| --- | --- |
| `viewany` | voir la liste et l'entrée du menu |
| `view` | ouvrir une fiche |
| `create` | créer |
| `edit` | modifier (et remettre en ordre, restaurer) |
| `delete` | supprimer |

Certaines listes ont des actions en plus, propres à leur métier : `administration.user.api-token` permet de créer ses
propres clés API, `administration.user.2fa` d'activer la double authentification.

Sans `viewany`, les autres actions ne servent à rien : l'entrée du menu n'apparaît pas.

## Les jokers (`*`)

Une permission qui finit par `*` vaut toutes celles qui commencent comme elle :

- `administration.user.*` : tout sur les utilisateurs ;
- `administration.*` : tout dans la rubrique Administration ;
- `panels.*` : l'entrée dans tous les panels.

Un joker est pratique, mais large : il donne aussi les droits de modifier et de supprimer, et ceux de toute page ou
action ajoutée plus tard sous ce nom. Il vaut aussi pour l'API.

## Les siens, ceux des autres, et leur état

Pour les listes où chaque élément a un **auteur** (les voyages), deux questions s'ajoutent :

- **Ceux de qui ?** Chacun voit toujours les siens. Pour voir ceux des autres : `voyage.voyage.viewall` (tout le monde)
  ou `voyage.voyage.viewrole.auteur` (les utilisateurs du rôle « auteur »).
- **Dans quel état ?** Pour les éléments des autres, il faut aussi l'état : `voyage.voyage.viewstate.publie` ouvre ceux
  qui sont à l'état « Publié ». Sans aucun état coché, on ne voit pas ceux des autres, même avec le rôle.

Exemple : un **superviseur** qui contrôle les voyages publiés des auteurs a « Ceux de certains rôles : auteur » **et**
« Publié ». Il ne voit pas leurs brouillons. Ses propres voyages, il les voit tous.

Les **passages d'un état à un autre** sont aussi des permissions : `voyage.voyage.transition.publier` permet de publier
(de Brouillon à Publié), sur un voyage qu'on voit. Ce qui rend un passage possible ou non (ce qu'il faut pour publier)
est une règle de l'application, pas un droit.

## Les permissions « par rôle »

Quelques droits portent sur **les utilisateurs d'un rôle** : ils existent alors une fois par rôle, et le dernier
morceau du nom est le rôle. Par exemple, `aiinteraction.viewrole.editeur` permet de voir les demandes IA des
utilisateurs qui ont le rôle « editeur ».

- Elles apparaissent toutes seules quand on crée un rôle, suivent son nom s'il est renommé (ceux qui les avaient les
  gardent), et disparaissent avec lui.
- Le joker `aiinteraction.viewrole.*` vaut pour tous les rôles.

Pour que les managers voient la consommation IA de leurs éditeurs, cochez `aiinteraction.viewrole.editeur` dans le
rôle « manager ». Voir [Créer et modifier un rôle](#modal-permissions.roles).

## Le rôle Super Admin

Le rôle **Super Admin** reçoit toutes les permissions, et chaque nouvelle permission lui est ajoutée automatiquement.
Il n'a pas de passe-droit caché : il voit tout parce qu'il a tout. Seul un Super Admin peut modifier un autre Super
Admin, ou donner ce rôle.

## Pour aller plus loin

- [Ce que chaque permission ouvre](#modal-permissions.acces) : panels, menus, pages, widgets.
- [La liste des permissions](#modal-permissions.liste) : d'où elles viennent.
- [Glossaire](#modal-permissions.glossaire).
