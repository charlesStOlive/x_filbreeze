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
  Son nom et son email ne sont pas modifiables ici ; son mot de passe est géré par le fournisseur externe.
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

Seul un Super Admin peut modifier, supprimer un Super Admin, ou donner ce rôle. Lui seul peut aussi réinitialiser la
double authentification d'un Super Admin, ou créer une clé API à son nom. Voir
[Le rôle Super Admin](#modal-permissions.comprendre).

## Réinitialiser la double authentification

Si un utilisateur a perdu son application d'authentification, **Réinitialiser MFA** (dans la liste) retire sa double
authentification : il se connecte avec son mot de passe, puis la réactive depuis son profil. Le bouton demande la
permission « Réinitialiser la double authentification d’un utilisateur » (carte **Utilisateurs** de l'écran des rôles).

## Clés API et double authentification

Dans son profil, un utilisateur ne voit la rubrique **Clés API** que s'il a `administration.user.api-token`, et la
double authentification que s'il a `administration.user.2fa`.

Dans la liste des utilisateurs, **Générer une clé API** crée une clé pour la personne choisie. Cette action demande
`administration.user.api-token-others`. Donnez un nom à la clé, puis copiez-la dans la fenêtre : elle ne sera plus
affichée une fois celle-ci fermée.

## Mon profil et mes options

Le menu du compte › **Profil** rassemble l'identité, les préférences régionales (fuseau horaire et langue), les clés API
et les réglages propres aux modules de l'application. Un changement d'email ou de mot de passe demande le mot de passe
actuel. Une identité gérée par un fournisseur externe reste non modifiable.

Chaque module propose sa section à qui elle est destinée ; les notifications du Support, par exemple, se règlent ici.
**Rétablir les valeurs par défaut** efface les choix personnels de la section et reprend les valeurs actuelles de
l'application. Une option n'accorde jamais de droit supplémentaire.

## Voir et rétablir les options d'une autre personne

Sur sa fiche, **Options** ouvre un volet avec ses réglages, en lecture seule. Cette action demande
`administration.user.options`. Chaque section indique **Valeurs par défaut** ou la date de sa dernière modification.
L'administrateur peut **Rétablir les valeurs par défaut**, après confirmation, mais pas choisir d'autres valeurs à la
place de la personne. Les sections affichées sont celles destinées à cette personne, pas celles de l'administrateur.
