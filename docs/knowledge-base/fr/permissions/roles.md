---
title: Créer et modifier un rôle
icon: heroicon-o-shield-check
order: 20
---

# Créer et modifier un rôle

Menu **Administration › Rôles**.

## La liste des rôles

Pour chaque rôle : son **nom**, le nombre de **permissions** qu'il contient (« ∞ » pour le Super Admin, qui les a
toutes) et le nombre d'**utilisateurs** qui l'ont.

## L'écran d'un rôle

Donnez au rôle un **Nom** qui dit à qui il s'adresse (« auteur », « superviseur »…). Deux rôles ne peuvent pas avoir
le même nom.

Les **Permissions** sont rangées par rubrique (Voyage, Administration…), d'après le début de leur nom : le plus souvent
comme le menu, mais une liste rangée ailleurs dans le menu reste dans la rubrique de ses permissions (la cartographie,
dans le menu Orchestrator, reste dans Voyage). Dans chaque rubrique, **une carte
par liste**. Le champ **Chercher** en haut filtre les cartes (« voyage », « publier », « brouillon »…), et le nombre de
permissions données s'affiche à côté.

Chaque carte montre ce que la liste permet :

- **Actions** : voir la liste, ouvrir une fiche, créer, modifier, supprimer. Sans **Voir la liste**, la liste n'apparaît
  pas dans le menu.
- **Actions propres à cette liste**, quand elle en a : créer ses clés API, importer, voir les demandes IA de tout le
  monde…
- **Des familles d'actions**, quand la liste en a : par exemple **Bibliothèque** (charger, modifier, taguer, supprimer
  des images, et chaque action du menu « Sélection » ; les demandes à l'IA, comme « Croquis au crayon », y sont sous le
  sous-titre **Intelligence artificielle**), **Intelligence artificielle** (la correction orthographique…),
  **Éditions PDF**, **Brouillons d'e-mails**, **Exports Excel** (un modèle d'édition par case), **Qonto**, **Versions**. La case **Toute la famille** donne toutes celles de la
  famille, y compris les modèles ou actions ajoutés plus tard : un nouveau modèle PDF de facture sera permis sans rien
  cocher. Les boutons suivent : « Générer un PDF » ne propose que les modèles permis, et disparaît s'il n'y en a aucun.
- **Voir les enregistrements des autres**, pour une liste où chaque élément a un auteur (les voyages) :
  - **Les siens seulement** : chacun ne voit que ce qu'il a créé ;
  - **Ceux de certains rôles** : cochez les rôles dont on voit les éléments (un superviseur voit ceux des auteurs) ;
  - **Ceux de tout le monde**.

  Chacun voit toujours les siens, dans tous leurs états.
- **… dans les états** : pour les éléments **des autres**, les états qu'on voit. Un superviseur qui ne doit contrôler
  que ce qui est prêt ne coche pas « Brouillon ». Voir [Comprendre les droits d'accès](#modal-permissions.comprendre).
- **Changer d'état** : les passages d'un état à un autre (« Publier », de Brouillon à Publié…), avec ce qu'ils font.
  Un passage ne se fait que sur un élément qu'on voit.
  Pour certaines listes (les factures, par exemple), l'état ne décide que des passages : tout le monde voit la liste
  entière, et seuls les boutons de passage dépendent des droits.

Le compteur en haut de chaque carte dit combien de ses permissions sont données.

## Tout donner : deux façons

- **Tout cocher** coche ce qui existe **aujourd'hui** dans la carte. Si la liste gagne plus tard une action, un état ou
  un passage, le rôle ne l'aura pas sans qu'on le coche.
- **Tout, y compris à venir** (la case marquée « y compris à venir ») donne tout, **et aussi ce qui sera ajouté plus
  tard**. Les cases qu'elle couvre sont cochées et verrouillées ; au survol, elles disent pourquoi. Elle existe pour
  une liste, pour une rubrique entière (« Toute la rubrique »), pour les rôles d'une portée (« Tous les rôles ») et
  pour les états (« Tous les états »).

Préférez « Tout cocher » pour un rôle qui ne doit pas grandir tout seul, et « y compris à venir » pour un responsable
de tout un domaine.

**Tout décocher** retire tout, y compris le « y compris à venir » de la carte.

## Pour se connecter

Il faut au rôle l'entrée du panel, en haut de l'écran : **Entrée des panels** (l'administration, le centre d'aide…).
Voir [Ce que chaque permission ouvre](#modal-permissions.acces).

## Autres permissions

La dernière rubrique, repliée, montre les permissions qui ne correspondent à aucun écran actuel : anciennes,
techniques ou ajoutées à la main. Elles restent visibles pour qu'aucun droit ne soit caché ; on peut les retirer.

## Le Super Admin

Ses permissions ne se modifient pas ici : il les reçoit toutes, à chaque mise à jour. Seul un Super Admin peut
modifier son rôle (son nom).

## Supprimer un rôle

Un rôle ne se supprime que s'il n'est plus donné à personne : retirez-le d'abord aux utilisateurs concernés.

## Vérifier ce qu'un rôle voit

L'équipe technique peut afficher, rôle par rôle, tout ce qui est ouvert ou fermé : voir
[Pour l'équipe technique](#modal-permissions.technique).
