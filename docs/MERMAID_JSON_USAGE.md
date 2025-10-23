# Utilisation du format Mermaid JSON enrichi

Ce format permet de générer des diagrammes Mermaid enrichis avec couleurs, icônes et descriptions.

## 🎯 Formats de sortie disponibles

### CLI
```bash
# Format table (console)
php artisan states:analyze Quote

# Format JSON (données brutes)
php artisan states:analyze Quote --format=json

# Format Markdown (documentation)
php artisan states:analyze Quote --format=markdown

# Format Mermaid JSON (diagrammes)
php artisan states:analyze Quote --format=mermaid-json
```

### API REST
```bash
# Données complètes
GET /api/states/Quote

# Données Mermaid
GET /api/states/Quote/mermaid-json

# Génération documentation
POST /api/states/Quote/generate-docs
```

## 🎨 Utilisation du JSON Mermaid

### Structure du JSON
```json
{
  "type": "flowchart",
  "direction": "LR",
  "nodes": [
    {
      "id": "Draft",
      "label": "Brouillon",
      "color": "#6b7280",
      "icon": "heroicon-o-pencil",
      "description": "Brouillon (Couleur: #6b7280) (Icône: heroicon-o-pencil)"
    }
  ],
  "edges": [
    {
      "from": "Draft",
      "to": "Validated",
      "label": "Valider devis",
      "description": "Valider devis (Avec formulaire) [Champs: validated_at]",
      "style": "thick"
    }
  ],
  "metadata": {
    "model": "Quote",
    "generated_at": "2025-10-23T09:36:13.177458Z",
    "total_states": 3,
    "total_transitions": 4
  }
}
```

### Conversion en syntaxe Mermaid

#### Exemple de rendu JavaScript
```javascript
function generateMermaidFromJson(data) {
    let mermaid = `flowchart ${data.direction}\n`;
    
    // Ajouter les nœuds avec couleurs
    data.nodes.forEach(node => {
        mermaid += `    ${node.id}[${node.label}]\n`;
        if (node.color) {
            mermaid += `    style ${node.id} fill:${node.color}\n`;
        }
    });
    
    // Ajouter les edges avec styles
    data.edges.forEach(edge => {
        let edgeStyle = '-->';
        if (edge.style === 'thick') {
            edgeStyle = '==>';
        } else if (edge.style === 'dotted') {
            edgeStyle = '-.->'; 
        }
        
        mermaid += `    ${edge.from} ${edgeStyle} ${edge.to}\n`;
        if (edge.label) {
            mermaid += `    ${edge.from} ${edgeStyle}|${edge.label}| ${edge.to}\n`;
        }
    });
    
    return mermaid;
}
```

### Exemple de rendu final
```mermaid
flowchart LR
    Draft[Brouillon]
    Validated[Validé]
    Canceled[Abandonné]
    
    Draft ==> Validated
    Draft -.-> Canceled
    Validated --> Draft
    Canceled --> Draft
    
    style Draft fill:#6b7280
    style Validated fill:#10b981
    style Canceled fill:oklch(0.637 0.237 25.331)
```

## 🌈 Couleurs supportées

### Arrays de couleurs
- Utilise automatiquement la valeur `500` de la palette
- Exemple: `oklch(0.637 0.237 25.331)` pour rouge 500

### Couleurs nommées Filament
- `gray` → `#6b7280`
- `success` → `#10b981`  
- `danger` → `#ef4444`
- `warning` → `#f59e0b`
- `info` → `#3b82f6`
- `primary` → `#6366f1`

## 🔧 Styles d'edges

### Types automatiques
- **Normal** : `-->` (transition simple)
- **Thick** : `==>` (transition avec formulaire)
- **Dotted** : `-.->` (transition avec redirection)

### Descriptions enrichies
- Formulaires : `(Avec formulaire) [Champs: field1, field2]`
- Redirections : `(Avec redirection) [URL: https://...]`
- Icônes : `(Icône: heroicon-o-check)`

## 💡 Cas d'usage

### Dashboard d'administration
Visualiser les workflows d'états pour chaque modèle

### Documentation technique
Générer automatiquement les diagrammes de flux

### API de diagrammes
Fournir des données structurées pour des outils externes

### Analyses de processus
Comprendre les transitions et leurs complexités