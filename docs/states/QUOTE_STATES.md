# États et Transitions - Quote

> Documentation générée automatiquement le 23/10/2025 à 09:13

## 📊 États disponibles

### Canceled

| Propriété | Valeur |
|-----------|--------|
| 📝 Label | Abandonné |
| 🎨 Couleur | `Filament Color Array` |
| 🔣 Icône | `heroicon-o-x-mark` |
| 📄 Description | Abandonné |

### Draft

| Propriété | Valeur |
|-----------|--------|
| 📝 Label | Brouillon |
| 🎨 Couleur | `gray` |
| 🔣 Icône | `heroicon-o-pencil` |
| 📄 Description | Brouillon |

### Validated

| Propriété | Valeur |
|-----------|--------|
| 📝 Label | Validé |
| 🎨 Couleur | `success` |
| 🔣 Icône | `heroicon-o-check` |
| 📄 Description | Devis validé |

## 🔄 Transitions disponibles

### CanceledToDraft

| Propriété | Valeur |
|-----------|--------|
| 📝 Label | Revenir au brouillon |
| 🔣 Icône | `heroicon-o-arrow-uturn-left` |
| 🔀 Redirection | NON |

### ToCanceled

| Propriété | Valeur |
|-----------|--------|
| 📝 Label | Abandonner |
| 🔣 Icône | `heroicon-o-x-mark` |
| 🔀 Redirection | OUI |
| 🔗 URL | `https://x_filbreeze.test/admin/crm/quotes` |

### ToDraft

| Propriété | Valeur |
|-----------|--------|
| 📝 Label | Annuler validation |
| 🔣 Icône | `heroicon-o-x-mark` |
| 🔀 Redirection | NON |

### ToValidated

| Propriété | Valeur |
|-----------|--------|
| 📝 Label | Valider devis |
| 🔣 Icône | `heroicon-o-check` |
| 📋 Formulaire | OUI |
| 📝 Champs | `validated_at`: Validé le |
| 🔀 Redirection | NON |

---

*Documentation générée par `php artisan states:analyze Quote --format=markdown`*
