---
title: Gérer les utilisateurs
icon: heroicon-o-users
order: 30
---

# Gérer les utilisateurs

Menu **Administration › Utilisateurs**.

## La fiche d'un utilisateur

- **Nom** et **Email** : l'email sert à se connecter ; deux utilisateurs ne peuvent pas avoir le même.
- **Connexion externe** : indique si l'utilisateur se connecte par une application externe (Salesforce…). Si
  l'application le prévoit, un compte créé à la première connexion externe reçoit automatiquement un rôle.
- **Mot de passe** : obligatoire à la création ; à la modification, laissez vide pour garder l'actuel.
- **Rôles** : ce qui donne à l'utilisateur tous ses droits. Voir [Créer et modifier un rôle](#modal-permissions.roles).

## À quoi servent les rôles ?

Un utilisateur n'a que les droits de ses rôles : il n'y a pas de permission donnée à une seule personne. Quand un
besoin change, on modifie le rôle une fois, et tous ses utilisateurs suivent. Pour un besoin propre à quelques
personnes, créez un rôle pour elles.

## Un utilisateur sans rôle

Un utilisateur sans rôle ne peut pas se connecter : il lui manque l'entrée du panel. Voir
[Ce que chaque permission ouvre](#modal-permissions.acces).

## Les Super Admin

Seul un Super Admin peut modifier, supprimer un Super Admin, ou donner ce rôle. Voir
[Le rôle Super Admin](#modal-permissions.comprendre).

## Réinitialiser la double authentification

Si un utilisateur a perdu son application d'authentification, **Réinitialiser MFA** (dans la liste) retire sa double
authentification : il se connecte avec son mot de passe, puis la réactive depuis son profil. Le bouton demande la
permission « Réinitialiser la double authentification d’un utilisateur » (carte **Utilisateurs** de l'écran des rôles).

## Clés API et double authentification

Dans son profil, un utilisateur ne voit la rubrique **Clés API** que s'il a `administration.user.api-token`, et la
double authentification que s'il a `administration.user.2fa`.
