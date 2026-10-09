---
title: La liste des permissions
icon: heroicon-o-list-bullet
order: 50
---

# La liste des permissions

Menu **Administration › Permissions**.

## D'où viennent les permissions

Les permissions sont **créées automatiquement** à partir de l'application : chaque liste, chaque page ou widget
réservé, chaque panel a les siennes. L'équipe technique les met à jour à chaque évolution (voir
[Pour l'équipe technique](#modal-permissions.technique)). Le Super Admin les reçoit toutes.

Il n'y a donc presque jamais besoin d'en créer une à la main : une permission dont aucun écran ne tient compte ne
sert à rien.

## Les colonnes

- **Nom** : voir [Lire le nom d'une permission](#modal-permissions.comprendre) ;
- **Guard** : `web` pour toutes, y compris celles qui servent à l'API ;
- **Rôles** : combien de rôles la contiennent.

## Modifier ou supprimer

Renommer une permission la déconnecte de l'écran qui la vérifie : ne le faites pas. Supprimer une permission la
retire de tous les rôles qui l'avaient ; si elle est encore utilisée par l'application, elle sera recréée à la
prochaine mise à jour, mais sans ses rôles.
