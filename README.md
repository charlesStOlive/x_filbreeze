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

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).


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