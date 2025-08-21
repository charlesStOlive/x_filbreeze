# 🔐 Documentation - Système de Permissions Wildcard

> **Version :** 2.0  
> **Date :** 21 Août 2025  
> **Framework :** Laravel 11 + Filament 3 + Spatie Permission  

## 📋 Vue d'ensemble

Ce système de permissions utilise une approche **wildcard** avec Spatie Permission pour gérer les autorisations dans Filament. Il permet une gestion granulaire et automatisée des permissions avec un système de nomenclature cohérent et hiérarchique.

### 🎯 Philosophie du système

- **Permissions wildcard** : `resource.*` pour accès complet, `resource.action` pour actions spécifiques
- **Structure hiérarchique** : `cluster.resource.*` pour les clusters, `resource.*` pour les resources autonomes
- **Auto-découverte** : Scan automatique des Resources Filament avec détection des clusters
- **Gestion centralisée** : Service unique `PermissionService` pour toutes les vérifications
- **Sécurité par défaut** : Rôle admin avec tous les droits, contrôles stricts sur les Resources
- **Déploiement automatisé** : Seeders générés pour déployer les configurations

---

## 🛠️ Commandes Artisan Disponibles

### 1. `permissions:sync` - Synchronisation automatique

**Usage :**
```bash
php artisan permissions:sync [--force] [--cleanup] [--dry-run]
```

**Description :**  
Scanne toutes les Resources Filament et génère automatiquement les permissions correspondantes.

**Fonctionnalités :**
- 🔍 Scan récursif des dossiers `app/Filament/Resources` et `app/Filament/Clusters`
- 📋 Génération automatique des permissions globales et spécifiques
- 🔐 Attribution automatique au rôle `admin`
- ✅ Détection des permissions existantes vs nouvelles
- 🧹 **NOUVEAU** : Nettoyage intelligent des permissions obsolètes
- 🛡️ **NOUVEAU** : Protection des permissions système (préfixe `s_`)

**Options :**
- `--force` : Synchronise sans demander de confirmation
- `--cleanup` : **NOUVEAU** - Active le nettoyage des permissions obsolètes
- `--dry-run` : **NOUVEAU** - Simule le nettoyage sans rien supprimer

**Exemple de sortie :**
```
🔍 Scan des Resources Filament...
📋 Trouvé 13 resource(s)
🔐 69 permission(s) à synchroniser

✅ Créé: products.view
ℹ️  Existe: users.*

🧹 Détection des permissions obsolètes...
⚠️  3 permission(s) obsolète(s) détectée(s):
┌─────────────────┬───────┬──────────────┬─────┐
│ Permission      │ Rôles │ Utilisateurs │ Sûr │
├─────────────────┼───────┼──────────────┼─────┤
│ old_permission  │ 1     │ 0            │ ⚠️  │
│ unused_perm     │ 0     │ 0            │ ✅  │
└─────────────────┴───────┴──────────────┴─────┘
ℹ️  Les permissions commençant par "s_" sont automatiquement exclues
```

**⚠️ Sécurités importantes :**
- Les permissions encore assignées à des rôles/utilisateurs nécessitent confirmation avant suppression
- Les permissions système (préfixe `s_`) sont automatiquement protégées
- Possibilité de retrait automatique des permissions obsolètes des rôles avant suppression

**Permissions générées pour chaque resource :**
- `resource.*` (accès complet)
- `resource.view` (consultation)
- `resource.create` (création)
- `resource.edit` (modification)
- `resource.delete` (suppression)

---

### 2. `permissions:add-resource` - Ajout d'une nouvelle resource

**Usage :**
```bash
php artisan permissions:add-resource {resource} [--actions=view,create,edit,delete]
```

**Description :**  
Crée les permissions pour une nouvelle resource et génère le code à intégrer.

**Arguments :**
- `resource` : Nom de la resource (ex: `client`, `product`, `invoice`)

**Options :**
- `--actions` : Actions personnalisées (défaut: `view,create,edit,delete`)

**Exemples :**
```bash
# Permissions standard
php artisan permissions:add-resource client

# Permissions personnalisées
php artisan permissions:add-resource report --actions=view,export,generate
```

**Code généré automatiquement :**
```php
// Ajoutez cet import en haut du fichier
use App\Services\PermissionService;

// Ajoutez ces méthodes dans votre Resource
public static function canViewAny(): bool
{
    return PermissionService::can('client.view');
}

public static function canCreate(): bool
{
    return PermissionService::can('client.create');
}
// ... etc
```

---

### 3. `permissions:reset` - Reset complet du système

**Usage :**
```bash
php artisan permissions:reset [--force]
```

**Description :**  
⚠️ **ATTENTION** : Supprime TOUTES les permissions, rôles et détache tous les utilisateurs.

**Processus :**
1. 📤 Détachement de tous les rôles des utilisateurs
2. 🗑️ Suppression de toutes les permissions
3. 👥 Suppression de tous les rôles
4. 🔄 Option de recréation automatique du système

**Options :**
- `--force` : Reset sans confirmation

**Workflow de récupération :**
```bash
php artisan permissions:reset
# Puis confirmer la recréation automatique
# Ou manuellement :
php artisan permissions:sync --force
```

---

## 🧹 Nettoyage Automatique des Permissions

### Principe du nettoyage intelligent

Le système peut détecter et supprimer automatiquement les permissions obsolètes qui ne correspondent plus aux Resources Filament actuelles.

### Protection des permissions système

**Exclusion automatique :**
- Toutes les permissions commençant par `s_` sont **automatiquement protégées**
- Exemples : `s_special_permission`, `s_system_config`, `s_custom_feature`
- Ces permissions ne seront jamais supprimées, même avec `--force`

### Processus de nettoyage sécurisé

1. **Détection** des permissions obsolètes
2. **Analyse** des assignations existantes (rôles + utilisateurs)
3. **Confirmation** avant retrait des assignations
4. **Retrait sécurisé** des permissions des rôles/utilisateurs
5. **Suppression** des permissions obsolètes
6. **Resynchronisation** du rôle admin

### Utilisation pratique

**Simulation (recommandé) :**
```bash
php artisan permissions:sync --cleanup --dry-run
```

**Nettoyage interactif :**
```bash
php artisan permissions:sync --cleanup
```

**Nettoyage automatique :**
```bash
php artisan permissions:sync --cleanup --force
```

### Exemple de session de nettoyage

```bash
$ php artisan permissions:sync --cleanup

🧹 Détection des permissions obsolètes...
⚠️  5 permission(s) obsolète(s) détectée(s):
┌─────────────────────┬───────┬──────────────┬─────┐
│ Permission          │ Rôles │ Utilisateurs │ Sûr │
├─────────────────────┼───────┼──────────────┼─────┤
│ crm.old_feature.*   │ 2     │ 0            │ ⚠️  │
│ unused_permission   │ 0     │ 0            │ ✅  │
│ s_system_config     │ 1     │ 0            │ 🛡️  │
└─────────────────────┴───────┴──────────────┴─────┘

⚠️  2 permission(s) sont encore assignées à des rôles
   - À 3 rôle(s)
   - À 0 utilisateur(s) directement

Voulez-vous retirer ces permissions de tous les rôles et utilisateurs ? [yes/no]
> yes

🔧 Retrait des permissions des rôles et utilisateurs...
✅ Permission 'crm.old_feature.*' retirée de 2 rôle(s)

Confirmer la suppression des permissions obsolètes ? [yes/no]  
> yes

🗑️ Suppression des permissions obsolètes...
✅ Supprimé: crm.old_feature.*
✅ Supprimé: unused_permission
🛡️ Protégé: s_system_config (exclusion s_*)

🧹 Nettoyage terminé !
   - 2 permission(s) supprimée(s)
   - 1 permission(s) protégée(s)
```

---

### 4. `permissions:generate-seeder` - Génération de seeders de déploiement

**Usage :**
```bash
php artisan permissions:generate-seeder [--file=NomSeeder] [--path=database/seeders] [--cluster=nom] [--role=nom] [--permissions-only] [--roles-only]
```

**Description :**  
Génère automatiquement un seeder contenant toutes les permissions et rôles actuels pour faciliter le déploiement sur d'autres environnements.

**Options :**
- `--file=NomSeeder` : Nom du fichier seeder (défaut: `PermissionSeeder`)
- `--path=database/seeders` : Dossier de destination
- `--cluster=nom` : Générer seulement pour un cluster spécifique (ex: `crm`, `datasets`)
- `--role=nom` : Générer seulement pour un rôle spécifique (ex: `admin`, `manager`)
- `--permissions-only` : Générer seulement les permissions (sans les rôles)
- `--roles-only` : Générer seulement les rôles (sans les permissions)

**Exemples d'utilisation :**

```bash
# Seeder complet (toutes permissions + tous rôles)
php artisan permissions:generate-seeder

# Seeder pour un cluster spécifique
php artisan permissions:generate-seeder --cluster=crm --file=CrmPermissionSeeder

# Seeder pour un rôle spécifique
php artisan permissions:generate-seeder --role=admin --file=AdminRoleSeeder

# Seulement les permissions
php artisan permissions:generate-seeder --permissions-only --file=PermissionsOnlySeeder

# Seulement les rôles
php artisan permissions:generate-seeder --roles-only --file=RolesOnlySeeder
```

**Fonctionnalités du seeder généré :**

- 📋 **Organisation hiérarchique** : Permissions groupées par cluster/resource
- 🔗 **Associations automatiques** : Rôles avec leurs permissions respectives
- 🛡️ **Idempotence** : Le seeder peut être exécuté plusieurs fois sans problème
- 📝 **Documentation** : Commentaires automatiques avec statistiques
- 🎯 **Filtrage intelligent** : Respect des filtres cluster/rôle dans toute la chaîne

**Structure du seeder généré :**

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeder généré automatiquement le 2025-08-21 15:30:00
 * 
 * Contient :
 * - 74 permissions organisées par cluster
 * - 4 rôles avec leurs associations
 */
class PermissionSeeder extends Seeder
{
    public function run()
    {
        // Création des permissions par groupe
        $this->createCrmPermissions();
        $this->createDatasetsPermissions();
        // ...

        // Création des rôles et associations
        $this->createRoles();
    }

    private function createCrmPermissions()
    {
        $permissions = [
            'crm.*', 'crm.companies.*', 'crm.companies.view',
            // ... permissions du cluster CRM
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
    }
}
```

**Utilisation pour le déploiement :**

1. **Génération** sur l'environnement source :
   ```bash
   php artisan permissions:generate-seeder --file=ProductionPermissions
   ```

2. **Transfert** du fichier vers l'environnement cible

3. **Exécution** sur l'environnement cible :
   ```bash
   # Ajouter dans database/seeders/DatabaseSeeder.php :
   $this->call(ProductionPermissions::class);
   
   # Puis exécuter :
   php artisan db:seed --class=ProductionPermissions
   ```

---

### 5. `permissions:list` - Liste et recherche

**Usage :**
```bash
php artisan permissions:list [--role=nom] [--search=terme]
```

**Description :**  
Affiche un aperçu complet du système de permissions avec possibilités de filtrage.

**Options :**
- `--role=nom` : Filtrer par rôle spécifique
- `--search=terme` : Rechercher dans les noms de permissions

**Exemples :**
```bash
# Vue d'ensemble complète
php artisan permissions:list

# Permissions d'un rôle
php artisan permissions:list --role=admin

# Recherche spécifique
php artisan permissions:list --search=user
```

**Affichage organisé :**
- 👥 **Rôles** : Nom, nombre de permissions, nombre d'utilisateurs
- 🔑 **Permissions** : Groupées par préfixe, avec rôles associés
- 📊 **Statistiques** : Totaux et compteurs

---

## 🏗️ Architecture du Système

### Structure des permissions

```
admin.*                    # Accès administrateur complet
users.*                    # Gestion utilisateurs (global)
├── users.view            # Consultation des utilisateurs
├── users.create          # Création d'utilisateurs
├── users.edit            # Modification d'utilisateurs
└── users.delete          # Suppression d'utilisateurs

roles.*                    # Gestion des rôles
permissions.*              # Gestion des permissions
products.*                 # Gestion des produits
crm.*                      # Module CRM complet
├── crm.companies.*       # Entreprises CRM
├── crm.contacts.*        # Contacts CRM
└── crm.invoices.*        # Facturation CRM
```

### Service de vérification

**`App\Services\PermissionService`**

```php
// Vérification avec utilisateur actuel
PermissionService::can('users.view')

// Vérification avec utilisateur spécifique
PermissionService::can($user, 'products.edit')

// Support wildcard
PermissionService::can('admin.*')  // true si admin.* OU permission exacte
```

### Intégration dans les Resources Filament

**Méthodes obligatoires dans chaque Resource :**

```php
public static function canViewAny(): bool
{
    return PermissionService::can('resource.view');
}

public static function canCreate(): bool
{
    return PermissionService::can('resource.create');
}

public static function canEdit($record): bool
{
    return PermissionService::can('resource.edit');
}

public static function canDelete($record): bool
{
    return PermissionService::can('resource.delete');
}
```

**Protection des Clusters :**

```php
// Dans votre Cluster
public static function canAccess(): bool
{
    return PermissionService::can('cluster.*');
}
```

---

## 🚀 Workflows Recommandés

### 1. Nouvelle Resource

```bash
# 1. Créer les permissions
php artisan permissions:add-resource maresource

# 2. Copier le code généré dans votre Resource

# 3. Vérifier
php artisan permissions:list --search=maresource
```

### 2. Maintenance du système

```bash
# Synchronisation complète (après ajout de Resources)
php artisan permissions:sync

# Vérification de l'état
php artisan permissions:list

# Recherche de problèmes
php artisan permissions:list --role=admin
```

### 3. Reset et reconstruction

```bash
# En cas de problème majeur
php artisan permissions:reset --force
php artisan permissions:sync --force

# Puis réassigner les utilisateurs aux rôles
```

---

## 🏗️ Structure Hiérarchique des Permissions

### Principe de hiérarchisation

Le système utilise une nomenclature hiérarchique qui reflète l'organisation des Resources Filament :

**Format général :**
- **Clusters :** `cluster.resource.action` (ex: `crm.companies.view`)
- **Resources autonomes :** `resource.action` (ex: `users.edit`)
- **Permissions globales :** `cluster.*` ou `resource.*`

### Structure actuelle

**Cluster CRM (`crm.*`) :**
```
crm.*                    → Accès global au cluster CRM
├── crm.companies.*      → Toutes actions sur companies
│   ├── crm.companies.view
│   ├── crm.companies.create
│   ├── crm.companies.edit
│   └── crm.companies.delete
├── crm.contacts.*       → Toutes actions sur contacts
│   ├── crm.contacts.view
│   ├── crm.contacts.create
│   ├── crm.contacts.edit
│   └── crm.contacts.delete
└── crm.leads.*         → Toutes actions sur leads
    ├── crm.leads.view
    ├── crm.leads.create
    ├── crm.leads.edit
    └── crm.leads.delete
```

**Cluster DataSets (`datasets.*`) :**
```
datasets.*               → Accès global au cluster DataSets
├── datasets.categories.*
├── datasets.countries.*
├── datasets.currencies.*
├── datasets.languages.*
└── datasets.timezones.*
```

**Cluster MsGraph (`msgraph.*`) :**
```
msgraph.*                → Accès global au cluster MsGraph
├── msgraph.calendar.*
├── msgraph.contacts.*
├── msgraph.drives.*
├── msgraph.mails.*
└── msgraph.teams.*
```

**Resources autonomes :**
```
users.*                  → Gestion utilisateurs
permissions.*           → Gestion permissions
roles.*                 → Gestion rôles
activity-log.*          → Logs d'activité
```

### Avantages de cette structure

- 🎯 **Granularité précise** : Contrôle fin par action et par resource
- 🏗️ **Organisation logique** : Reflète l'architecture Filament
- 🔍 **Facilité de gestion** : Permissions groupées par domaine métier
- 🚀 **Évolutivité** : Ajout facile de nouveaux clusters/resources
- 🛡️ **Sécurité** : Permissions minimales par défaut

### Gestion des accès par cluster

**Protection des clusters :**
```php
// Dans app/Filament/Clusters/Crm.php
public static function canAccess(): bool
{
    return PermissionService::can('crm.*');
}
```

**Protection des resources dans un cluster :**
```php
// Dans app/Filament/Clusters/Crm/Resources/CompanyResource.php
public static function canViewAny(): bool
{
    return PermissionService::can('crm.companies.view');
}
```

**Logique de vérification wildcard :**
- `crm.*` → Donne accès à TOUTES les permissions `crm.*`
- `crm.companies.*` → Donne accès à toutes les actions sur companies
- Permission exacte → Donne accès à l'action spécifique

---

## 🔧 Configuration et Personnalisation

### Variables d'environnement

Aucune configuration spéciale requise. Le système utilise la configuration standard de Spatie Permission.

### Rôles par défaut

- **`admin`** : Accès complet (permission `admin.*`)
- **`user`** : Utilisateur de base
- **`crm_manager`** : Gestionnaire CRM
- **`product_manager`** : Gestionnaire produits

### Personnalisation des permissions

Modifiez le fichier `app/Console/Commands/SyncPermissions.php` pour personnaliser :

- Actions par défaut
- Permissions globales
- Logique de génération

---

## 🛡️ Sécurité et Bonnes Pratiques

### ✅ Recommandations

1. **Toujours utiliser PermissionService** dans les Resources Filament
2. **Principle of least privilege** : Donner le minimum de droits nécessaires
3. **Tester les permissions** après chaque modification
4. **Sauvegarder avant reset** : `php artisan db:dump` (si disponible)
5. **Utiliser les wildcards** pour simplifier la gestion

### ⚠️ Attention

- La commande `permissions:reset` est **irréversible**
- Les permissions sont **case-sensitive**
- Les **Clusters nécessitent canAccess()** pour être protégés
- Tester en **mode développement** avant production

### 🔒 Contrôles de sécurité

Le système vérifie automatiquement :
- Authentification utilisateur
- Existence des permissions
- Correspondance wildcard avec `fnmatch()`
- Fallback sur permissions exactes

---

## 🐛 Dépannage

### Problèmes fréquents

**1. Resource accessible à tous**
```bash
# Vérifiez que les méthodes can* sont présentes
grep -r "canViewAny" app/Filament/Resources/MaResource.php
```

**2. Permissions manquantes**
```bash
# Resynchroniser
php artisan permissions:sync --force
```

**3. Utilisateur sans droits**
```bash
# Vérifier les rôles
php artisan permissions:list --role=admin

# Assigner le rôle admin
php artisan tinker
>>> $user = App\Models\User::find(1);
>>> $user->assignRole('admin');
```

**4. Cache de permissions**
```bash
php artisan config:clear
php artisan cache:clear
```

### Logs et debugging

Activez le debugging dans `PermissionService` pour tracer les vérifications :

```php
// Dans config/app.php
'debug' => true,

// Les vérifications apparaîtront dans storage/logs/laravel.log
```

---

## 📚 Références

- **Spatie Permission** : [Documentation officielle](https://spatie.be/docs/laravel-permission)
- **Filament Authorization** : [Documentation Filament](https://filamentphp.com/docs/3.x/panels/resources#authorization)
- **Laravel Gates** : [Documentation Laravel](https://laravel.com/docs/11.x/authorization)

---

**🎯 Système créé le 21 Août 2025 pour x_filbreeze**  
**Maintenu par : Charles St-Olive**
