# Documentation - Gestion des Thèmes CSS avec Vite

## Vue d'ensemble

Ce projet utilise **3 configurations Vite distinctes** pour gérer différents types de CSS :

1. **Thème Filament Admin** (`vite.config.js`) - Port 5200
2. **Thème PDF** (`vite.pdf.config.js`) - Port 5201  
3. **Thème Frontend** (`vite.frontend.config.js`) - Port 5202 *(préparé pour l'avenir)*

## Structure des fichiers

```
resources/
├── css/
│   ├── filament/admin/
│   │   ├── theme.css                     # Thème Filament
│   │   └── tailwind.config.js            # Config Tailwind pour Filament
│   ├── pdf/
│   │   ├── theme.css                     # Thème pour PDFs
│   │   └── tailwind.pdf.config.js        # Config Tailwind pour PDFs
│   └── frontend/                         # 🆕 Préparé pour le frontend
│       ├── theme.css                     # Thème frontend public
│       └── tailwind.frontend.config.js   # Config Tailwind pour frontend
└── js/
    ├── app.js                            # JS principal
    ├── bootstrap.js                      # Bootstrap Laravel
    ├── diff2html.js                      # Utilitaires
    └── frontend/                         # 🆕 JS pour le frontend
        └── app.js                        # JS frontend spécifique
```

## Configurations Vite

### 1. Configuration principale (`vite.config.js`)

- **Usage** : Interface admin Filament
- **Port** : 5200 (HTTPS)
- **Inputs** :
  - `resources/js/app.js`
  - `resources/css/filament/admin/theme.css`

### 2. Configuration PDF (`vite.pdf.config.js`)
- **Usage** : Génération de PDFs
- **Port** : 5201 (HTTPS)
- **Input** : `resources/css/pdf/theme.css`
- **Build directory** : `public/pdf/`
- **Hot file** : `public/pdf.hot`

### 3. Configuration Frontend (`vite.frontend.config.js`) *(Futur)*
- **Usage** : Frontend public (quand implémenté)
- **Port** : 5202 (HTTPS)
- **Inputs** : 
  - `resources/css/frontend/theme.css`
  - `resources/js/frontend/app.js`
- **Build directory** : `public/frontend/`
- **Hot file** : `public/frontend.hot`

## Scripts NPM disponibles

### Développement individuel
```bash
npm run dev          # Lance uniquement l'admin Filament (port 5200)
npm run dev:pdf      # Lance uniquement le build PDF (port 5201)
npm run dev:frontend # Lance uniquement le frontend (port 5202)
```

### Développement parallèle
```bash
npm run watch        # Lance admin + PDF en parallèle ⭐ RECOMMANDÉ
npm run dev:all      # Alias pour watch
npm run dev:complete # Lance admin + PDF + frontend (quand nécessaire)
```

### Build production
```bash
npm run build         # Build admin uniquement
npm run build:pdf     # Build PDF uniquement
npm run build:frontend # Build frontend uniquement
npm run build:all     # Build admin + PDF
npm run build:complete # Build admin + PDF + frontend
```

## Comment utiliser en développement

### Pour le développement quotidien (Filament + PDF) :
```bash
npm run watch
```
Cette commande lance **concurrently** les deux serveurs Vite :
- Admin Filament sur https://x_filbreeze.test:5200
- PDF sur https://x_filbreeze.test:5201

### Avantages de cette approche :

1. **Hot Reload** sur les deux thèmes simultanément
2. **Aucun conflit de ports** (ports distincts)
3. **Builds séparés** pour optimiser chaque usage
4. **Extensible** pour ajouter d'autres thèmes

## Intégration dans Laravel

### Blade templates Filament
```php
@vite(['resources/css/filament/admin/theme.css'])
```

### Blade templates PDF
```php
@vite(['resources/css/pdf/theme.css'], 'pdf')
```

### Blade templates Frontend (futur)
```php
@vite(['resources/css/frontend/theme.css', 'resources/js/frontend/app.js'], 'frontend')
```

## Configuration Tailwind

Chaque thème a sa propre config Tailwind :
- **Admin** : `resources/css/filament/admin/tailwind.config.js`
- **PDF** : `resources/css/pdf/tailwind.pdf.config.js`
- **Frontend** : `resources/css/frontend/tailwind.frontend.config.js`

Toutes partagent les mêmes couleurs primaires mais peuvent avoir des configurations spécifiques.

## Troubleshooting

### Problème de ports occupés
Si vous avez des erreurs de ports occupés :
```bash
# Windows PowerShell
taskkill /F /IM node.exe
npm run watch
```

### Problème de certificats SSL
Vérifiez que les certificats Laragon existent :
- `C:/laragon/etc/ssl/laragon.key`
- `C:/laragon/etc/ssl/laragon.crt`

### Hot reload ne fonctionne pas
1. Vérifiez que le domaine `x_filbreeze.test` pointe vers localhost
2. Vérifiez les certificats SSL
3. Redémarrez avec `npm run watch`

## Recommandations

✅ **Utilisez `npm run watch` pour le développement quotidien**  
✅ **Gardez les configs Tailwind synchronisées pour les couleurs**  
✅ **Testez régulièrement les builds de production**  
⚠️ **N'oubliez pas de build avant le déploiement avec `npm run build:all`**
