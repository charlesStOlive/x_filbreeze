# 🚀 Permissions - Référence Rapide

## Commandes Essentielles

```bash
# 🔄 Synchroniser toutes les permissions
php artisan permissions:sync

# 🧹 Synchroniser et nettoyer les permissions obsolètes
php artisan permissions:sync --cleanup

# 👀 Voir ce qui serait nettoyé (simulation)
php artisan permissions:sync --cleanup --dry-run

# 🚀 Nettoyage automatique sans confirmation
php artisan permissions:sync --cleanup --force

# ➕ Ajouter une nouvelle resource
php artisan permissions:add-resource nomResource

# 📋 Lister les permissions
php artisan permissions:list

# 🌱 Générer un seeder pour déploiement
php artisan permissions:generate-seeder

# 🗑️ Reset complet (ATTENTION !)
php artisan permissions:reset
```

## 🌱 Génération de Seeders

### Commandes de génération

```bash
# Seeder complet (toutes permissions + tous rôles)
php artisan permissions:generate-seeder

# Seeder pour un cluster spécifique
php artisan permissions:generate-seeder --cluster=crm --file=CrmSeeder

# Seeder pour un rôle spécifique  
php artisan permissions:generate-seeder --role=admin --file=AdminSeeder

# Seulement les permissions
php artisan permissions:generate-seeder --permissions-only

# Seulement les rôles
php artisan permissions:generate-seeder --roles-only
```

### 🎯 Options de filtrage

- **--cluster=nom** : Filtrer par cluster (crm, datasets, msgraph)
- **--role=nom** : Filtrer par rôle (admin, manager, user)
- **--permissions-only** : Exclure les rôles du seeder
- **--roles-only** : Exclure les permissions du seeder
- **--file=nom** : Nom personnalisé du fichier seeder
- **--path=chemin** : Dossier de destination personnalisé

## 🧹 Nettoyage Automatique

### Commandes de nettoyage

```bash
# Simulation complète (recommandé avant nettoyage)
php artisan permissions:sync --cleanup --dry-run

# Nettoyage interactif avec confirmations
php artisan permissions:sync --cleanup

# Nettoyage automatique pour scripts
php artisan permissions:sync --cleanup --force
```

### 🛡️ Protections automatiques

- **Permissions système** : Préfixe `s_*` → **jamais supprimées**
- **Permissions actives** : Encore assignées → **confirmation requise**
- **Dry-run** : Mode simulation → **rien n'est supprimé**

## 🏗️ Structure Hiérarchique

### Format des permissions

**Clusters :**
- `cluster.*` → Accès global au cluster
- `cluster.resource.*` → Accès complet à une resource
- `cluster.resource.action` → Action spécifique

**Resources autonomes :**
- `resource.*` → Accès complet
- `resource.action` → Action spécifique

### Exemples concrets

```
crm.*                    ← Accès total CRM
├── crm.companies.*      ← Toutes actions companies
│   ├── crm.companies.view
│   ├── crm.companies.create
│   └── crm.companies.edit
datasets.*               ← Accès total datasets
users.*                  ← Gestion utilisateurs (autonome)
```

### Logique wildcard

- `crm.*` → Donne accès à TOUT le cluster CRM
- `crm.companies.*` → Donne accès à toutes les actions companies
- Permission exacte → Donne accès à l'action spécifique

## Workflow Nouvelle Resource

1. **Créer les permissions :**
   ```bash
   php artisan permissions:add-resource client
   ```

2. **Copier le code généré dans votre Resource :**
   ```php
   use App\Services\PermissionService;
   
   public static function canViewAny(): bool
   {
       return PermissionService::can('client.view');
   }
   ```

3. **Protéger le Cluster (si applicable) :**
   ```php
   public static function canAccess(): bool
   {
       return PermissionService::can('client.*');
   }
   ```

4. **Synchroniser et nettoyer :**
   ```bash
   php artisan permissions:sync --cleanup --dry-run
   ```

## Structure des Permissions

```
resource.*           # Accès complet à la resource
resource.view        # Consultation
resource.create      # Création  
resource.edit        # Modification
resource.delete      # Suppression
```

## Rôles par Défaut

- **admin** : Tous les droits (`admin.*`)
- **user** : Utilisateur de base
- **crm_manager** : Gestionnaire CRM
- **product_manager** : Gestionnaire produits

## Dépannage Rapide

```bash
# Resource accessible à tous ?
# → Ajouter les méthodes can* dans la Resource

# Permissions manquantes ?
php artisan permissions:sync --force

# Nettoyer les permissions obsolètes ?
php artisan permissions:sync --cleanup

# Voir ce qui serait supprimé ?
php artisan permissions:sync --dry-run

# Cache de permissions ?
php artisan config:clear

# Vérifier un utilisateur ?
php artisan permissions:list --role=admin
```

## 🛡️ Exclusions Automatiques

Les permissions commençant par **`s_`** sont automatiquement **exclues** du nettoyage :
- `s_special_permission` ✅ Préservée
- `s_system_config` ✅ Préservée  
- `s_custom_feature` ✅ Préservée

Utilisez ce préfixe pour vos **permissions système spéciales** !

---
**📚 Documentation complète :** `PERMISSIONS_DOCUMENTATION.md`
