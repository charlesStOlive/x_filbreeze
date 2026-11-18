# filbreeze — instructions projet

## Contexte
- Projet Laravel, dossier réel `/home/charles/filbreeze` sous WSL (Ubuntu-22.04).
- Repo Git : remote `origin` = `https://github.com/charlesStOlive/x_filbreeze.git` (attention : nom du repo GitHub différent du nom du dossier local, `x_filbreeze` vs `filbreeze`). Branche courante habituelle : `init_crm`.

## Stack
- Laravel ^13.0, PHP ^8.4 (image Sail `sail-8.4/app`, imposé par spatie/laravel-model-states ≥ 2.13), Filament ~5.0 (admin panel).
- Environnement dev via **Laravel Sail** (`laravel/sail` ^1.52, `compose.yaml` à la racine).
- DB : MySQL (`DB_CONNECTION=mysql` dans le `.env` réel).
- Administration (Utilisateurs, Rôles, Permissions) et Profil : classes de `filament-permission-manager`,
  enregistrées par `FilamentPermissionManagerPlugin` dans le panel. Ne pas republier de copies dans `app/Filament`.
  `App\Models\User` étend le modèle du paquet et conserve sa relation `msgUserDraft()`.

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

## Packages filament-* maison : mode local / distant avec `bin/plugins` (depuis le 18/11/2026)
- Les sources de dev des packages `charlesstolive/*` vivent hors de ce repo, dans `~/packages_filament/<nom-du-package>`
  (chacun son repo Git et son remote GitHub, branche `main` ou `master`), jamais dans `vendor/`.
- Mode d'emploi complet : `docs/plugins-locaux.md` (le même que dans `~/desapp`). L'essentiel :
  - `composer.json` / `composer.lock` sont **toujours distants** (repositories `vcs` GitHub, comme en prod) : plus
    d'entrées `path` / `-local` à ajouter à la main. Le mode local vit dans `composer.local.json` /
    `composer.local.lock`, ignorés par git, recréés par `bin/plugins local` (tous les clones, sans jeton GitHub).
  - `bin/plugins` (état), `bin/plugins local`, `bin/plugins distant`, `bin/plugins publier [plugin…]`,
    `bin/plugins jeton`. Chaque commande qui change `vendor` redémarre l'app, la file et le scheduler.
  - Avant de modifier un package : `bin/plugins` doit dire « Mode : local », sinon `bin/plugins local`.
  - Publier : committer et pousser le clone, `bin/plugins publier`, `sail test`, committer `composer.lock`, puis
    `bin/plugins local`.
  - Hook `.githooks/pre-commit` (activé par `bin/plugins`) : refuse un `composer.json` / `composer.lock` qui pointe
    vers `../`.
- `docs/knowledge-base` est suivi par git ici : les scripts de composer y republient l'aide des plugins
  (`vendor:publish --force`). `bin/plugins` refuse de tourner s'il y a des modifications non commitées dedans, et liste
  ensuite ce qu'il a republié.
- Jeton GitHub : `~/.config/composer/auth.json` de l'hôte, monté en lecture seule dans `laravel.test` (`compose.yaml`).
  Il se renouvelle sur l'hôte (`composer config -g github-oauth.github.com <jeton>`), jamais par `sail composer`.
  Une erreur 401 / `Permission denied (publickey)` / « Host key verification failed » de composer = jeton expiré :
  `bin/plugins jeton`, et le signaler au user plutôt que de contourner.
