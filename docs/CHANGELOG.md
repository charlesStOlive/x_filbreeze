# 📋 Notes de Version - X-Filbreeze

## Version 3.0 - 5 Novembre 2025

### 🔄 Simplification du Système de Statuts d'Emails

#### ❌ Suppression de l'État "Partial"

- **État `EmailStatus::Partial` supprimé** : Logique de statuts simplifiée
- **Règle unifiée** : Tous les emails avec services terminés passent à `End`
- **Plus de confusion** entre "partiellement terminé" et "terminé"

#### 🐛 Correction des Services Bloqués

- **Fix majeur** : Les emails avec tous les services bloqués lors du preflight passent maintenant à `End`
- **Logique dans MsGraphNotificationService** : Recalcul automatique du statut après les preflight
- **Cohérence** : Même comportement que les services qui réussissent

#### 🎨 Améliorations de l'Interface Utilisateur

- **MailServiceColumn configurables** :
  - `modalWidth()` : Contrôle de la taille des modals (xs à 2xl)
  - `buttonSize()` : Dimensions personnalisables des boutons 
  - `showMessage()` : Affichage des messages de retour dans les boutons
- **Design amélioré** :
  - Bordures colorées selon le statut (vert/bleu/gris)
  - Icônes de statut positionnées dans les coins
  - Support des messages tronqués avec tooltip
- **Flexibilité** : Configuration adaptée à différents contextes d'affichage

### 📊 Nouvelle Logique de Statuts

**Avant** (complexe) :
```php
$hasSuccess = in_array(ProcessorStatus::Success->value, $statuses, true);
$hasBlocked = in_array(ProcessorStatus::Blocked->value, $statuses, true);
$status = ($hasSuccess && $hasBlocked) ? Partial : End;
```

**Après** (simplifié) :
```php
if (active_jobs > 0) return Processing;
if (has_error) return Error;
return End; // Dans tous les autres cas
```

### 🔧 Modifications Techniques

- **BaseEmailDraftProcessor::recomputeEmailStatus()** : Logique simplifiée
- **MsGraphNotificationService** : Recalcul automatique des statuts
- **Suppression de code redondant** : Nettoyage des anciennes conditions

---

## Version 2.0 - 21 Août 2025

### 🆕 Nouvelles Fonctionnalités Majeures

#### 🏗️ Structure Hiérarchique des Permissions

- **Permissions par cluster** : Format `cluster.resource.action` (ex: `crm.companies.view`)
- **Permissions globales** : Support `cluster.*` pour accès complet au cluster
- **Organisation logique** : Permissions regroupées par domaine métier
- **Compatibilité ascendante** : Resources autonomes conservent le format `resource.action`

#### 🌱 Génération Automatique de Seeders

- **Commande `permissions:generate-seeder`** : Création automatique de seeders
- **Filtrage avancé** : Par cluster, rôle, type de contenu
- **Déploiement facilité** : Seeders prêts pour production
- **Structure idempotente** : Exécution multiple sans problème

#### 🧹 Nettoyage Automatique des Permissions

- **Détection automatique** des permissions obsolètes qui ne correspondent plus aux Resources Filament
- **Protection système** : Les permissions commençant par `s_` sont automatiquement exclues
- **Nettoyage sécurisé** : Confirmation requise avant retrait des permissions des rôles/utilisateurs
- **Mode simulation** : Option `--dry-run` pour tester sans risque

### � Nouvelles Commandes

#### `permissions:generate-seeder`
```bash
php artisan permissions:generate-seeder [--file=nom] [--cluster=nom] [--role=nom] [--permissions-only] [--roles-only]
```

**Options disponibles :**
- `--file=nom` : Nom personnalisé du fichier seeder
- `--path=chemin` : Dossier de destination
- `--cluster=nom` : Filtrer par cluster spécifique
- `--role=nom` : Filtrer par rôle spécifique
- `--permissions-only` : Générer seulement les permissions
- `--roles-only` : Générer seulement les rôles

#### Options ajoutées à `permissions:sync`
- `--cleanup` : Active le nettoyage des permissions obsolètes
- `--dry-run` : Mode simulation pour voir ce qui serait supprimé

### 🏗️ Restructuration Architecturale

#### Clusters Hiérarchiques

**Cluster CRM (`crm.*`) :**
- `crm.companies.*` : Gestion des entreprises
- `crm.contacts.*` : Gestion des contacts
- `crm.leads.*` : Gestion des prospects

**Cluster DataSets (`datasets.*`) :**
- `datasets.categories.*` : Catégories
- `datasets.countries.*` : Pays
- `datasets.currencies.*` : Devises
- `datasets.languages.*` : Langues
- `datasets.timezones.*` : Fuseaux horaires

**Cluster MsGraph (`msgraph.*`) :**
- `msgraph.calendar.*` : Calendrier
- `msgraph.contacts.*` : Contacts
- `msgraph.drives.*` : Lecteurs
- `msgraph.mails.*` : E-mails
- `msgraph.teams.*` : Teams

#### Resources Autonomes
- `users.*` : Gestion des utilisateurs
- `permissions.*` : Gestion des permissions
- `roles.*` : Gestion des rôles
- `activity-log.*` : Journaux d'activité

### � Améliorations du Service

#### PermissionService
- **Support wildcard étendu** : Gestion des permissions hiérarchiques
- **Optimisation des vérifications** : Logique améliorée pour les clusters
- **Compatibilité** : Fonctionne avec ancienne et nouvelle structure

### �🛡️ Protections Renforcées

- **Exclusion automatique** des permissions système (`s_*`)
- **Retrait sécurisé** des permissions des rôles avant suppression
- **Confirmations multiples** pour éviter les suppressions accidentelles
- **Resynchronisation automatique** du rôle admin après nettoyage

### � Documentation Complète

#### Nouveaux Documents
- **SEEDER_GENERATION.md** : Guide complet des seeders
- **Structure hiérarchique** documentée dans PERMISSIONS_DOCUMENTATION.md
- **Référence rapide** mise à jour avec nouvelles commandes

#### Guides Pratiques
- **Workflow de déploiement** avec seeders
- **Exemples concrets** de filtrage
- **Bonnes pratiques** de sécurité
- **Cas d'usage avancés**

---

## Version 1.1 - 21 Août 2025

### 🆕 Nouvelles Fonctionnalités

#### 🧹 Nettoyage Automatique des Permissions (Version Initiale)

- **Détection automatique** des permissions obsolètes
- **Protection système** : Préfixe `s_*` protégé
- **Mode simulation** : `--dry-run`

### 🔧 Améliorations

#### Interface Utilisateur
- Messages plus clairs et informatifs
- Tableaux détaillés avec statut de sécurité
- Compteurs précis des assignations
- Émojis pour améliorer la lisibilité

### 🚀 Utilisation

#### Workflow Recommandé

1. **Simulation** (sans risque) :
   ```bash
   php artisan permissions:sync --cleanup --dry-run
   ```

2. **Nettoyage interactif** :
   ```bash
   php artisan permissions:sync --cleanup
   ```

3. **Automatisation** (scripts) :
   ```bash
   php artisan permissions:sync --cleanup --force
   ```

#### Exemples de Résultats

```bash
🧹 Détection des permissions obsolètes...
⚠️  5 permission(s) obsolète(s) détectée(s):

┌─────────────────────┬───────┬──────────────┬─────┐
│ Permission          │ Rôles │ Utilisateurs │ Sûr │
├─────────────────────┼───────┼──────────────┼─────┤
│ crm.old_feature.*   │ 2     │ 0            │ ⚠️  │
│ unused_permission   │ 0     │ 0            │ ✅  │
│ s_system_config     │ 1     │ 0            │ 🛡️  │
└─────────────────────┴───────┴──────────────┴─────┘

ℹ️  Les permissions commençant par "s_" sont automatiquement exclues
```

### 🔄 Migration

#### Automatique
Le système est **rétrocompatible**. Aucune migration manuelle nécessaire.

#### Recommandations
1. Tester avec `--dry-run` avant le premier nettoyage
2. Vérifier les permissions système importantes
3. Sauvegarder la base avant nettoyage massif

### 📊 Impact

- **Cohérence** : Permissions alignées sur les Resources actuelles
- **Performance** : Moins de permissions inutiles à vérifier
- **Maintenance** : Nettoyage automatisé
- **Sécurité** : Protection des permissions critiques

---

## Version 1.0 - Août 2025

### 🎯 Fonctionnalités Initiales

- Système de permissions wildcard avec Spatie Permission
- Auto-découverte des Resources Filament
- Service centralisé `PermissionService`
- Commandes Artisan complètes :
  - `permissions:sync`
  - `permissions:add-resource`
  - `permissions:list`
  - `permissions:reset`
- Documentation complète
- Gestion des Clusters Filament
- Permissions granulaires par action

### 🏗️ Architecture

- **Laravel 11** + **Filament 3** + **Spatie Permission**
- Structure de permissions cohérente
- Système de rôles flexible
- Vérifications centralisées

### 📋 Permissions Générées

- Permissions globales : `admin.*`, `users.*`, `roles.*`, `permissions.*`
- Permissions par Resource : `resource.*`, `resource.view`, `resource.create`, `resource.edit`, `resource.delete`
- Support des Clusters : Scan automatique des Resources dans les Clusters
