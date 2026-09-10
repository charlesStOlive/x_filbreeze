# filbreeze — instructions projet

## Contexte
- Projet Laravel, dossier réel `/home/charles/filbreeze` sous WSL (Ubuntu-22.04).
- Repo Git : remote `origin` = `https://github.com/charlesStOlive/x_filbreeze.git` (attention : nom du repo GitHub différent du nom du dossier local, `x_filbreeze` vs `filbreeze`). Branche courante habituelle : `init_crm`.

## Stack
- Laravel ^12.0, PHP ^8.3, Filament ~5.0 (admin panel).
- Environnement dev via **Laravel Sail** (`laravel/sail` ^1.52, `compose.yaml` à la racine).
- DB : MySQL (`DB_CONNECTION=mysql` dans le `.env` réel).

## Lancer l'environnement
```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm run dev
```

## Git
- Au 2026-09-10 : repo propre, sur branche `init_crm` (pas `master`) — vérifier avant de commit/push sur quelle branche on travaille réellement.

## Règle impérative : toujours passer par Sail
- Ne jamais exécuter `php`, `artisan`, `composer`, `npm`, `phpunit`, etc. directement sur l'hôte : tout passe par `./vendor/bin/sail ...` (ex. `./vendor/bin/sail artisan test`, `./vendor/bin/sail composer update ...`).
- Si Sail n'est pas démarré (`./vendor/bin/sail up -d` nécessaire), le lancer avant toute commande — les conteneurs (`laravel.test`, `mysql`, `mailpit`) doivent tourner.

## Développement sur un package filament-* local (ex. filament-qonto) — routine à appliquer sans demander
- Les sources de dev des packages `charlesstolive/*` vivent hors de ce repo, dans `\\wsl.localhost\Ubuntu-22.04\home\charles\packages_filament\<nom-du-package>` (ex. `packages_filament/filament-qonto`), pas dans `vendor/`. Chacun est son propre repo Git avec son propre remote GitHub (ex. `charlesStOlive/filament-qonto.git`, branche `master`).
- `composer.json` déclare ces packages via des repositories `vcs` (GitHub). C'est l'état par défaut/prod : `vendor/charlesstolive/<package>` est une install normale (pas un lien).

**Ouverture (dès qu'on va modifier le code d'un de ces packages) :**
1. Ajouter un repository `path` dans `composer.json`, juste avant les repositories `vcs` : `{"type": "path", "url": "../packages_filament/<package>", "options": {"symlink": true}}`.
2. `./vendor/bin/sail composer update charlesstolive/<package>` → `vendor/charlesstolive/<package>` devient un symlink vers `packages_filament/<package>`. Sans ça, les modifs faites dans `packages_filament` ne sont pas vues par l'app.
3. Éditer directement dans `packages_filament/<package>` (c'est le vrai repo Git du package, pas un scratch dir).

**Fermeture (dès que le dev sur le package est terminé pour la session, avant de considérer que c'est fini/prod) — à faire automatiquement, sans repasser par une question :**
1. Committer (et pousser si demandé) les changements dans le repo du package (`packages_filament/<package>`).
2. Retirer l'entrée `path` de `composer.json` (retour à la config `vcs` d'origine).
3. `./vendor/bin/sail composer update charlesstolive/<package>` → réinstalle le vrai commit poussé depuis GitHub, `vendor/charlesstolive/<package>` redevient un dossier normal (plus de symlink).
4. Committer `composer.json`/`composer.lock` dans filbreeze.
- Si l'étape 3 échoue avec des erreurs SSH/`Permission denied (publickey)` sur un AUTRE package privé (ex. `filament-permission-manager`), c'est un souci d'auth GitHub de l'environnement (token/clé), pas une erreur de manip — le signaler plutôt que de contourner avec un hack (ex. extraire l'arbre Git localement).
