# 📚 Documentation

Ce dossier contient toute la documentation du projet x_filbreeze avec un focus sur le système de permissions wildcard.

## � Système de Gestion d'États (Model States)

### 📖 [MODEL_STATES_GUIDE.md](./MODEL_STATES_GUIDE.md)
**Guide complet** du système d'états basé sur Spatie Laravel Model States + FilamentStateFusion.

**Contenu :**
- Vue d'ensemble et architecture
- Guide de démarrage rapide avec commande `make:states`
- États, transitions et formulaires conditionnels
- Fonctionnalités avancées (redirections, debug)
- Système d'override pour corrections bugs plugin
- Templates et personnalisation UI

### 🎯 [MODEL_STATES_EXAMPLE.md](./MODEL_STATES_EXAMPLE.md)
**Exemple pratique complet** avec SupplierInvoice généré par la commande.

**Contenu :**
- Exemple de génération complète avec `make:states SupplierInvoice`
- Configuration modèle, migration et Filament Resource
- Personnalisations avancées (formulaires, redirections, couleurs)
- Utilisation programmatique et points d'attention

## �🔐 Système de Permissions v2.0

### 📖 [PERMISSIONS_DOCUMENTATION.md](./PERMISSIONS_DOCUMENTATION.md)
**Documentation complète** du système de permissions wildcard avec structure hiérarchique.

**Contenu :**
- Vue d'ensemble et philosophie du système
- Guide complet des commandes Artisan (sync, cleanup, seeder)
- Structure hiérarchique par clusters
- Architecture et intégration Filament
- Workflows recommandés et sécurité

### 🚀 [PERMISSIONS_QUICK_REFERENCE.md](./PERMISSIONS_QUICK_REFERENCE.md)
**Référence rapide** pour usage quotidien avec nouvelles fonctionnalités.

**Contenu :**
- Commandes essentielles + génération de seeders
- Workflow nouvelle resource
- Structure hiérarchique des permissions
- Filtrage et nettoyage automatique

### 🌱 [SEEDER_GENERATION.md](./SEEDER_GENERATION.md)
**Guide complet** de la génération automatique de seeders pour le déploiement.

**Contenu :**
- Utilisation de `permissions:generate-seeder`
- Filtrage avancé par cluster, rôle, type
- Workflow de déploiement multi-environnements
- Exemples concrets et bonnes pratiques

### 🔧 [PERMISSIONS_CUSTOM_EXAMPLE.md](./PERMISSIONS_CUSTOM_EXAMPLE.md)
Exemples de commandes personnalisées et extensions du système.

**Contenu :**
- Code complet d'une commande avancée
- Gestion des permissions par module
- Idées d'extensions

### � [CHANGELOG.md](./CHANGELOG.md)
**Historique détaillé** des versions et changements.

**Contenu :**
- Version 2.0 : Structure hiérarchique + seeders
- Version 1.1 : Nettoyage automatique
- Nouvelles fonctionnalités et améliorations

## �🚀 Démarrage Rapide

### Commandes Essentielles

```bash
# Synchroniser toutes les permissions (hiérarchiques)
php artisan permissions:sync

# Nettoyage automatique des permissions obsolètes
php artisan permissions:sync --cleanup --dry-run

# Ajouter une nouvelle resource
php artisan permissions:add-resource nomResource

# Générer un seeder pour déploiement
php artisan permissions:generate-seeder

# Lister les permissions
php artisan permissions:list
```

### Structure Hiérarchique

```
crm.*                    ← Accès total cluster CRM
├── crm.companies.*      ← Toutes actions companies
├── crm.contacts.*       ← Toutes actions contacts
datasets.*               ← Accès total cluster DataSets
users.*                  ← Gestion utilisateurs (autonome)
```

## 📊 État Actuel du Système

- **74 permissions** organisées hiérarchiquement
- **3 clusters** : CRM, DataSets, MsGraph
- **4 rôles** : admin, manager, user, crm_manager
- **Seeders automatiques** pour déploiement
- **Nettoyage intelligent** des permissions obsolètes

## 🎯 Fonctionnalités Principales

### 🏗️ Structure Hiérarchique
- Permissions par cluster : `cluster.resource.action`
- Permissions globales : `cluster.*` ou `resource.*`
- Compatibilité avec resources autonomes

### 🌱 Génération de Seeders
- Création automatique pour déploiement
- Filtrage par cluster, rôle, type
- Structure idempotente

### 🧹 Nettoyage Automatique
- Détection des permissions obsolètes
- Protection des permissions système (`s_*`)
- Mode simulation et nettoyage sécurisé

## 📝 Contributions

Pour contribuer à la documentation :

1. Modifier les fichiers Markdown appropriés
2. Respecter la structure existante
3. Ajouter des exemples pratiques
4. Tester les commandes documentées

---

**🏠 Retour au projet :** [../README.md](../README.md)
