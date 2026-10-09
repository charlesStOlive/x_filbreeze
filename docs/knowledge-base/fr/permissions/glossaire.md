---
title: Glossaire
icon: heroicon-o-book-open
order: 90
---

# Glossaire

- **Permission** : l'autorisation de faire une seule chose, désignée par un nom à points
  (`administration.user.edit`). Voir [Comprendre les droits d'accès](#modal-permissions.comprendre).
- **Rôle** : un ensemble de permissions, donné à des utilisateurs. Voir [Créer et modifier un rôle](#modal-permissions.roles).
- **Joker** : une permission qui finit par `*` et vaut toutes celles qui commencent comme elle.
- **Permission par rôle** : une permission qui existe une fois par rôle et porte sur ses utilisateurs
  (`aiinteraction.viewrole.editeur`). Voir [Comprendre les droits d'accès](#modal-permissions.comprendre).
- **Auteur** (d'un élément) : celui qui l'a créé. Chacun voit toujours les siens.
- **État** : où en est un élément (Brouillon, Publié…). Voir les éléments des autres dans un état demande sa permission
  (`…viewstate.publie`).
- **Passage** (ou transition) : le changement d'un état à un autre (« Publier »), une permission (`…transition.publier`).
- **Tout, y compris à venir** : un joker ; il couvre aussi ce qui sera ajouté plus tard. Voir
  [Créer et modifier un rôle](#modal-permissions.roles).
- **Super Admin** : le rôle qui reçoit toutes les permissions.
- **Panel** : un espace de l'application avec son propre menu (l'administration, l'aide…), et sa permission d'entrée
  `panels.{panel}.access`.
- **Rubrique** (ou cluster) : un groupe de listes dans le menu, visible dès qu'une de ses listes l'est.
- **`viewany`, `view`, `create`, `edit`, `delete`** : voir la liste, ouvrir une fiche, créer, modifier, supprimer.
- **Permission obsolète** : une permission que plus aucun écran ne vérifie. Voir [Pour l'équipe technique](#modal-permissions.technique).
