# FilamentStateFusion Overrides

## Problèmes identifiés dans le plugin original

### 1. StateFusionActionGroup - Problème de chaînage
**Problème :** Les actions sont générées dans le constructeur avant la configuration, empêchant le chaînage fluide.

**Solution :** Override qui déplace la génération dans `setUp()`.

### 2. ResolvesActionAttributes - Problème de type couleur  
**Problème :** Le trait force un retour `?string` pour les couleurs alors que Filament accepte `string|array|null`.

**Erreur typique :**
```
Return value must be of type ?string, array returned
```

**Solution :** Override qui accepte les types corrects Filament.

## Classes overrides créées

### `App\Filament\Overrides\Actions\StateFusionActionGroup`
```php
StateFusionActionGroup::generate('state', QuoteState::class)
    ->label('Changer état')
    ->icon('fas-code-branch')
    ->button()
    ->color('primary')
    ->tooltip('Cliquez pour changer l\'état');
```

### `App\Filament\Overrides\Actions\StateFusionAction`
Utilise le trait `ResolvesActionAttributes` corrigé pour supporter les couleurs Filament.

### `App\Filament\Overrides\Concerns\ResolvesActionAttributes`
Corrige le type de retour des couleurs pour accepter `string|array|null`.

## Utilisation des couleurs

Maintenant vos classes d'état peuvent utiliser :

### Couleurs string (comme avant)
```php
public function getColor(): string|array
{
    return 'danger'; // fonctionne
}
```

### Couleurs Filament (nouveau)
```php
use Filament\Support\Colors\Color;

public function getColor(): string|array
{
    return Color::Red; // fonctionne maintenant
}
```

### Couleurs personnalisées
```php
public function getColor(): string|array
{
    return [
        50 => '#fef2f2',
        100 => '#fee2e2',
        // ... définition complète
    ];
}
```

## Méthodes disponibles sur StateFusionActionGroup
- `->label(string)` - Texte du bouton
- `->icon(string)` - Icône du bouton  
- `->color(string)` - Couleur (primary, success, warning, danger, etc.)
- `->button()` - Affichage en tant que bouton
- `->tooltip(string)` - Info-bulle
- `->size(string)` - Taille (sm, md, lg, xl)
- `->outlined()` - Style outlined
- `->disabled(bool)` - Désactiver le bouton

## Structure des fichiers
```
app/Filament/Overrides/
├── Actions/
│   ├── StateFusionAction.php           # Action corrigée
│   ├── StateFusionActionGroup.php      # Groupe corrigé
│   └── README.md                       # Cette documentation
└── Concerns/
    └── ResolvesActionAttributes.php    # Trait couleurs corrigé
```

## Import dans vos classes
```php
use App\Filament\Overrides\Actions\StateFusionActionGroup;
```

## Fonctionnalité bonus : HasRedirection

Une interface `HasRedirection` a été ajoutée pour gérer les redirections après les transitions d'état.

### Utilisation
```php
// Dans une classe de transition
class ToCanceled extends Transition implements HasRedirection
{
    public function getRedirectUrl(Model $record): ?string
    {
        return QuoteResource::getUrl('index'); // Redirige vers l'index
    }
}

// Classe sans redirection (reste sur la page)
class ToValidated extends Transition
{
    // N'implémente pas HasRedirection = reste sur la page
}
```

### Logique
- Si la transition implémente `HasRedirection` et `getRedirectUrl()` retourne une URL → redirection
- Sinon → reste sur la page courante

Voir `app/Filament/Contracts/README.md` pour plus de détails.

## Note importante
Ces overrides corrigent les bugs du plugin tout en gardant la compatibilité totale avec l'API originale.