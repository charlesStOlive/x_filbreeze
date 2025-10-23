<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## 🔐 Système de Permissions

Ce projet utilise un **système de permissions wildcard personnalisé** basé sur Spatie Permission et intégré avec Filament.

### 📚 Documentation

- **[📖 Documentation complète](./docs/PERMISSIONS_DOCUMENTATION.md)** - Guide complet du système
- **[🚀 Référence rapide](./docs/PERMISSIONS_QUICK_REFERENCE.md)** - Commandes essentielles  
- **[🔧 Exemples avancés](./docs/PERMISSIONS_CUSTOM_EXAMPLE.md)** - Commandes personnalisées

### 🛠️ Commandes Essentielles

```bash
# Synchroniser toutes les permissions (scan automatique des Resources)
php artisan permissions:sync

# Ajouter une nouvelle resource
php artisan permissions:add-resource nomResource

# Lister les permissions et rôles
php artisan permissions:list

# Reset complet du système (ATTENTION!)
php artisan permissions:reset
```

### ⚡ Démarrage Rapide

1. **Synchroniser les permissions existantes :**

   ```bash
   php artisan permissions:sync
   ```

2. **Nettoyer les permissions obsolètes :**

   ```bash
   php artisan permissions:sync --cleanup --dry-run  # Simulation
   php artisan permissions:sync --cleanup           # Nettoyage interactif
   ```

3. **Pour une nouvelle Resource, ajouter les permissions :**

   ```bash
   php artisan permissions:add-resource client
   ```

4. **Copier le code généré dans votre Resource :**

   ```php
   use App\Services\PermissionService;

   public static function canViewAny(): bool
   {
       return PermissionService::can('client.view');
   }
   ```

### 🎯 Structure des Permissions

- `resource.*` - Accès complet à la resource
- `resource.view` - Consultation
- `resource.create` - Création
- `resource.edit` - Modification  
- `resource.delete` - Suppression

**Exemple :** `users.*`, `users.view`, `products.create`, `companies.edit`

### 🧹 Nettoyage Automatique

Le système peut automatiquement détecter et supprimer les permissions obsolètes :

- **Protection système** : Les permissions `s_*` sont automatiquement protégées
- **Sécurité** : Confirmation requise avant suppression des permissions actives
- **Simulation** : Mode `--dry-run` pour tester sans risque

```bash
# Voir ce qui serait supprimé
php artisan permissions:sync --cleanup --dry-run

# Nettoyage interactif avec confirmations
php artisan permissions:sync --cleanup

```

# Installation de Imagick sur windows

* Télecherger le binaries de Imagick ici : https://imagemagick.org/script/download.php#windows 
* A noter
  * Q16 ou Q8 => Q16 signifie 16 bits par pixel, offrant une meilleure qualité d'image mais avec une consommation de mémoire un peu plus élevée. Pour des besoins de précision (traitement de PDF avec des détails fins), Q16 est généralement recommandé
  * HDRI (High Dynamic Range Imaging) => La version HDRI (ImageMagick-7.1.1-40-Q16-HDRI-x64-dll.exe) permet de travailler avec des images HDR. Elle n'est généralement nécessaire que pour des applications spécifiques et utilise plus de mémoire. À moins que vous ayez des besoins en HDR, vous pouvez rester sur la version non HDRI
  * Attention x64 ou x32
* Trouveeer le bon dll en fonction de la version : 
  * Aller sur https://mlocati.github.io/articles/php-windows-imagick.html
  * Choisir n fonction de la version de php. 
  * Pour verifier TS ou la version du compilateur : 
  ```
  php -i | findstr "Architecture"
  php -i | findstr "Thread Safety"
  php -i | findstr "Compiler"

  ```
  * Extraire l'ensemble du fichier dans un repertoire choisis : ex C:/PHP ( ou dans le dossier de laragon) 
  * Copier le fichieer dll dans le etc de la bonne version de php : C:\laragon\bin\php\php-8.3.9-Win32-vs16-x64\ext
  * Ajouter à la variable d'environement le repertoire de l'ensemble des autres fichiers ex : C:\laragon\imagick_all
  * Veriffication : 
  ```
  php -m | findstr imagick
  ```

  # Installation de tesseract
  * Télecharger la version officielle :   https://github.com/UB-Mannheim/tesseract/wiki
  * hoisissez le dossier d’installation (par défaut : C:\Program Files\Tesseract-OCR).
  * Ajouter aux  les variables d'environnement :  (ex: C:\Program Files\Tesseract-OCR).
  * Verification : 
  ````
  tesseract -v
  ````
## Le wrapper laravel : 
````
composer require thiagoalessio/tesseract_ocr
````

## Pour convertir des PDF avec imagick :
Il faut installer GhostScript : https://github.com/dlemstra/Magick.NET/blob/main/docs/ConvertPDF.md


https://filamentphp.com/docs/4.x/schemas/custom-components#! 
https://filamentphp.com/docs/4.x/infolists/custom-entries 
https://filamentphp.com/docs/4.x/advanced/assets#asynchronous-alpinejs-components
https://livewire.laravel.com/docs/alpine