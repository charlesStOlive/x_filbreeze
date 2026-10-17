# Déploiement — refonte du module TMA (16/10/2026)

Note pour le **prochain déploiement de Filbreeze en production**. Elle complète la section « Production » du
[README](README.md) ; à supprimer une fois ce déploiement fait.

## Ce qui part

La refonte du plugin `charlesstolive/filament-tma` (voir son `docs/ADDENDUM.md`, §2, §3, §3 bis) :

- un seul circuit, du client au Support ; le traitement interne est retiré ;
- la règle du tour dans le moteur, les forçages, « Prendre la main », le contact au Support choisi à la transmission ;
- de nouveaux droits : `tma.ticket.correspond`, `tma.ticket.handle`, `tma.ticket.force-close`,
  `tma.ticket.force-return` (plus de `tma.ticket.workflow.*` ni `tma.ticket.comment`) ;
- **les migrations du module remises à plat** : 5 migrations `create_tma_*` remplacent les 18 anciennes.

La production a déjà l'ancienne version du module : ses tables `tma_*` existent. Il faut donc **remettre le schéma
à plat une fois**, avec `tma:reset-schema`, au lieu de laisser `migrate` créer les nouvelles tables.

| Ce qui est gardé | Ce qui est perdu |
| --- | --- |
| les applications clientes déclarées, les membres du Support reliés, les forfaits ; les utilisateurs, les rôles, les jetons | les tickets, tâches, projets, messages, notes et temps du module (des données de test) |

## 1. Avant de déployer (poste de dev)

1. **Pousser les plugins** qui ont des commits non poussés :
   ```bash
   cd ~/packages_filament/filament-tma && git push -u origin master   # 3 commits, dont la refonte
   cd ~/packages_filament/filament-ui  && git push                    # 1 commit (->onDemand())
   ```
2. **Remettre Filbreeze en dépôts GitHub.** `composer.json` pointe aujourd'hui vers les clones locaux
   (`../packages_filament`, dépôts `path`) pour 7 plugins. Remettre les entrées `vcs` (modèle :
   `composer-vcs-path.json`, et la marche à suivre dans `PRODUCTION_CONFIG.md`), puis :
   ```bash
   ./vendor/bin/sail composer update "charlesstolive/*" --minimal-changes
   ./vendor/bin/sail artisan test
   ```
   Vérifier que `composer.lock` ne contient plus aucune distribution `path`.
3. **Committer** `composer.json` et `composer.lock`, puis **pousser la branche** qui part en production.

## 2. Sur le serveur

Faire une **sauvegarde de la base** avant tout.

Puis la procédure du README, avec une seule différence : **`tma:reset-schema` à la place de `migrate`** (la
commande lance elle-même `migrate` une fois les tables du module supprimées).

```bash
git pull                                                   # ou l'outil de déploiement
./vendor/bin/sail composer install --no-dev --optimize-autoloader
./vendor/bin/sail npm ci && ./vendor/bin/sail npm run build

./vendor/bin/sail artisan tma:reset-schema --force         # AVANT tout migrate
./vendor/bin/sail artisan permissions:sync --force

./vendor/bin/sail artisan config:cache
./vendor/bin/sail artisan route:cache
./vendor/bin/sail artisan view:cache
./vendor/bin/sail artisan queue:restart
```

(Sur un serveur sans Sail : `php artisan …`, `composer …`.)

> ⚠️ **Si le déploiement lance `migrate` tout seul** (script, outil de déploiement), le désactiver pour cette fois,
> ou faire passer `tma:reset-schema --force` avant lui. Sinon les nouvelles migrations essaient de créer des tables
> qui existent déjà, et `migrate` échoue.

> **Toujours le même utilisateur que les fichiers.** Le script `post-autoload-dump` de `composer.json` republie l'aide
> (`vendor:publish --tag=filament-tma-docs --force`) en écrasant `docs/knowledge-base/fr/tma`. Si un de ces fichiers
> appartient à un autre utilisateur (root, par une commande lancée en root), Composer s'arrête sur « Unable to write
> file at location: to://tma/… ». Lancer `composer` et `artisan` avec l'utilisateur propriétaire du projet (en Sail :
> `sail …`, ou `docker exec -u sail …`, jamais `docker exec` seul), et en cas d'erreur, rendre les fichiers à cet
> utilisateur (`chown`).

`tma:reset-schema` vérifie d'abord que les 5 nouvelles migrations sont bien dans `database/migrations` et que les
anciennes n'y sont plus (c'est le cas dans le commit déployé) ; sinon, il s'arrête sans rien toucher.

## 3. Les droits

**Retirer les anciennes permissions du module**, que `permissions:sync` ne supprime pas (éviter
`permissions:sync --cleanup` : il propose aussi des suppressions hors du module) :

```bash
./vendor/bin/sail artisan tinker --execute='Spatie\Permission\Models\Permission::where("name","like","tma.ticket.workflow.%")->orWhereIn("name",["tma.ticket.comment","tma.ticket.share-note"])->get()->each->delete(); app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();'
```

**Vérifier les rôles** (écran des rôles) :

- les membres du Support ont `tma.ticket.handle` ; les administrateurs, en plus, `tma.ticket.force-close` et
  `tma.ticket.force-return` ;
- un rôle qui a le joker `tma.ticket.*` (Super Admin) a déjà tout.

`TMA_SUPPORT_DEFAULTS` (`.env`) vaut `charles@notilac.fr` par défaut : c'est le contact d'office d'un ticket transmis
sans contact choisi.

## 4. Vérifier

- **Support › Tickets** s'ouvre (liste vide) ; **Support › Applications clientes** a gardé ses applications.
- `./vendor/bin/sail artisan migrate:status | grep tma` : les 5 migrations `create_tma_*` passées, aucune ancienne.
- `./vendor/bin/sail artisan tma:list --token=…` répond.
- La file tourne de nouveau (`queue:restart` fait), le planificateur aussi.

## Retour arrière

Restaurer la sauvegarde de la base, puis redéployer le commit précédent (avec son `composer.lock`).

## Desapp

Pas concerné tant qu'il n'est pas en production avec le module. Le jour où il y passera, même chose : dépôts GitHub
au lieu des dépôts `path` (branche `tma`), puis `tma:reset-schema --force` si une ancienne version du module y est
installée, sinon un simple `migrate`.
