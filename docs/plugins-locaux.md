# Plugins maison : mode local et mode distant

Les plugins `charlesstolive/*` s'installent depuis GitHub (dépôts `vcs` de `composer.json`). Pour les développer,
l'app doit utiliser leurs clones de travail, `../packages_filament/<plugin>`. `bin/plugins` passe d'un mode à
l'autre **sans jamais modifier `composer.json` ni `composer.lock`**.

## Le principe : deux fichiers composer, pas deux branches

| Fichiers                                      | Mode                                   | Dans git                       |
|-----------------------------------------------|----------------------------------------|--------------------------------|
| `composer.json` + `composer.lock`             | **distant** : GitHub, comme en prod    | commités, toujours distants    |
| `composer.local.json` + `composer.local.lock` | **local** : tous les plugins clonés    | ignorés, recréés par le script |

`bin/plugins local` recrée `composer.local.json` à partir de `composer.json` : un dépôt `path` en tête pour chaque
plugin qui a un clone, et sans les dépôts GitHub de ces plugins. Il copie `composer.lock` dans `composer.local.lock`,
puis lance `composer update` des seuls plugins avec `COMPOSER=composer.local.json`. Résultat :

- `vendor/charlesstolive/<plugin>` devient un lien vers le clone ; le reste de `vendor` est celui de la prod ;
- **le mode local ne parle jamais à GitHub** : il marche même avec un jeton expiré ;
- `git status` reste propre : on committe et on change de branche quand on veut.

Deux branches (une « locale », une « distante ») ne marcheraient pas : leurs `composer.json` / `composer.lock`
diffèrent, chaque fusion ferait un conflit ou ferait passer les `path` côté prod, et il faudrait doubler chaque branche.

## Au quotidien

| Commande                      | Ce qu'elle fait                                                                                     |
|-------------------------------|-----------------------------------------------------------------------------------------------------|
| `bin/plugins` ou `bin/plugins etat` | Pour chaque plugin : installé en local ou depuis GitHub, branche et commit du clone, travail non commité ou non poussé, en avance ou en retard sur le lock de prod. Puis le jeton GitHub : lu par le conteneur ? accepté ? date d'expiration ? |
| `bin/plugins local`           | Tous les plugins depuis leurs clones, puis redémarrage de l'app, de la file et du scheduler         |
| `bin/plugins distant`         | `composer install` sur le lock de prod : l'app tourne exactement comme en production                |
| `bin/plugins publier [tma …]` | Met `composer.lock` sur les commits poussés des clones (tous, ou ceux nommés) : à committer ensuite  |
| `bin/plugins jeton`           | Vérifie le jeton GitHub et dit comment le renouveler                                                |

Les options sont passées à composer : `bin/plugins local -W` si un plugin demande une autre version d'une dépendance.

Le cycle d'une modification de plugin :

1. `bin/plugins local` (une fois : l'app reste en local tant qu'on ne repasse pas en distant) ;
2. travailler dans `~/packages_filament/<plugin>`, sur sa branche `main` / `master` (composer prend la version
   `dev-main` / `dev-master` du nom de la branche du clone) ;
3. committer et pousser le clone ;
4. `bin/plugins publier` : il refuse si un clone a du travail non commité ou non poussé, ou n'est pas sur la bonne
   branche (et dit lequel) ; sinon il met `composer.lock` à jour depuis GitHub et vérifie qu'il a reçu les mêmes
   commits que les clones. `vendor` est alors celui de la prod ;
5. `sail test`, puis committer `composer.lock` ;
6. `bin/plugins local` pour reprendre le travail.

À savoir :

- **Ajouter une dépendance** : `sail composer require …` comme d'habitude (elle va dans `composer.json`). Cela remet
  les plugins sur GitHub : relancer ensuite `bin/plugins local`.
- **Après un `git pull` ou un changement de branche qui touche `composer.lock`** : `bin/plugins local` (ou `distant`).
- **Aide des plugins republiée** : les scripts de composer font `vendor:publish --force` de l'aide des plugins. Si
  `docs/knowledge-base` est suivi par git (filbreeze), `bin/plugins` refuse de tourner quand il y a des
  modifications non commitées dedans (`PLUGINS_FORCE=1` pour passer outre), et liste ensuite ce que composer a
  republié.

## Le jeton GitHub

Les plugins privés se téléchargent depuis GitHub avec le jeton de `~/.config/composer/auth.json`, **sur l'hôte**
(WSL). Chaque conteneur a son propre composer, mais tous lisent ce même fichier : `compose.yaml` le monte dans
`/home/sail/.config/composer/auth.json`, en lecture seule. Un seul jeton à renouveler, pour toutes les apps ;
`bin/plugins etat` vérifie que le conteneur voit bien le même.

Seuls `bin/plugins distant`, `bin/plugins publier` (et le déploiement) en ont besoin, pas le mode local.

Le renouveler :

1. <https://github.com/settings/personal-access-tokens/new> : *Repository access* « All repositories » (les
   prochains plugins marcheront sans nouveau jeton), *Permissions* « Contents » en **Read-only** (composer ne fait
   que lire), l'expiration la plus longue proposée ;
2. **sur l'hôte, pas dans `sail`** (le fichier est en lecture seule dans les conteneurs) :
   `composer config -g github-oauth.github.com <le_jeton>`. Sans composer sur l'hôte : écrire
   `{"github-oauth": {"github.com": "<le_jeton>"}}` dans `~/.config/composer/auth.json`.

Les conteneurs le voient aussitôt, sans redémarrage. `bin/plugins etat` prévient 15 jours avant l'expiration.

La production a son propre jeton (son `auth.json` ou `COMPOSER_AUTH`) : il expire de la même façon.

## Les garde-fous

- **Hook git `pre-commit`** (`.githooks/pre-commit`) : refuse un `composer.json` / `composer.lock` qui pointe vers
  `../` (un clone local). `bin/plugins` l'active (`git config core.hooksPath .githooks`), à chaque nouveau clone de
  l'app il suffit de lancer `bin/plugins etat` une fois.
- `bin/plugins publier` refuse de mettre dans le lock un clone non commité, non poussé ou sur une autre branche.
- `bin/plugins etat` signale un `composer.json` / `composer.lock` en mode `path` (l'ancienne méthode).

## Mettre en place dans une autre app

1. Copier `bin/plugins` et `.githooks/pre-commit` (identiques dans chaque app, rien n'y est propre à une app), et ce
   fichier `docs/plugins-locaux.md`.
2. `.gitignore` :
   ```
   /composer.local.json
   /composer.local.lock
   ```
3. `compose.yaml` :
   - sur le service de l'app :
     `- '${HOME}/.config/composer/auth.json:/home/sail/.config/composer/auth.json:ro'` ;
   - sur chaque service qui lance le PHP de l'app (app, file, scheduler) :
     `- '../packages_filament:/var/www/packages_filament'`, pour que les liens de `vendor` mènent quelque part.
4. `composer.json` : les plugins en dépôts `vcs` (GitHub) seulement, plus aucune entrée `path`.
5. `sail up -d`, puis `bin/plugins etat` (il active le hook) et `bin/plugins local`.

Réglages, dans `.env` ou l'environnement, tous facultatifs :

| Variable                 | Par défaut              | Rôle                                                            |
|--------------------------|-------------------------|-----------------------------------------------------------------|
| `APP_SERVICE`            | `laravel.test`          | le service de l'app (celui de Sail)                             |
| `PLUGINS_DIR`            | `../packages_filament`  | le dossier des clones, relatif à l'app (le même dans le conteneur) |
| `PLUGINS_VENDOR`         | `charlesstolive`        | le préfixe des paquets concernés                                |
| `PLUGINS_PUBLISHED_DIRS` | `docs/knowledge-base`   | les dossiers que les scripts de composer réécrivent             |
| `PLUGINS_FORCE`          | —                       | `1` : passer outre l'aide non commitée ou le jeton refusé       |

Les services à redémarrer sont trouvés seuls : ceux qui tournent sur la même image que l'app.

## Si ça coince

- **composer demande un jeton, 401, « Host key verification failed »** : jeton expiré, `bin/plugins jeton`.
- **« le clone est sur … , composer attend … »** : remettre le clone sur `main` / `master`.
- **« en retard sur la prod »** : le lock de prod a un commit plus récent que le clone, `git pull` dans le clone.
- **Mode « mixte » dans `bin/plugins etat`** : quelqu'un a lancé `sail composer install` / `require` : relancer
  `bin/plugins local` (ou `distant`).
