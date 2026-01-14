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

## VS Code Integration

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
