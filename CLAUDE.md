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

## Développement sur un package filament-* local (ex. filament-qonto)
- Les sources de dev des packages `charlesstolive/*` vivent hors de ce repo, dans `\\wsl.localhost\Ubuntu-22.04\home\charles\packages_filament\<nom-du-package>` (ex. `packages_filament/filament-qonto`), pas dans `vendor/`.
- `composer.json` déclare ces packages via des repositories `vcs` (GitHub) : par défaut `vendor/charlesstolive/<package>` est une install normale, pas un lien vers `packages_filament`.
- Pour travailler en local sur un de ces packages : ajouter temporairement un repository `path` (ex. `{"type": "path", "url": "../packages_filament/filament-qonto"}`, symlink activé par défaut pour un repo `path`) pointant vers le dossier du package dans `composer.json`, puis lancer `./vendor/bin/sail composer update charlesstolive/<package>` pour que `vendor/charlesstolive/<package>` devienne un symlink vers `packages_filament/<package>`. Sans ça, les modifications faites dans `packages_filament` ne sont pas prises en compte par l'app.
- Penser à revenir à la config `vcs` d'origine (ou committer/publier le package) une fois le dev terminé si le symlink ne doit pas rester en l'état.
