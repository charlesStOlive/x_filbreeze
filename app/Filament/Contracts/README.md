# Interface HasRedirection

## Concept

L'interface `HasRedirection` permet de définir des redirections spécifiques après les transitions d'état. Elle ne s'applique qu'aux **classes de transition** (qui étendent `Transition`), pas aux classes d'état.

## Interface

```php
<?php

namespace App\Filament\Contracts;

use Illuminate\Database\Eloquent\Model;

interface HasRedirection
{
    /**
     * Get the redirection URL after the state transition
     * 
     * @param Model $record The model that underwent the transition
     * @return string|null The URL to redirect to, or null to stay on current page
     */
    public function getRedirectUrl(Model $record): ?string;
}
```

## Utilisation

### Exemple 1: Redirection vers l'index après annulation

```php
<?php

use App\Filament\Contracts\HasRedirection;
use App\Filament\Clusters\Crm\Resources\QuoteResource;

class ToCanceled extends Transition implements HasRedirection
{
    // ... autres méthodes ...

    public function getRedirectUrl(Model $record): ?string
    {
        // Redirige vers l'index des devis après annulation
        return QuoteResource::getUrl('index');
    }
}
```

### Exemple 2: Pas de redirection (reste sur la page)

```php
<?php

class ToValidated extends Transition
{
    // N'implémente pas HasRedirection
    // = reste sur la page courante après transition
}
```

### Exemple 3: Redirection conditionnelle

```php
<?php

class ToArchived extends Transition implements HasRedirection
{
    public function getRedirectUrl(Model $record): ?string
    {
        // Redirige seulement si c'est le dernier devis actif
        if ($record->company->quotes()->active()->count() === 0) {
            return CompanyResource::getUrl('edit', ['record' => $record->company]);
        }
        
        // Sinon reste sur la page
        return null;
    }
}
```

## Logique de fonctionnement

1. **Transition exécutée** : La méthode `handle()` de la transition est appelée
2. **Vérification de redirection** : Le système vérifie si la transition implémente `HasRedirection`
3. **Redirection conditionnelle** :
   - Si `getRedirectUrl()` retourne une URL → redirection
   - Si `getRedirectUrl()` retourne `null` → reste sur la page
   - Si la transition n'implémente pas `HasRedirection` → reste sur la page

## Classes de transition dans le projet

| Transition | Implémente HasRedirection | Comportement |
|------------|---------------------------|--------------|
| `ToCanceled` | ✅ Oui | Redirige vers l'index |
| `ToValidated` | ❌ Non | Reste sur la page |
| `ToDraft` | ❌ Non | Reste sur la page |

## Options de redirection disponibles

```php
// Vers l'index d'une ressource
return QuoteResource::getUrl('index');

// Vers l'édition d'un autre enregistrement
return QuoteResource::getUrl('edit', ['record' => $otherRecord]);

// Vers une page personnalisée
return route('custom.page', ['id' => $record->id]);

// URL absolue
return 'https://external-site.com/success';

// Rester sur la page courante
return null;
```

## Intégration

Cette fonctionnalité est automatiquement intégrée dans `App\Filament\Overrides\Actions\StateFusionAction` et fonctionne avec `StateFusionActionGroup`.