# Guide de Déploiement Simplifié

## 🏠 DÉVELOPPEMENT LOCAL (Actuel)

Gardez votre configuration actuelle - elle fonctionne parfaitement pour le développement.

## 🚀 DÉPLOIEMENT PRODUCTION 

### Option A : Modification manuelle avant push (RECOMMANDÉE)

Avant de pousser votre code en production, modifiez manuellement le `composer.json` :

```json
{
  "repositories": {
    "filament-permission-manager": {
      "type": "vcs",
      "url": "https://github.com/charlesStOlive/filament-permission-manager.git"
    }
  },
  "require": {
    "charlesstolive/filament-permission-manager": "dev-main"
  }
}
```

### Option B : Script de déploiement automatique

Si vous avez un script de déploiement, ajoutez ces commandes :

```bash
# Dans votre script de déploiement
composer config --unset repositories.filament-permission-manager
composer config repositories.filament-permission-manager vcs https://github.com/charlesStOlive/filament-permission-manager.git
composer require charlesstolive/filament-permission-manager:dev-main --no-update
composer update --no-dev --optimize-autoloader
```

### Option C : Utiliser les scripts que j'ai créés

```bash
# Sur le serveur de production
.\composer-env.ps1 prod
composer update --no-dev --optimize-autoloader
```

## ⚠️ IMPORTANT

- Le dossier `/packages` n'existe pas en production
- Composer DOIT utiliser GitHub en production
- Votre script de déploiement DOIT faire cette transition

## 🎯 RECOMMANDATION

**Utilisez l'Option A** : Modifiez manuellement le composer.json avant chaque déploiement.
C'est le plus simple et le plus fiable.