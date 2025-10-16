# Générateur de Palettes de Couleurs X-Artfil

## Vue d'ensemble

Cette commande artisan génère automatiquement des palettes de couleurs complètes pour votre projet Laravel/Filament en utilisant la bibliothèque [Iris](https://github.com/ozdemirburak/iris). Elle met à jour automatiquement :

- Les variables CSS dans vos fichiers de thème
- La configuration des couleurs Filament
- Les palettes Tailwind CSS complètes (50-900)

## Fichiers nécessaires

### 1. Commande Console

- `app/Console/Commands/GenerateColorsCommand.php` - Commande artisan principale

### 2. Service principal

- `app/Services/ColorPaletteService.php` - Service de génération et manipulation des couleurs

### 3. Dépendance Composer

- `ozdemirburak/iris: ^3.1` - Bibliothèque de manipulation des couleurs (déjà installée)

### 4. Fichiers CSS à mettre à jour (configurables)

- `resources/css/front/front.css` - Thème frontend
- `resources/css/filament/admin/filament.css` - Thème admin Filament
- `resources/css/shared/shared-utilities.css` - Utilitaires partagés (lecture seule, utilise les variables)

### 5. Provider Filament

- `app/Providers/Filament/AdminPanelProvider.php` - Configuration des couleurs Filament

### 6. Fichiers de configuration requis

Chaque fichier CSS doit contenir les balises de détection :

```css
@theme {
  /* color-schema-console-generated-start */
  /* Variables générées automatiquement */  
  /* color-schema-console-generated-end */
}
```

## Installation

La bibliothèque Iris est déjà installée via Composer. La commande est disponible immédiatement.

## Modes de génération

### 1. Mode Manuel
Permet de spécifier manuellement les 3 couleurs (primaire, secondaire, tertiaire).

### 2. Mode Split-Complémentaire ⭐ **RECOMMANDÉ**
Génère automatiquement une palette harmonieuse basée sur le schéma split-complémentaire :
- **Principe** : Couleur principale + les deux couleurs adjacentes à sa complémentaire
- **Avantage** : Plus doux que le triadique, mais reste harmonieux et équilibré
- **Exemple** : Rouge → Bleu-cyan + Vert-cyan (au lieu du cyan pur)

### 3. Mode Analogique Simple
Génère des couleurs adjacentes sur le cercle chromatique :
- **Principe** : Couleurs voisines harmonieuses (+30° et -30°)
- **Avantage** : Très harmonieux et apaisant
- **Exemple** : Rouge → Rouge-orange + Rouge-violet

## Utilisation

### Mode commande avec options

```bash
# Mode split-complémentaire (recommandé) - génération automatique
php artisan generate:colors --primary=#FF2C2C --mode=split-comp

# Mode analogique simple - génération automatique  
php artisan generate:colors --primary=#FF2C2C --mode=simple

# Mode manuel - toutes les couleurs spécifiées
php artisan generate:colors --mode=manuel --primary=#B24030 --secondary=#F7D463 --tertiary=#10B981

# Avec inversion des couleurs secondaire et tertiaire
php artisan generate:colors --primary=#FF2C2C --mode=split-comp --swap
```

### Mode interactif

```bash
php artisan generate:colors
```

La commande vous demandera :

1. **Mode de génération** : manuel, split-comp, simple
2. **Couleur primaire** : Format hex (ex: #B24030)
3. **Couleurs supplémentaires** : Selon le mode choisi

## Fonctionnalités

### Génération de palettes

Pour chaque couleur (primaire et secondaire), la commande génère une palette complète :

```css
'primary' => [
    '50'  => '#eff6ff',  /* Très clair */
    '100' => '#dbeafe',
    '200' => '#bfdbfe',
    '300' => '#93c5fd',  /* Clair (utilisé comme primary-light) */
    '400' => '#60a5fa',
    '500' => '#3b82f6',  /* Base (couleur fournie) */
    '600' => '#2563eb',
    '700' => '#1d4ed8',  /* Sombre (utilisé comme primary-dark) */
    '800' => '#1e40af',
    '900' => '#1e3a8a',  /* Très sombre */
]
```

### Variables CSS générées

La commande génère maintenant uniquement les palettes Tailwind complètes (format standard) :

#### Pour tous les fichiers CSS (front.css et filament.css)

```css
/* Palette complète primary (50-900) */
--color-primary-50: #eff6ff;   /* Très clair */
--color-primary-100: #dbeafe;
--color-primary-200: #bfdbfe;
--color-primary-300: #93c5fd;  /* Clair */
--color-primary-400: #60a5fa;
--color-primary-500: #3b82f6;  /* Base (couleur fournie) */
--color-primary-600: #2563eb;
--color-primary-700: #1d4ed8;  /* Sombre */
--color-primary-800: #1e40af;
--color-primary-900: #1e3a8a;  /* Très sombre */

/* Palette complète secondary (50-900) */
--color-secondary-50: #fef8e7;
--color-secondary-100: #fcf2cf;
--color-secondary-200: #fae49e;
--color-secondary-300: #f7d76e;
--color-secondary-400: #f5ca3d;
--color-secondary-500: #f7d463;
--color-secondary-600: #c2970a;
--color-secondary-700: #917108;
--color-secondary-800: #614b05;
--color-secondary-900: #302603;

/* Palette complète tertiary (50-900) - optionnelle */
--color-tertiary-50: #e8fdf6;
--color-tertiary-100: #d0fbed;
--color-tertiary-200: #a1f7da;
--color-tertiary-300: #72f3c8;
--color-tertiary-400: #43efb6;
--color-tertiary-500: #10b981;
--color-tertiary-600: #10bc83;
--color-tertiary-700: #0c8d62;
--color-tertiary-800: #085e41;
--color-tertiary-900: #042f21;
```

### Utilisation des variables

Dans vos CSS, vous pouvez maintenant utiliser directement :

```css
/* Exemples d'utilisation */
.btn-primary {
  background-color: var(--color-primary-500);  /* Couleur de base */
}

.btn-primary:hover {
  background-color: var(--color-primary-600);  /* Plus sombre au survol */
}

.text-primary-light {
  color: var(--color-primary-300);  /* Texte clair */
}

/* Gradients */
.gradient-primary {
  background: linear-gradient(135deg, var(--color-primary-700), var(--color-primary-300));
}
```

### Fichiers mis à jour

1. **resources/css/front/front.css** - Variables CSS pour le frontend
2. **resources/css/filament/admin/filament.css** - Variables CSS pour l'admin Filament  
3. **app/Providers/Filament/AdminPanelProvider.php** - Configuration des couleurs Filament

## Configuration des fichiers

### Balises de détection

Les fichiers CSS doivent contenir les balises suivantes pour que la commande puisse les mettre à jour :

```css
@theme {
  --font-sans: 'Poppins', ui-sans-serif, system-ui, sans-serif;
  /* color-schema-console-generated-start */
  /* Les variables de couleur seront insérées ici */
  /* color-schema-console-generated-end */
}
```

### Structure requise pour Filament Provider

Le fichier `AdminPanelProvider.php` doit contenir une section `colors()` :

```php
->colors([
    'primary' => '#B24030',
    'secondary' => '#F7D463',
])
```

## Options avancées

### Option `--swap`
Inverse les couleurs secondaire et tertiaire après génération. Utile pour tester différentes harmonies :

```bash
# Génération normale
php artisan generate:colors --primary=#FF2C2C --mode=split-comp

# Génération avec inversion
php artisan generate:colors --primary=#FF2C2C --mode=split-comp --swap
```

### Algorithmes de génération automatique

#### Mode Split-Complémentaire
- **Secondaire** : +150° sur le cercle chromatique (30° avant la complémentaire)
- **Tertiaire** : +210° sur le cercle chromatique (30° après la complémentaire)  
- Ajustement harmonieux de la saturation et luminosité

#### Mode Analogique Simple  
- **Secondaire** : +30° sur le cercle chromatique
- **Tertiaire** : -30° sur le cercle chromatique
- Variations subtiles de saturation et luminosité pour créer de l'intérêt

## Classes CSS partagées

Les classes utilitaires comme `.prose-brush-primary` et `.prose-brush-secondary` restent dans `shared-utilities.css` et utilisent automatiquement les nouvelles variables CSS.

## Exemples complets

### Mode Split-Complémentaire (Recommandé)

```bash
# Génération automatique split-complémentaire
php artisan generate:colors --primary=#FF2C2C --mode=split-comp

# Résultat affiché :
🎨 Générateur de palettes de couleurs X-Artfil

Couleur primaire: #FF2C2C
Couleur secondaire: #2CFFCC (généré automatiquement)
Couleur tertiaire: #CC2CFF (généré automatiquement)

Génération des palettes de couleurs...
# [Affichage des palettes complètes 50-900]

Mise à jour des fichiers CSS...
✅ resources/css/front/front.css
✅ resources/css/filament/admin/filament.css
Mise à jour du provider Filament...
✅ app/Providers/Filament/AdminPanelProvider.php

🎉 Génération terminée ! 2 fichier(s) CSS mis à jour.
N'oubliez pas de recompiler vos assets (npm run build)
```

### Mode Analogique Simple

```bash
# Génération automatique analogique
php artisan generate:colors --primary=#3B82F6 --mode=simple

# Couleurs générées : Bleu → Bleu-violet + Bleu-vert
```

### Mode Manuel avec inversion

```bash
# Mode manuel avec inversion des couleurs
php artisan generate:colors --mode=manuel --primary=#8B5CF6 --secondary=#10B981 --tertiary=#F59E0B --swap

# 🔄 Couleurs secondaire et tertiaire inversées
# Résultat : Primary=#8B5CF6, Secondary=#F59E0B, Tertiary=#10B981
```

## Après la génération

N'oubliez pas de recompiler vos assets après avoir utilisé la commande :

```bash
npm run build
# ou
npm run dev
```

## Dépannage

## Ajout de nouveaux fichiers CSS

Pour ajouter un nouveau fichier CSS à traiter par la commande, suivez ces étapes :

### 1. Ajouter le fichier à la liste

Dans `GenerateColorsCommand.php`, ajoutez le chemin du nouveau fichier dans `$cssFiles` :

```php
protected array $cssFiles = [
    'resources/css/front/front.css',
    'resources/css/filament/admin/filament.css',
    'resources/css/email/email.css',  // Nouveau fichier
];
```

### 2. Créer le fichier avec la structure requise

Le nouveau fichier doit contenir les balises de détection :

```css
@import "tailwindcss";

@theme {
  --font-sans: 'Poppins', ui-sans-serif, system-ui, sans-serif;
  /* color-schema-console-generated-start */
  /* Les variables seront générées automatiquement ici */
  /* color-schema-console-generated-end */
}
```

### 3. Tester la génération

Lancez la commande pour vérifier que le nouveau fichier est bien traité :

```bash
php artisan generate:colors --primary=#FF2C2C --mode=split-comp
```

## Dépannage

### Fichier non trouvé

- Vérifiez que les fichiers CSS existent
- Vérifiez que les balises `/* color-schema-console-generated-start */` et `/* color-schema-console-generated-end */` sont présentes

### Couleur invalide

- Utilisez le format hex à 6 caractères : `#RRGGBB`
- Exemple valide : `#B24030`, `#3B82F6`
- Exemple invalide : `#B24`, `rgb(178, 64, 48)`

### Provider Filament non mis à jour

- Vérifiez que le fichier `app/Providers/Filament/AdminPanelProvider.php` existe
- Vérifiez qu'il contient une section `->colors([...])` existante