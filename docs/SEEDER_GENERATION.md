# 🌱 Génération Automatique de Seeders

> **Guide complet pour la génération et le déploiement de seeders de permissions**

## 📋 Vue d'ensemble

La commande `permissions:generate-seeder` permet de créer automatiquement des seeders contenant toutes les permissions et rôles actuels, facilitant le déploiement sur différents environnements.

## 🚀 Utilisation Basique

### Seeder complet

```bash
php artisan permissions:generate-seeder
```

**Résultat :** Génère `database/seeders/PermissionSeeder.php` avec :
- Toutes les permissions organisées par cluster
- Tous les rôles avec leurs associations
- Structure idempotente

### Paramètres disponibles

```bash
php artisan permissions:generate-seeder [options]
```

| Option | Description | Défaut |
|--------|-------------|--------|
| `--file=nom` | Nom du fichier seeder | `PermissionSeeder` |
| `--path=chemin` | Dossier de destination | `database/seeders` |
| `--cluster=nom` | Filtrer par cluster | Tous |
| `--role=nom` | Filtrer par rôle | Tous |
| `--permissions-only` | Seulement les permissions | Non |
| `--roles-only` | Seulement les rôles | Non |
| `--with-fake-users` | Inclure des utilisateurs de test | Non |

## 🎯 Exemples de Filtrage

### Par cluster

```bash
# Seeder pour le cluster CRM uniquement
php artisan permissions:generate-seeder --cluster=crm --file=CrmPermissionSeeder

# Seeder pour le cluster DataSets
php artisan permissions:generate-seeder --cluster=datasets --file=DataSetsSeeder
```

**Contenu généré :**
- Permissions `crm.*` et toutes les sous-permissions
- Rôles avec leurs permissions CRM uniquement

### Par rôle

```bash
# Seeder pour le rôle admin uniquement
php artisan permissions:generate-seeder --role=admin --file=AdminRoleSeeder

# Seeder pour un rôle manager
php artisan permissions:generate-seeder --role=manager --file=ManagerSeeder
```

### Utilisateurs de test

```bash
# Inclure des utilisateurs de test avec l'option
php artisan permissions:generate-seeder --with-fake-users

# Ou répondre "yes" à la question interactive
php artisan permissions:generate-seeder
# → Voulez-vous inclure la création de faux utilisateurs de test ? (yes/no) [no]: yes
```

**Utilisateurs créés automatiquement :**
- `admin@test.com` - Utilisateur admin avec tous les droits
- `manager@test.com` - Utilisateur manager (si le rôle existe)
- `user@test.com` - Utilisateur basique
- Et un utilisateur pour chaque rôle existant

**Mot de passe par défaut :** `password`

### Par type de contenu

```bash
# Seulement les permissions (sans les rôles)
php artisan permissions:generate-seeder --permissions-only --file=PermissionsOnlySeeder

# Seulement les rôles (sans les permissions)
php artisan permissions:generate-seeder --roles-only --file=RolesOnlySeeder
```

## 📂 Structure du Seeder Généré

### Structure du seeder généré

**Architecture basée sur des stubs :**
- Utilisation d'un fichier `app/Console/Commands/stubs/Permissions/permission_seeder.stub` pour la génération
- Remplacements dynamiques selon les options sélectionnées
- Structure modulaire et facilement adaptable
- Organisation spécifique à la commande pour une meilleure maintenance

**En-tête automatique :**

```php
/**
 * Seeder généré automatiquement le 2025-08-21 15:30:00
 * 
 * Ce seeder contient toutes les permissions et rôles du système
 * avec leurs associations[, ainsi que des utilisateurs de test].
 * 
 * Généré avec: php artisan permissions:generate-seeder [options]
 * 
 * Statistiques:
 * - 74 permission(s)
 * - 4 rôle(s)
 * [- Utilisateurs de test inclus]
 */
```

### Méthodes générées automatiquement

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
 * 
 * Filtres appliqués : aucun
 */
class PermissionSeeder extends Seeder
{
    public function run()
    {
        // Création des permissions par groupe
        $this->createCrmPermissions();
        $this->createDatasetsPermissions();
        $this->createMsgraphPermissions();
        $this->createStandalonePermissions();

        // Création des rôles et associations
        $this->createRoles();
    }
}
```

### Méthodes de création par cluster

```php
private function createCrmPermissions()
{
    $permissions = [
        'crm.*',
        'crm.companies.*',
        'crm.companies.view',
        'crm.companies.create',
        'crm.companies.edit',
        'crm.companies.delete',
        // ... autres permissions CRM
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web'
        ]);
    }
}
```

### Méthodes de création des rôles

```php
private function createRoles()
{
    // Création du rôle admin
    $adminRole = Role::firstOrCreate([
        'name' => 'admin',
        'guard_name' => 'web'
    ]);

    // Attribution de toutes les permissions à admin
    $adminRole->syncPermissions(Permission::all());

    // Autres rôles...
}
```

### Gestion des utilisateurs de test

```php
/**
 * Créer des utilisateurs de test
 */
private function createFakeUsers(): void
{
    // Créer un utilisateur admin de test
    $adminUser = User::firstOrCreate(
        ['email' => 'admin@test.com'],
        [
            'name' => 'Admin Test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]
    );
    $adminUser->assignRole('admin');

    // Créer d'autres utilisateurs pour chaque rôle...
}
```

**Fonctionnalités des utilisateurs de test :**
- Emails prédictibles : `[role]@test.com`
- Mot de passe uniforme : `password`
- Comptes vérifiés automatiquement
- Assignation automatique des rôles correspondants
- Évite les doublons avec `firstOrCreate()`

## 🔄 Workflow de Déploiement

### 1. Environnement source (développement)

```bash
# Générer le seeder avec toutes les permissions actuelles
php artisan permissions:generate-seeder --file=ProductionPermissions

# Vérifier le contenu généré
cat database/seeders/ProductionPermissions.php
```

### 2. Transfert vers l'environnement cible

```bash
# Copier le fichier vers l'environnement de production
scp database/seeders/ProductionPermissions.php user@prod:/path/to/app/database/seeders/
```

### 3. Déploiement sur l'environnement cible

```bash
# Option 1 : Ajouter dans DatabaseSeeder.php
echo '$this->call(ProductionPermissions::class);' >> database/seeders/DatabaseSeeder.php
php artisan db:seed

# Option 2 : Exécuter directement
php artisan db:seed --class=ProductionPermissions
```

## 🛡️ Caractéristiques de Sécurité

### Idempotence

Le seeder peut être exécuté plusieurs fois sans problème :
- `firstOrCreate()` évite les doublons
- `syncPermissions()` gère les associations proprement

### Gestion des erreurs

```php
try {
    Permission::firstOrCreate(['name' => $permission]);
} catch (Exception $e) {
    // Log et continue
}
```

### Validation des données

- Vérification de l'existence des permissions avant création
- Validation des noms de rôles
- Protection contre les données malformées

## 📊 Statistiques et Monitoring

### Sortie de la commande

```bash
$ php artisan permissions:generate-seeder --cluster=crm

🌱 Génération du seeder de permissions...
📋 Trouvé 36 permission(s) et 4 rôle(s)
✅ Seeder généré avec succès : /path/to/CrmPermissionSeeder.php

Pour utiliser ce seeder :
1. Ajouter 'CrmPermissionSeeder::class,' dans DatabaseSeeder.php
2. Exécuter : php artisan db:seed --class=CrmPermissionSeeder
3. Ou exécuter : php artisan db:seed

🔍 Filtré pour le cluster : crm
```

### Métadonnées dans le seeder

Chaque seeder généré contient :
- Timestamp de génération
- Nombre de permissions et rôles
- Filtres appliqués
- Instructions d'utilisation

## 🔧 Cas d'Usage Avancés

### Déploiement par phases

```bash
# Phase 1 : Permissions essentielles
php artisan permissions:generate-seeder --permissions-only --file=Step1Permissions

# Phase 2 : Rôles administratifs
php artisan permissions:generate-seeder --role=admin --file=Step2AdminRole

# Phase 3 : Rôles utilisateurs
php artisan permissions:generate-seeder --roles-only --file=Step3UserRoles
```

### Environnements spécialisés

```bash
# Environnement CRM uniquement
php artisan permissions:generate-seeder --cluster=crm --file=CrmOnlySeeder

# Environnement sans cluster msgraph
php artisan permissions:generate-seeder --cluster=crm --cluster=datasets --file=NoMsgraphSeeder
```

### Sauvegarde avant migration

```bash
# Sauvegarder l'état actuel avant changements
php artisan permissions:generate-seeder --file=BackupBeforeMigration_$(date +%Y%m%d)
```

## ⚠️ Bonnes Pratiques

### 1. Tests avant déploiement

```bash
# Tester le seeder sur un environnement de test
php artisan db:seed --class=ProductionPermissions --env=testing
```

### 2. Versionning des seeders

```bash
# Inclure la version dans le nom
php artisan permissions:generate-seeder --file=PermissionSeeder_v2_1_0
```

### 3. Documentation des changements

Chaque seeder généré doit être accompagné d'une note expliquant :
- Les changements par rapport à la version précédente
- Les nouveaux rôles/permissions ajoutés
- Les instructions de rollback si nécessaire

### 4. Monitoring post-déploiement

```bash
# Vérifier que toutes les permissions sont présentes
php artisan permissions:list

# Vérifier les associations des rôles
php artisan permission:show
```
