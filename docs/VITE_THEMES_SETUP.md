# Documentation - Gestion des Thèmes CSS avec Vite

## Vue d'ensemble

Ce projet utilise **2 configurations Vite distinctes** pour gérer différents types de CSS :

1. **Thème Filament Admin** (`vite.config.js`) - Port 5173
2. **Thème PDF** (`vite.pdf.config.js`) - Port 5174

## Structure des fichiers

```
resources/
├── css/
│   ├── filament/admin/
│   │   ├── theme.css                     # Thème Filament
│   │   └── tailwind.config.js            # Config Tailwind pour Filament
│   └── pdf/
│       ├── theme.css                     # Thème pour PDFs
│       └── tailwind.pdf.config.js        # Config Tailwind pour PDFs
└── js/
    ├── app.js                            # JS principal
    ├── bootstrap.js                      # Bootstrap Laravel
    └── diff2html.js                      # Utilitaires
```

## Configurations Vite

### 1. Configuration principale (`vite.config.js`)

- **Usage** : Interface admin Filament
- **Port** : 5173 (par défaut)
- **Inputs** :
  - `resources/js/app.js`
  - `resources/css/filament/admin/theme.css`

### 2. Configuration PDF (`vite.pdf.config.js`)

- **Usage** : Génération de PDFs
- **Port** : 5174 (par défaut)
- **Input** : `resources/css/pdf/theme.css`
- **Build directory** : `public/pdf/`
- **Hot file** : `public/pdf.hot`

## Scripts NPM disponibles

### Développement parallèle

```bash
npm run dev          # Lance admin + PDF en parallèle ⭐ RECOMMANDÉ
npm run dev:admin    # Lance uniquement l'admin Filament 
npm run dev:pdf      # Lance uniquement le build PDF
```

### Build production

```bash
npm run build        # Build admin + PDF
npm run build:admin  # Build admin uniquement  
npm run build:pdf    # Build PDF uniquement
```

### Nettoyage

```bash
npm run clean        # Supprime tous les builds (public/build, public/pdf)
```

## Comment utiliser en développement

### Pour le développement quotidien (Filament + PDF)

```bash
npm run dev
```

Cette commande lance **concurrently** les deux serveurs Vite :

- Admin Filament sur le port par défaut
- PDF sur le port par défaut + 1

### Avantages de cette approche

1. **Hot Reload** sur les deux thèmes simultanément
2. **Aucun conflit de ports** (ports distincts)
3. **Builds séparés** pour optimiser chaque usage
4. **Configuration simplifiée**

## Intégration dans Laravel

### Blade templates Filament

```php
@vite(['resources/css/filament/admin/theme.css'])
```

### Blade templates PDF

```php
@vite(['resources/css/pdf/theme.css'], 'pdf')
```

## Configuration Tailwind

Chaque thème a sa propre config Tailwind :

- **Admin** : `resources/css/filament/admin/tailwind.config.js`
- **PDF** : `resources/css/pdf/tailwind.pdf.config.js`

Toutes partagent les mêmes couleurs primaires mais peuvent avoir des configurations spécifiques.

## Troubleshooting

### Problème de ports occupés

Si vous avez des erreurs de ports occupés :

```bash
# Windows PowerShell
taskkill /F /IM node.exe
npm run dev
```

### Problème de certificats SSL

Vérifiez que les certificats Laragon existent :

- `C:/laragon/etc/ssl/laragon.key`
- `C:/laragon/etc/ssl/laragon.crt`

### Hot reload ne fonctionne pas

1. Vérifiez que le domaine `x_filbreeze.test` pointe vers localhost
2. Vérifiez les certificats SSL
3. Redémarrez avec `npm run dev`

## Recommandations

✅ **Utilisez `npm run dev` pour le développement quotidien**  
✅ **Gardez les configs Tailwind synchronisées pour les couleurs**  
✅ **Testez régulièrement les builds de production**  
⚠️ **N'oubliez pas de build avant le déploiement avec `npm run build`**
