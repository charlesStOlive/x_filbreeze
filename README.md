# Filbreeze

Application Laravel avec interface Filament, exécutée avec Laravel Sail et Docker.

## Stack serveur

Versions de référence définies dans Docker et vérifiées dans le conteneur Sail:

| Composant | Version |
| --- | --- |
| Système de base Docker | Ubuntu 24.04 |
| PHP | 8.3 |
| Laravel | 12.66.0 |
| Node.js | 24 LTS (`24.19.0` vérifié) |
| npm | `10.8.2` |
| Tailwind CSS | 4.x (`^4.1.13`) |
| Vite | 6.x (`^6.4.3`) |
| MySQL | 8.0.40 |
| Puppeteer | 25.8.0 |
| Browsershot | 5.x (`5.4.0` verrouillé) |
| Mailpit | latest (développement uniquement) |

La version Node est définie par `NODE_VERSION` dans `docker/Dockerfile` et peut être surchargée dans `compose.yaml` avec la variable d'environnement `NODE_VERSION`.

## Pré-requis

- Docker Engine avec Docker Compose
- Git
- Un fichier `.env` configuré
- Les ports définis dans `.env` disponibles

## Installation et développement

```bash
git clone <url-du-depot> filbreeze
cd filbreeze
cp .env.example .env
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail npm ci
./vendor/bin/sail npm run dev
```

Si l'image Docker a été modifiée ou si la version Node change:

```bash
./vendor/bin/sail build --no-cache laravel.test
./vendor/bin/sail up -d
./vendor/bin/sail node -v
```

La version Node attendue est Node 24.x.

## Base de données

```bash
./vendor/bin/sail artisan migrate
```

Pour utiliser MySQL depuis l'application, conserver les valeurs Docker dans `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
```

## Génération des assets

En production, installer exactement les versions verrouillées puis compiler:

```bash
./vendor/bin/sail npm ci
./vendor/bin/sail npm run build
```

Le build produit les assets dans `public/build`.

## Production

Le serveur de production doit utiliser la même version Node 24 pendant la phase de build. La méthode recommandée est de compiler les assets dans la CI ou dans l'image Docker, puis de déployer `public/build` avec l'application.

Sur un serveur qui construit directement l'application:

```bash
./vendor/bin/sail build laravel.test
./vendor/bin/sail up -d
./vendor/bin/sail composer install --no-dev --optimize-autoloader
./vendor/bin/sail npm ci
./vendor/bin/sail npm run build
./vendor/bin/sail artisan migrate --force
./vendor/bin/sail artisan config:cache
./vendor/bin/sail artisan route:cache
./vendor/bin/sail artisan view:cache
```

En production:

- ne pas utiliser `npm update` pendant le déploiement;
- utiliser `npm ci` avec `package-lock.json` versionné;
- ne pas utiliser Mailpit;
- ne pas exposer MySQL directement à Internet;
- conserver les secrets uniquement dans les variables d'environnement;
- exécuter les migrations avec `--force` après validation de la sauvegarde;
- reconstruire l'image Docker après toute modification de `docker/Dockerfile` ou `compose.yaml`.

## PDF et navigateur Chromium

La génération PDF utilise Spatie Browsershot et Puppeteer 25.8.0. Node 24 est requis pour cette version de la chaîne Puppeteer. L'image Docker installe également les dépendances système nécessaires à Playwright/Chromium.

Un test rapide Browsershot peut être effectué avec:

```bash
./vendor/bin/sail php -r 'require "vendor/autoload.php"; Spatie\\Browsershot\\Browsershot::html("<h1>OK</h1>")->savePdf("/tmp/test.pdf"); echo "PDF OK\n";'
```

## Vérifications utiles

```bash
./vendor/bin/sail ps
./vendor/bin/sail php -v
./vendor/bin/sail node -v
./vendor/bin/sail npm -v
./vendor/bin/sail npm audit
./vendor/bin/sail artisan about
```
