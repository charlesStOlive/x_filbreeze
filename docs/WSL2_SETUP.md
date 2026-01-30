# 🐧 Guide Complet : Configuration WSL2 + Laravel Sail

## 📋 Table des matières

1. [Qu'est-ce que WSL2 ?](#quest-ce-que-wsl2)
2. [Installation initiale](#installation-initiale)
3. [Configuration Ubuntu](#configuration-ubuntu)
4. [Installer PHP et Composer](#installer-php-et-composer)
5. [Copier votre projet](#copier-votre-projet)
6. [Installer Laravel Sail](#installer-laravel-sail)
7. [Utilisation quotidienne](#utilisation-quotidienne)
8. [Structure et chemins](#structure-et-chemins)
9. [Dépannage](#dépannage)

---

## Qu'est-ce que WSL2 ?

**WSL2** = Windows Subsystem for Linux 2

C'est un **système Linux complet qui tourne sur Windows**, avec :
- ✅ Vrai noyau Linux
- ✅ Système de fichiers Linux (10-50x plus rapide que Windows)
- ✅ Accès à vos fichiers Windows via `/mnt/c/`
- ✅ Docker fonctionne nativement
- ✅ Accès depuis VS Code via "Remote - WSL"

**Avantages pour Laravel :**
- 🚀 Performance 10-50x meilleure (fichiers sur système Linux)
- 🔧 Environnement proche de votre serveur production
- 📦 Pas besoin de Laragon/XAMPP
- 🐳 Docker Sail fonctionne parfaitement

---

## Installation initiale

### Étape 1 : Activer WSL2 sur Windows

Dans **PowerShell (en tant qu'administrateur)** :

```powershell
# Activer WSL2
wsl --install

# Cela installe :
# - WSL2
# - Une distribution Ubuntu par défaut
```

⏱️ **Temps** : 5-10 minutes

À la fin, vous devez redémarrer Windows.

### Étape 2 : Vérifier l'installation

```powershell
# Voir les distributions installées
wsl --list --verbose

# Doit afficher quelque chose comme :
# NAME            STATE           VERSION
# Ubuntu-22.04    Running         2
# docker-desktop  Stopped         2
```

### Étape 3 : Installer Ubuntu-22.04

Si vous n'avez pas Ubuntu, installez-la :

```powershell
wsl --install -d Ubuntu-22.04
```

### Étape 4 : Définir Ubuntu comme distribution par défaut

```powershell
wsl --set-default Ubuntu-22.04
```

Maintenant, `wsl` va ouvrir **Ubuntu** et pas `docker-desktop`.

---

## Configuration Ubuntu

### Première connexion

```powershell
# Lancer Ubuntu pour la première fois
wsl -d Ubuntu-22.04
```

Ubuntu va vous demander :
1. **Nom d'utilisateur** : Exemple : `charles`
2. **Mot de passe** : Créez-en un (vous le retiendrez pour `sudo`)

### Vérifier votre nom d'utilisateur

Dans WSL, tapez :

```bash
whoami
```

Ça affiche votre nom (exemple : `charles`).

### Mettre à jour Ubuntu

```bash
sudo apt update
sudo apt upgrade -y
```

---

## Installer PHP et Composer

### Installer PHP 8.3

```bash
# Ajouter le repository PPA qui contient PHP 8.3
sudo add-apt-repository ppa:ondrej/php -y

# Mettre à jour la liste des paquets
sudo apt update

# Installer PHP 8.3 + extensions nécessaires pour Laravel
sudo apt install -y php8.3-cli \
    php8.3-curl \
    php8.3-mbstring \
    php8.3-xml \
    php8.3-zip \
    php8.3-json \
    php8.3-bcmath \
    php8.3-gd \
    unzip
```

### Installer Composer

```bash
# Télécharger l'installateur de Composer
curl -sS https://getcomposer.org/installer | php

# Placer Composer dans le PATH
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
```

### Vérifier l'installation

```bash
php -v
# Doit afficher : PHP 8.3.x

composer -V
# Doit afficher : Composer version x.x.x
```

✅ **Parfait !** PHP et Composer sont installés.

---

## Copier votre projet

### Option A : Depuis Windows vers WSL

Si vous avez déjà un projet dans `C:\laragon\www\x_filbreeze` :

**Dans PowerShell Windows :**

```powershell
# Copier le projet vers WSL (évitera les slowdowns)
robocopy C:\laragon\www\x_filbreeze \\wsl$\Ubuntu-22.04\home\charles\filbreeze /E /XD vendor node_modules .git docker storage/logs

# Entrer dans WSL
wsl
```

**Dans WSL :**

```bash
# Aller dans le projet
cd ~/filbreeze

# Vérifier que les fichiers sont là
ls -la
```

### Option B : Cloner depuis Git

```bash
cd ~
git clone votre-repo-git filbreeze
cd filbreeze
```

---

## Installer Laravel Sail

### Étape 1 : Installer les dépendances Composer

```bash
cd ~/filbreeze
composer install
```

### Étape 2 : Installer Laravel Sail

```bash
composer require laravel/sail --dev
```

### Étape 3 : Initialiser Sail

```bash
php artisan sail:install
```

Lors de l'exécution, Sail vous demande quels services vous voulez :

```
Which services would you like to include in your Sail environment?
  - MySQL (8.0) ✓
  - Redis ✓
  - Mailpit ✓
```

Répondez **oui** à tous (ou seulement ceux que vous avez besoin).

### Étape 4 : Publier les Dockerfiles

```bash
php artisan sail:publish
```

Ça crée :
```
docker/
├── 8.3/
│   └── Dockerfile
└── nginx/
    └── conf.d/
```

Vous pouvez maintenant **personnaliser ces Dockerfiles** si besoin (ajouter des extensions, changer les versions, etc.).

### Étape 5 : Vérifier l'alias `sail`

Créer un alias pour plus de facilité :

```bash
echo "alias sail='bash vendor/bin/sail'" >> ~/.bashrc
source ~/.bashrc
```

### Étape 6 : Ajouter les alias Sail (recommandé)

Pour utiliser directement `npm`, `php`, `artisan`, etc. sans préfixer `sail` :

```bash
cat >> ~/.bashrc << 'EOF'

# Laravel Sail aliases
alias sail='./vendor/bin/sail'
alias artisan='sail artisan'
alias art='sail artisan'
alias php='sail php'
alias composer='sail composer'
alias npm='sail npm'
alias npx='sail npx'
alias node='sail node'
alias yarn='sail yarn'

EOF
source ~/.bashrc
```

Maintenant vous pouvez utiliser directement :
```bash
npm run dev        # au lieu de sail npm run dev
artisan migrate    # au lieu de sail artisan migrate
php --version      # au lieu de sail php --version
```

---

## Configurer Sail pour matcher votre environnement de production

### Pourquoi personnaliser le Dockerfile ?

Par défaut, Sail installe les **dernières versions** (PHP 8.5, MySQL 8.4, etc.), mais votre **production utilise** :
- **PHP 8.3.13**
- **MySQL 8.0.40**
- **Node 18.20.5**
- **npm 10.8.2**
- **Tesseract OCR** (pour reconnaissance optique)

Pour éviter les surprises, il faut **adapter le Dockerfile** pour matcher exactement la production.

### Étape 1 : Créer un Dockerfile custom

Créez le répertoire `/docker` :

```bash
mkdir -p docker
```

Créez `/docker/Dockerfile` avec PHP 8.3 + Tesseract :

```dockerfile
FROM ubuntu:24.04

LABEL maintainer="Charles"

ARG WWWGROUP
ARG NODE_VERSION=18
ARG MYSQL_CLIENT="mysql-client"

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC
ENV SUPERVISOR_PHP_COMMAND="/usr/bin/php -d variables_order=EGPCS /var/www/html/artisan serve --host=0.0.0.0 --port=80"
ENV SUPERVISOR_PHP_USER="sail"
ENV PLAYWRIGHT_BROWSERS_PATH=0

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN echo "Acquire::http::Pipeline-Depth 0;" > /etc/apt/apt.conf.d/99custom && \
    echo "Acquire::http::No-Cache true;" >> /etc/apt/apt.conf.d/99custom && \
    echo "Acquire::BrokenProxy    true;" >> /etc/apt/apt.conf.d/99custom

RUN apt-get update && apt-get upgrade -y \
    && mkdir -p /etc/apt/keyrings \
    && apt-get install -y gnupg gosu curl ca-certificates zip unzip git supervisor sqlite3 libcap2-bin libpng-dev python3 dnsutils librsvg2-bin fswatch ffmpeg nano \
    && curl -sS 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0xb8dc7e53946656efbce4c1dd71daeaab4ad4cab6' | gpg --dearmor | tee /etc/apt/keyrings/ppa_ondrej_php.gpg > /dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/ppa_ondrej_php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu noble main" > /etc/apt/sources.list.d/ppa_ondrej_php.list \
    && apt-get update \
    && apt-get install -y \
        libgd3 \
        php8.3-cli \
        php8.3-dev \
        php8.3-pgsql \
        php8.3-sqlite3 \
        php8.3-gd \
        php8.3-curl \
        php8.3-mongodb \
        php8.3-imap \
        php8.3-mysql \
        php8.3-mbstring \
        php8.3-xml \
        php8.3-zip \
        php8.3-bcmath \
        php8.3-soap \
        php8.3-intl \
        php8.3-readline \
        php8.3-ldap \
        php8.3-msgpack \
        php8.3-igbinary \
        php8.3-redis \
        php8.3-memcached \
        php8.3-pcov \
        php8.3-imagick \
        php8.3-xdebug \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_VERSION.x nodistro main" > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update \
    && apt-get install -y nodejs \
    && npm install -g npm@10.8.2 \
    && npm install -g pnpm \
    && npm install -g bun \
    && npx playwright install-deps \
    && curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | gpg --dearmor | tee /etc/apt/keyrings/yarn.gpg >/dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/yarn.gpg] https://dl.yarnpkg.com/debian/ stable main" > /etc/apt/sources.list.d/yarn.list \
    && apt-get update \
    && apt-get install -y yarn \
    && apt-get install -y $MYSQL_CLIENT \
    && apt-get install -y \
        tesseract-ocr \
        libtesseract-dev \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN setcap "cap_net_bind_service=+ep" /usr/bin/php8.3

RUN userdel -r ubuntu
RUN groupadd --force -g $WWWGROUP sail
RUN useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 sail
RUN git config --global --add safe.directory /var/www/html

COPY docker/start-container /usr/local/bin/start-container
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php.ini /etc/php/8.3/cli/conf.d/99-sail.ini
RUN chmod +x /usr/local/bin/start-container

EXPOSE 80/tcp

ENTRYPOINT ["start-container"]
```

### Étape 2 : Créer les fichiers de config

Créez `/docker/start-container` :

```bash
#!/usr/bin/env bash

if [ "$SUPERVISOR_PHP_USER" != "root" ] && [ "$SUPERVISOR_PHP_USER" != "sail" ]; then
    echo "You should set SUPERVISOR_PHP_USER to either 'sail' or 'root'."
    exit 1
fi

if [ ! -z "$WWWUSER" ]; then
    usermod -u $WWWUSER sail
fi

if [ ! -d /.composer ]; then
    mkdir /.composer
fi

chmod -R ugo+rw /.composer

if [ $# -gt 0 ]; then
    if [ "$SUPERVISOR_PHP_USER" = "root" ]; then
        exec "$@"
    else
        exec gosu $WWWUSER "$@"
    fi
else
    exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
fi
```

Créez `/docker/supervisord.conf` :

```ini
[supervisord]
nodaemon=true
user=root
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid

[program:php]
command=%(ENV_SUPERVISOR_PHP_COMMAND)s
user=%(ENV_SUPERVISOR_PHP_USER)s
environment=LARAVEL_SAIL="1"
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

Créez `/docker/php.ini` :

```ini
[PHP]
post_max_size = 100M
upload_max_filesize = 100M
variables_order = EGPCS
pcov.directory = .
```

### Étape 3 : Modifier compose.yaml

Changez le `build` de `laravel.test` pour utiliser le custom Dockerfile :

**Avant :**
```yaml
laravel.test:
    build:
        context: './vendor/laravel/sail/runtimes/8.5'
        dockerfile: Dockerfile
        args:
            WWWGROUP: '${WWWGROUP}'
    image: 'sail-8.5/app'
```

**Après :**
```yaml
laravel.test:
    build:
        context: '.'
        dockerfile: docker/Dockerfile
        args:
            WWWGROUP: '${WWWGROUP}'
    image: 'sail-8.3/app'
```

Changez aussi MySQL de 8.4 à 8.0.40 :

**Avant :**
```yaml
mysql:
    image: 'mysql:8.4'
```

**Après :**
```yaml
mysql:
    image: 'mysql:8.0.40'
```

### Étape 4 : Ajouter les variables dans .env

```bash
echo -e "\nWWWUSER=$(id -u)\nWWWGROUP=$(id -g)" >> .env
```

### Étape 5 : Builder et démarrer

```bash
# Nettoyer les anciens containers (si besoin)
sail down
docker system prune

# Builder la nouvelle image
sail build --no-cache

# Démarrer
sail up -d

# Vérifier les versions
sail php --version
sail npm --version
sail exec laravel.test mysql --version
sail exec laravel.test tesseract --version
```

**Résultat attendu :**
```
PHP 8.3.29 (cli)
10.8.2
mysql  Ver 8.0.40
tesseract 5.3.4
```

---

## Utilisation quotidienne

### Démarrer le projet

```bash
# Depuis WSL
cd ~/filbreeze

# Démarrer tous les services
sail up -d

# Ou avec l'alias
sail up -d
```

Accédez à http://localhost (port 80 par défaut).

### Commandes courantes

```bash
# Artisan
sail artisan migrate
sail artisan make:model Article
sail artisan tinker

# Composer
sail composer install
sail composer require vendor/package

# npm
sail npm install
sail npm run dev

# Logs
sail logs -f

# Arrêter
sail down
```

### Accéder à une base de données

```bash
# Depuis WSL
sail shell    # Entre dans le container Laravel

# Depuis votre machine Windows
# Utilisez un client SQL (DBeaver, PhpMyAdmin, etc.)
# Host: localhost
# Port: 3306
# Database: filbreeze (ou celui dans .env)
# User: sail (par défaut dans Sail)
```

---

## Structure et chemins

### Vue depuis Windows

```
C:\laragon\www\x_filbreeze\
├── app/
├── resources/
├── routes/
├── docker-compose.yml
├── docker/
├── .env
├── vendor/
└── ...
```

### Vue depuis WSL

```bash
# Depuis Windows (via /mnt/c/)
/mnt/c/laragon/www/x_filbreeze/

# Depuis Linux (copié localement)
~/filbreeze/
/home/charles/filbreeze/
```

### Pourquoi deux emplacements ?

- **Windows** (`/mnt/c/`) : Accès à vos fichiers depuis Windows
- **Linux** (`~/filbreeze/`) : 10-50x plus rapide pour les opérations de fichiers
  - ✅ `sail up -d` plus rapide
  - ✅ Migrations plus rapides
  - ✅ npm install plus rapide

**Recommandation** : Travailler depuis `~/filbreeze/` en WSL pour la performance, synchroniser avec Windows via Git.

---

## 🔐 Configurer Git et GitHub

### Configuration globale Git

```bash
# Identité
git config --global user.name "Ton Nom"
git config --global user.email "ton.email@exemple.com"
git config --global init.defaultBranch main
git config --global pull.rebase false
git config --global core.autocrlf input
```

### Authentification GitHub

**Option 1 : Via VS Code (recommandé pour HTTPS)**

1. Ouvrir VS Code dans WSL : `code .`
2. Cliquer sur **Accounts** (en bas à gauche) → **Sign in to GitHub**
3. VS Code gère automatiquement les tokens pour push/pull

**Option 2 : SSH (avancé)**

```bash
# Générer une clé SSH
ssh-keygen -t ed25519 -C "ton.email@exemple.com"

# Accepter le chemin par défaut (~/.ssh/id_ed25519)
# Créer une passphrase

# Charger la clé
eval "$(ssh-agent -s)"
ssh-add ~/.ssh/id_ed25519

# Voir la clé publique
cat ~/.ssh/id_ed25519.pub

# Copier la clé, puis :
# GitHub → Settings → SSH and GPG keys → New SSH key → Coller

# Tester
ssh -T git@github.com
# Doit répondre : "Hi <user>! You've successfully authenticated..."
```

**Option 3 : GitHub CLI (pratique)**

```bash
sudo apt install gh

# Se connecter
gh auth login
# Choisir GitHub.com → SSH ou HTTPS selon votre choix
```

### Vérifier l'accès

```bash
# Via HTTPS (VS Code gère)
git remote -v
git ls-remote origin   # Doit lister les branches

# Via SSH (si configuré)
ssh -T git@github.com
```

---

## 📧 Mailpit : Service de test d'email

### Qu'est-ce que Mailpit ?

**Mailpit** = Service SMTP local qui **capture tous les emails** sans les envoyer vraiment.

**Idéal pour :**
- ✅ Tester les emails sans polluer votre inbox
- ✅ Voir le rendu exact des emails
- ✅ Déboguer les templates Blade

### Configuration dans Sail

Mailpit est **inclus par défaut** dans `compose.yaml`. Accessible sur :

- **SMTP** : `localhost:1025` (utilisé par Laravel)
- **Interface Web** : http://localhost:8025 (voir les mails)

### Configuration .env Laravel

```env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@example.com"
MAIL_FROM_NAME="Filbreeze"
```

### Utilisation

```php
// Dans votre code Laravel
Mail::send('mails.welcome', ['user' => $user], function ($message) {
    $message->to($user->email)->subject('Welcome!');
});
```

L'email ne sera **pas envoyé**, mais stocké dans Mailpit.

**Voir les emails :**
1. Ouvrir http://localhost:8025
2. Tous les emails captés s'affichent
3. Cliquer sur un email pour voir le contenu HTML/texte

---

## 🧘 Nettoyage des containers (important)

Quand vous relancez `sail up` après quelques jours, les anciens containers peuvent occuper les ports.

### Nettoyer les vieux containers

```bash
# Arrêter tous les containers Sail
sail down

# Supprimer les containers non utilisés
docker system prune -a

# Builder et relancer
sail build --no-cache
sail up -d
```

### Vérifier quels containers tournent

```bash
# Voir tous les containers Sail actifs
sail ps

# Voir TOUS les containers (y compris arrêtés)
docker ps -a

# Arrêter un container spécifique
docker stop nom-du-container
docker rm nom-du-container
```

---

## 🔧 Installer GitKraken (optionnel)

**GitKraken** = Interface graphique pour Git.

### Sur Windows

1. Télécharger depuis https://www.gitkraken.com/download
2. Installer
3. GitKraken détecte automatiquement vos dépôts WSL

### Dans WSL

```bash
# Télécharger
wget https://api.gitkraken.dev/releases/production/linux/x64/active/gitkraken-amd64.deb

# Installer
sudo apt install ./gitkraken-amd64.deb

# Supprimer le fichier d'installation
rm gitkraken-amd64.deb

# Lancer
gitkraken &
```

**Note :** Nécessite WSLg (interface graphique). Généralement disponible sur WSL2 récent.

### Installer l'extension Remote - WSL

1. Ouvrir VS Code
2. Installer l'extension : **Remote - WSL** (Microsoft)

### Ouvrir le projet dans VS Code depuis WSL

```bash
# Dans WSL, depuis votre projet
cd ~/filbreeze
code .
```

VS Code va automatiquement se connecter à WSL et ouvrir le projet.

**Avantage** : VS Code utilise le terminal WSL directement, accès aux fichiers Linux rapide.

---

## Dépannage

### Docker ne démarre pas

```bash
# Vérifier que Docker Desktop tourne depuis Windows
# (Regardez en bas à droite du taskbar)

# Redémarrer Docker
docker ps
```

### Sail commandes longues à la première exécution

La première fois, Sail build les images. C'est normal (3-5 minutes).

```bash
sail up -d --build
```

### Problème de mot de passe sudo

Si vous oubliez votre mot de passe Ubuntu :

```powershell
# Dans PowerShell Windows
wsl -d Ubuntu-22.04 -u root

# Dans WSL root
passwd charles  # Remplacez charles par votre nom d'utilisateur

# Sortir
exit
```

### Les fichiers sont lents depuis Windows

Vous travaillez dans `/mnt/c/` (Windows mount).

**Solution** : Copiez le projet dans `~` (Linux) pour 10-50x de vitesse.

```bash
# Copier depuis /mnt/c vers ~
cp -r /mnt/c/laragon/www/x_filbreeze ~/filbreeze-dev

# Travailler depuis ~
cd ~/filbreeze-dev
```

### Permission denied lors de npm install

```bash
# Donner les droits
sail bash
chown -R sail:sail /var/www/html/node_modules
```

---

## Workflow recommandé

### Jour 1 : Installation complète

```bash
# 1. Dans PowerShell Windows
wsl --install -d Ubuntu-22.04
wsl --set-default Ubuntu-22.04

# 2. Dans WSL
wsl

# 3. Copier le projet
robocopy C:\laragon\www\x_filbreeze \\wsl$\Ubuntu-22.04\home\charles\filbreeze /E /XD vendor node_modules .git

# 4. Dans WSL
cd ~/filbreeze
sudo apt update && sudo apt upgrade -y
sudo add-apt-repository ppa:ondrej/php -y
sudo apt install -y php8.3-cli php8.3-curl php8.3-mbstring php8.3-xml php8.3-zip unzip
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer

# 5. Installer Sail
composer install
composer require laravel/sail --dev
php artisan sail:install
php artisan sail:publish

# 6. Démarrer
sail up -d
sail artisan migrate
sail npm install
```

### Quotidien

```bash
# Ouvrir WSL
wsl

# Aller dans le projet
cd ~/filbreeze

# Démarrer les services
sail up -d

# Développer
# ... travail normal

# Arrêter
sail down
```

---

---

## 📝 Résumé des fichiers créés/modifiés

Voici ce qui a été configuré pour matcher votre environnement de production :

### Fichiers créés

```
docker/
├── Dockerfile              # Custom, PHP 8.3 + Tesseract OCR
├── start-container        # Script de démarrage du container
├── supervisord.conf       # Configuration du superviseur (gère PHP)
└── php.ini               # Configuration PHP personnalisée
```

### Fichiers modifiés

| Fichier | Changement |
|---------|-----------|
| `compose.yaml` | `laravel.test.build.context` → `'.'` au lieu de `'./vendor/laravel/sail/runtimes/8.5'` |
| `compose.yaml` | `laravel.test.build.dockerfile` → `docker/Dockerfile` |
| `compose.yaml` | `laravel.test.image` → `'sail-8.3/app'` au lieu de `'sail-8.5/app'` |
| `compose.yaml` | `mysql.image` → `'mysql:8.0.40'` au lieu de `'mysql:8.4'` |
| `.env` | Ajouté `WWWUSER=1000` et `WWWGROUP=1000` |
| `~/.bashrc` | Aliases Sail pour `npm`, `artisan`, `php`, `composer`, etc. |

### Versions finales

```
PHP:      8.3.29 (proche de votre 8.3.13)
MySQL:    8.0.40 (exact)
Node:     18.20.8
npm:      10.8.2 (exact)
Tesseract: 5.3.4
```

---

## 🚀 Quick Start (après première installation)

Chaque jour :

```bash
# 1. Ouvrir WSL
wsl

# 2. Aller dans le projet
cd ~/filbreeze

# 3. Démarrer les services
sail up -d

# 4. Migrer (si besoin)
artisan migrate

# 5. Développer
npm run dev    # Front
# ... VS Code pour le code

# 6. À la fin de la journée
sail down
```

---

## 🎯 Points clés à retenir

✅ **WSL2** = Linux complet sur Windows  
✅ **PHP + Composer** = Outils de dev seulement (dans WSL)  
✅ **Laravel Sail** = Docker wrapper pour Laravel  
✅ **Pas de Laragon** = Plus besoin après Sail installé  
✅ **Performance** = 10-50x meilleur que Windows mount  
✅ **VS Code** = Utilise WSL directement via Remote - WSL

---

## 📚 Ressources

- [WSL Documentation Officielle](https://learn.microsoft.com/en-us/windows/wsl/)
- [Laravel Sail Documentation](https://laravel.com/docs/sail)
- [VS Code Remote - WSL](https://code.visualstudio.com/docs/remote/wsl)

---

**Bon développement ! 🚀**
