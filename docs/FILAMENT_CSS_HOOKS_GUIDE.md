# Guide des Hooks CSS Filament - Personnalisation du Menu Dark/Light Mode

## Introduction

Les hooks CSS de Filament permettent de personnaliser facilement l'apparence de l'interface admin sans modifier les fichiers source. Tous les hooks sont préfixés par `fi-`.

## Hooks CSS Principaux pour la Navigation

### 🎯 Hooks de la Sidebar (Menu Latéral)

| Hook CSS | Description | Usage |
|----------|-------------|--------|
| `.fi-sidebar` | Container principal de la sidebar | Couleur de fond, bordures |
| `.fi-sidebar-header` | En-tête de la sidebar | Logo, titre |
| `.fi-sidebar-nav` | Zone de navigation principale | Style global navigation |
| `.fi-sidebar-nav-item` | Éléments de navigation individuels | Couleurs, hover, états |
| `.fi-sidebar-nav-item.fi-active` | Élément de navigation actif | Mise en évidence |
| `.fi-sidebar-group` | Groupes de navigation | Séparateurs, organisation |
| `.fi-sidebar-group-label` | Labels des groupes | Titres de sections |

### 🎯 Hooks de la Topbar (Barre Supérieure)

| Hook CSS | Description | Usage |
|----------|-------------|--------|
| `.fi-topbar` | Barre supérieure principale | Couleur de fond, ombres |
| `.fi-topbar-start` | Section gauche de la topbar | Logo, boutons navigation |
| `.fi-topbar-end` | Section droite de la topbar | Menu utilisateur, notifications |
| `.fi-topbar-nav-groups` | Groupes de navigation topbar | Navigation horizontale |

## 💡 Exemples d'Implémentation

### Exemple 1: Sidebar avec couleurs différentes (Light/Dark)

```css
/* Light Mode - Thème Bleu */
.fi-sidebar {
    @apply bg-blue-50 border-r border-blue-200;
}

.fi-sidebar-nav-item {
    @apply text-blue-700 hover:bg-blue-100 hover:text-blue-800;
}

.fi-sidebar-nav-item.fi-active {
    @apply !bg-blue-200 !text-blue-900 font-semibold border-r-4 border-blue-500;
}

/* Dark Mode - Thème Violet */
.fi-sidebar:where(.dark, .dark *) {
    @apply bg-purple-950 border-r border-purple-800;
}

.fi-sidebar-nav-item:where(.dark, .dark *) {
    @apply text-purple-300 hover:bg-purple-800 hover:text-purple-100;
}

.fi-sidebar-nav-item.fi-active:where(.dark, .dark *) {
    @apply !bg-purple-800 !text-purple-100 !border-purple-400;
}
```

### Exemple 2: Topbar avec gradient (CSS natif)

```css
/* Light Mode - Fond vert */
.fi-topbar {
    background-color: #ecfdf5; /* green-50 */
    border-bottom: 1px solid #bbf7d0; /* green-200 */
}

/* Dark Mode - Fond sombre */
.fi-topbar:where(.dark, .dark *) {
    background-color: #111827; /* gray-900 */
    border-bottom: 1px solid #374151; /* gray-700 */
}
```

### Exemple 3: Logo avec gradient personnalisé

```css
.fi-topbar-brand {
    background: linear-gradient(135deg, #F87F04, #FF9F36);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
```

## 🔧 Techniques Avancées

### 1. Transitions et Animations

```css
.fi-sidebar-nav-item {
    @apply transition-all duration-200 ease-in-out;
}

.fi-sidebar-nav-item:hover {
    @apply transform translate-x-1;
}
```

### 2. États Focus pour l'Accessibilité

```css
.fi-sidebar-nav-item:focus-visible {
    @apply ring-2 ring-blue-500 ring-offset-2 outline-none;
}

.fi-sidebar-nav-item:focus-visible:where(.dark, .dark *) {
    @apply ring-purple-400 ring-offset-gray-900;
}
```

### 3. Responsive Design

```css
@media (max-width: 768px) {
    .fi-sidebar {
        @apply shadow-xl;
    }
    
    .fi-sidebar:where(.dark, .dark *) {
        @apply shadow-purple-900/50;
    }
}
```

## 🎨 Palette de Couleurs Recommandées

### Light Mode
- **Primaire**: `blue-50`, `blue-100`, `blue-200`
- **Texte**: `blue-700`, `blue-800`, `blue-900`
- **Accents**: `green-50`, `green-200`

### Dark Mode  
- **Primaire**: `purple-950`, `purple-900`, `purple-800`
- **Texte**: `purple-300`, `purple-200`, `purple-100`
- **Accents**: `gray-900`, `gray-700`

## 🚀 Méthodes d'Application

### Méthode 1: Avec @apply (Recommandée)
```css
.fi-sidebar {
    @apply bg-blue-50 border-r border-blue-200;
}
```

### Méthode 2: CSS Natif
```css
.fi-sidebar {
    background-color: #eff6ff; /* blue-50 */
    border-right: 1px solid #dbeafe; /* blue-200 */
}
```

### Méthode 3: Avec Variables CSS
```css
:root {
    --sidebar-bg-light: #eff6ff;
    --sidebar-bg-dark: #1e1b4b;
}

.fi-sidebar {
    background-color: var(--sidebar-bg-light);
}

.fi-sidebar:where(.dark, .dark *) {
    background-color: var(--sidebar-bg-dark);
}
```

## 🔍 Découverte des Hooks

Pour trouver les hooks CSS disponibles :

1. **Ouvrir les DevTools** du navigateur (F12)
2. **Inspecter l'élément** que vous voulez modifier
3. **Chercher les classes** commençant par `fi-`
4. **Appliquer les styles** dans `resources/css/filament/admin/theme.css`

## ⚠️ Bonnes Pratiques

1. **Utilisez toujours les hooks** plutôt que de modifier les vues Blade
2. **Testez en light ET dark mode** avec `:where(.dark, .dark *)`
3. **Ajoutez des transitions** pour une UX fluide
4. **Respectez l'accessibilité** avec des contrastes appropriés
5. **Utilisez !important** uniquement si nécessaire

## 📁 Structure des Fichiers

```
resources/css/filament/admin/
└── theme.css          # Votre fichier de personnalisation
```

## 🔄 Compilation

Après modifications, compilez les assets :

```bash
# Development
npm run dev

# Production
npm run build
```

## 📚 Hooks CSS Complets Disponibles

Voir les DevTools pour découvrir tous les hooks, mais voici les principaux :

- `fi-sidebar*` - Tous les éléments de la sidebar
- `fi-topbar*` - Tous les éléments de la topbar
- `fi-page*` - Éléments de page
- `fi-header*` - En-têtes
- `fi-navigation*` - Éléments de navigation
- `fi-user-menu*` - Menu utilisateur
- `fi-brand*` - Éléments de marque/logo

Ce guide vous permet de créer une interface Filament complètement personnalisée avec des couleurs différentes pour le light/dark mode !
