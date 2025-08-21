# 🏗️ Structure du Système de Permissions v2.0

> **Architecture complète du système de permissions hiérarchiques**

## 📋 Vue d'ensemble

Le système utilise une architecture hiérarchique basée sur les clusters Filament avec support wildcard pour une gestion granulaire des autorisations.

## 🎯 Structure Actuelle des Permissions

### 📊 Statistiques
- **74 permissions** totales
- **3 clusters** principaux
- **Resources autonomes**
- **4 rôles** configurés

## 🏗️ Clusters Hiérarchiques

### 🏢 Cluster CRM (`crm.*`)

```
crm.*                              ← Accès global au cluster CRM
├── crm.companies.*                ← Toutes actions sur companies
│   ├── crm.companies.view         ← Voir les companies
│   ├── crm.companies.create       ← Créer des companies
│   ├── crm.companies.edit         ← Modifier les companies
│   └── crm.companies.delete       ← Supprimer les companies
├── crm.contacts.*                 ← Toutes actions sur contacts
│   ├── crm.contacts.view
│   ├── crm.contacts.create
│   ├── crm.contacts.edit
│   └── crm.contacts.delete
└── crm.leads.*                    ← Toutes actions sur leads
    ├── crm.leads.view
    ├── crm.leads.create
    ├── crm.leads.edit
    └── crm.leads.delete
```

**Total : 13 permissions CRM**

### 📊 Cluster DataSets (`datasets.*`)

```
datasets.*                         ← Accès global aux datasets
├── datasets.categories.*          ← Gestion des catégories
│   ├── datasets.categories.view
│   ├── datasets.categories.create
│   ├── datasets.categories.edit
│   └── datasets.categories.delete
├── datasets.countries.*           ← Gestion des pays
│   ├── datasets.countries.view
│   ├── datasets.countries.create
│   ├── datasets.countries.edit
│   └── datasets.countries.delete
├── datasets.currencies.*          ← Gestion des devises
│   ├── datasets.currencies.view
│   ├── datasets.currencies.create
│   ├── datasets.currencies.edit
│   └── datasets.currencies.delete
├── datasets.languages.*           ← Gestion des langues
│   ├── datasets.languages.view
│   ├── datasets.languages.create
│   ├── datasets.languages.edit
│   └── datasets.languages.delete
└── datasets.timezones.*          ← Gestion des fuseaux horaires
    ├── datasets.timezones.view
    ├── datasets.timezones.create
    ├── datasets.timezones.edit
    └── datasets.timezones.delete
```

**Total : 21 permissions DataSets**

### 📱 Cluster MsGraph (`msgraph.*`)

```
msgraph.*                          ← Accès global Microsoft Graph
├── msgraph.calendar.*             ← Gestion du calendrier
│   ├── msgraph.calendar.view
│   ├── msgraph.calendar.create
│   ├── msgraph.calendar.edit
│   └── msgraph.calendar.delete
├── msgraph.contacts.*             ← Contacts Microsoft
│   ├── msgraph.contacts.view
│   ├── msgraph.contacts.create
│   ├── msgraph.contacts.edit
│   └── msgraph.contacts.delete
├── msgraph.drives.*               ← Gestion des lecteurs
│   ├── msgraph.drives.view
│   ├── msgraph.drives.create
│   ├── msgraph.drives.edit
│   └── msgraph.drives.delete
├── msgraph.mails.*                ← Gestion des e-mails
│   ├── msgraph.mails.view
│   ├── msgraph.mails.create
│   ├── msgraph.mails.edit
│   └── msgraph.mails.delete
└── msgraph.teams.*                ← Microsoft Teams
    ├── msgraph.teams.view
    ├── msgraph.teams.create
    ├── msgraph.teams.edit
    └── msgraph.teams.delete
```

**Total : 21 permissions MsGraph**

## 🎯 Resources Autonomes

### 👥 Gestion Système

```
users.*                            ← Gestion des utilisateurs
├── users.view                     ← Voir les utilisateurs
├── users.create                   ← Créer des utilisateurs
├── users.edit                     ← Modifier les utilisateurs
└── users.delete                   ← Supprimer les utilisateurs

permissions.*                      ← Gestion des permissions
├── permissions.view
├── permissions.create
├── permissions.edit
└── permissions.delete

roles.*                            ← Gestion des rôles
├── roles.view
├── roles.create
├── roles.edit
└── roles.delete

activity-log.*                     ← Journaux d'activité
├── activity-log.view
├── activity-log.create
├── activity-log.edit
└── activity-log.delete
```

**Total : 16 permissions autonomes**

### 🔒 Permissions Système

```
s_special_permission               ← Permission système protégée
s_system_config                    ← Configuration système
s_custom_feature                   ← Fonctionnalité personnalisée
```

**Note :** Les permissions commençant par `s_` sont automatiquement protégées contre la suppression.

## 👥 Structure des Rôles

### 🔑 Rôle Admin
- **Permissions :** TOUTES (74 permissions)
- **Accès :** Complet sur tous les clusters et resources
- **Wildcard :** `admin.*` (accès global)

### 👔 Rôle Manager
- **Permissions :** Sélectionnées selon le domaine
- **Accès :** Clusters spécifiques + certaines resources autonomes
- **Exemple :** `crm.*` + `users.view`

### 👨‍💼 Rôle CRM Manager
- **Permissions :** Cluster CRM uniquement
- **Accès :** `crm.*` (13 permissions)
- **Spécialisation :** Gestion commerciale

### 👤 Rôle User
- **Permissions :** Minimales
- **Accès :** Lecture seule sur certaines resources
- **Exemple :** `crm.companies.view`, `datasets.*.view`

## 🔄 Logique Wildcard

### Principe de Résolution

1. **Permission exacte** : `crm.companies.view` → Autorise l'action spécifique
2. **Wildcard resource** : `crm.companies.*` → Autorise toutes les actions sur companies
3. **Wildcard cluster** : `crm.*` → Autorise tout dans le cluster CRM
4. **Wildcard global** : `admin.*` → Autorise tout dans le système

### Exemples Pratiques

```php
// Vérification d'une permission spécifique
PermissionService::can('crm.companies.view')
// → true si : crm.companies.view OU crm.companies.* OU crm.* OU admin.*

// Vérification d'un cluster
PermissionService::can('crm.*') 
// → true si : crm.* OU admin.*

// Vérification globale
PermissionService::can('admin.*')
// → true seulement si : admin.*
```

## 📁 Organisation des Fichiers

### Fichiers de Configuration

```
app/Services/PermissionService.php     ← Service central de vérification
app/Console/Commands/
├── SyncPermissions.php                ← Synchronisation + nettoyage
├── GeneratePermissionSeeder.php       ← Génération de seeders
├── AddResourcePermissions.php         ← Ajout de nouvelles resources
├── ListPermissions.php                ← Listing et recherche
├── ResetPermissions.php               ← Reset complet
└── stubs/Permissions/
    └── permission_seeder.stub         ← Template pour génération seeders
```

### Clusters et Resources

```
app/Filament/Clusters/
├── Crm.php                           ← Cluster CRM
├── DataSets.php                      ← Cluster DataSets
└── MsGraph.php                       ← Cluster MsGraph

app/Filament/Clusters/Crm/Resources/
├── CompanyResource.php               ← Companies CRM
├── ContactResource.php               ← Contacts CRM
└── LeadResource.php                  ← Leads CRM

app/Filament/Resources/
├── UserResource.php                  ← Gestion utilisateurs
├── PermissionResource.php            ← Gestion permissions
└── RoleResource.php                  ← Gestion rôles
```

## 🌱 Seeders Générés

### Structure Type

```php
class PermissionSeeder extends Seeder
{
    public function run()
    {
        // Création par cluster
        $this->createCrmPermissions();
        $this->createDatasetsPermissions();
        $this->createMsgraphPermissions();
        $this->createStandalonePermissions();
        
        // Création des rôles et associations
        $this->createRoles();
    }
}
```

### Exemples de Génération

```bash
# Seeder complet (74 permissions + 4 rôles)
php artisan permissions:generate-seeder

# Seeder CRM uniquement (13 permissions)
php artisan permissions:generate-seeder --cluster=crm

# Seeder rôle admin uniquement
php artisan permissions:generate-seeder --role=admin
```

## 🔧 Maintenance et Évolution

### Ajout d'un Nouveau Cluster

1. **Créer le cluster Filament**
2. **Créer les resources dans le cluster**
3. **Synchroniser** : `php artisan permissions:sync`
4. **Générer le seeder** : `php artisan permissions:generate-seeder`

### Ajout d'une Resource Autonome

1. **Créer la resource Filament**
2. **Ajouter les permissions** : `php artisan permissions:add-resource nom`
3. **Intégrer le code** généré dans la resource
4. **Synchroniser** : `php artisan permissions:sync`

### Nettoyage Périodique

```bash
# Vérifier les permissions obsolètes
php artisan permissions:sync --cleanup --dry-run

# Nettoyer en mode interactif
php artisan permissions:sync --cleanup

# Automatiser le nettoyage
php artisan permissions:sync --cleanup --force
```

## 🚀 Évolutions Futures

### Fonctionnalités Prévues

- **Permissions temporaires** : Avec expiration automatique
- **Permissions conditionnelles** : Basées sur des critères métier
- **Audit avancé** : Tracking des changements de permissions
- **Interface graphique** : Pour la gestion visuelle des permissions

### Extensibilité

Le système est conçu pour être facilement extensible :
- Nouveaux clusters sans modification du code existant
- Patterns de permissions personnalisés
- Intégration avec d'autres systèmes d'autorisation
