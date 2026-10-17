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

**État actuel — ouverts depuis le 17/10/2026 :** `filament-tma`, `filament-prism`, `filament-ui`,
`filament-permission-manager` (entrées `<nom>-local` en tête de `repositories`, les `vcs` restent dessous). `~/desapp`
est dans le même état. Ils restent ouverts d'une session à l'autre : la fermeture ci-dessous se fait quand le user
annonce un passage en production, pas en fin de session. D'ici là, ne pas committer `composer.json` ni
`composer.lock`. La procédure détaillée, commune aux deux apps, est dans `~/desapp/CLAUDE.md` (« Repasser en
production »).

**Fermeture (au passage en production) :**
1. Committer (et pousser si demandé) les changements dans le repo du package (`packages_filament/<package>`).
2. Retirer les entrées `path` (`-local`) de `composer.json` (retour à la config `vcs` d'origine).
3. `./vendor/bin/sail composer update charlesstolive/<package>` → réinstalle le vrai commit poussé depuis GitHub, `vendor/charlesstolive/<package>` redevient un dossier normal (plus de symlink).
4. Vérifier : `grep -c '"type": "path"' composer.lock` répond 0, plus aucun lien dans `vendor/charlesstolive` ;
   `docker restart filbreeze-laravel.test-1 filbreeze-queue-1 filbreeze-scheduler-1`, puis `sail test`.
5. Committer `composer.json`/`composer.lock` dans filbreeze.
- Si l'étape 3 échoue avec des erreurs SSH/`Permission denied (publickey)` sur un AUTRE package privé (ex. `filament-permission-manager`), c'est un souci d'auth GitHub de l'environnement (token/clé), pas une erreur de manip — le signaler plutôt que de contourner avec un hack (ex. extraire l'arbre Git localement).
