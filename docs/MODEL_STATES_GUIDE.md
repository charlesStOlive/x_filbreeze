# Système de Gestion d'États (Model States)

## Vue d'ensemble

Ce projet utilise un système d'états basé sur **Spatie Laravel Model States** intégré avec **FilamentStateFusion** pour gérer les workflows des modèles (Quotes, Invoices, etc.) directement dans l'interface Filament.

## Stack technique

- **[Spatie Laravel Model States v2.0](https://github.com/spatie/laravel-model-states)** : Gestion des états et transitions
- **[FilamentStateFusion v2.1](https://github.com/a909m/filament-state-fusion)** : Intégration Filament avec corrections personnalisées
- **Laravel v11** + **Filament v4**

## Architecture

### Structure des dossiers

```text
app/
├── Models/States/
│   ├── Quote/
│   │   ├── QuoteState.php (classe abstraite)
│   │   ├── Draft.php
│   │   ├── Validated.php
│   │   ├── Canceled.php
│   │   ├── ToValidated.php
│   │   ├── ToCanceled.php
│   │   ├── ToDraft.php
│   │   └── CanceledToDraft.php
│   └── Invoice/
│       └── ... (même structure)
├── Filament/
│   ├── Overrides/ (Corrections des bugs du plugin)
│   │   ├── Actions/
│   │   │   ├── StateFusionActionGroup.php
│   │   │   └── StateFusionAction.php
│   │   └── Concerns/
│   │       └── ResolvesActionAttributes.php
│   └── Contracts/
│       └── HasRedirection.php
└── Console/Commands/
    └── MakeStatesCommand.php
```

## Guide de démarrage rapide

### 1. Créer un nouveau système d'états

```bash
php artisan make:states SupplierInvoice
```

La commande interactive vous demandera :

- Les états disponibles avec leurs labels
- Les transitions possibles
- Les labels des transitions

### 2. Configurer le modèle

```php
// app/Models/SupplierInvoice.php
use Spatie\ModelStates\HasStates;
use App\Models\States\SupplierInvoice\SupplierInvoiceState;

class SupplierInvoice extends Model
{
    use HasStates;

    protected $casts = [
        'state' => SupplierInvoiceState::class,
    ];
}
```

### 3. Ajouter dans Filament Resource

```php
// app/Filament/Resources/SupplierInvoiceResource.php
use App\Filament\Overrides\Actions\StateFusionActionGroup;

public static function form(Form $form): Form
{
    return $form->schema([
        // Vos champs...
        StateFusionActionGroup::make('state')
            ->label('Actions')
            ->color('primary')
            ->icon('heroicon-o-cog')
            ->size('sm'),
    ]);
}
```

## Concepts détaillés

### États (States)

Chaque état hérite de la classe abstraite du modèle et implémente les interfaces Filament :

```php
class Draft extends QuoteState implements HasDescription, HasColor, HasIcon, HasLabel
{
    public static $name = 'draft';
    public $isSaveHidden = false;

    public function getLabel(): string
    {
        return __('Brouillon');
    }

    public function getColor(): string
    {
        return 'gray';
    }

    public function getIcon(): string
    {
        return 'heroicon-o-pencil';
    }

    public function getDescription(): ?string
    {
        return __('Brouillon');
    }
}
```

### Transitions

Les transitions gèrent le passage d'un état à un autre avec formulaires optionnels :

```php
class ToValidated extends Transition implements FilamentSpatieTransition, HasColor, HasLabel, HasIcon
{
    use ProvidesSpatieTransitionToFilament;

    public function __construct(
        private Quote $quote,
        private ?array $data = null
    ) {}

    public function getLabel(): string
    {
        return __('Valider devis');
    }
 
    public function getColor(): string
    {
        return 'success';
    }

    public function getIcon(): string
    {
        return 'heroicon-o-check';
    }

    public function handle(): Quote
    {
        $this->quote->state = new Validated($this->quote);
        $this->quote->validated_at = $this->data['validated_at'];
        $this->quote->save();
        return $this->quote;
    }

    public function form(): array | Closure | null
    {
        return [
            DateTimePicker::make('validated_at')
                ->label('Validé le')
                ->default(now())
                ->helperText(__('Date de validation'))
        ];
    }
}
```

### Configuration des transitions

Dans la classe State abstraite :

```php
public static function config(): StateConfig
{
    return parent::config()
        ->default(Draft::class)
        ->allowTransition(Draft::class, Validated::class, ToValidated::class)
        ->allowTransition(Draft::class, Canceled::class, ToCanceled::class)
        ->allowTransition(Canceled::class, Draft::class, CanceledToDraft::class)
        ->allowTransition(Validated::class, Draft::class, ToDraft::class);
}
```

## Fonctionnalités avancées

### Redirections post-transition

Implémentez l'interface `HasRedirection` pour rediriger après une transition :

```php
use App\Filament\Contracts\HasRedirection;

class ToValidated extends Transition implements HasRedirection
{
    // ... autres méthodes

    public function getRedirectUrl($model): ?string
    {
        // Rediriger vers la page d'édition
        return route('filament.admin.resources.quotes.edit', $model);
        
        // Ou vers la liste
        // return route('filament.admin.resources.quotes.index');
    }
}
```

### Formulaires conditionnels

Utilisez `form()` pour collecter des données avant la transition :

```php
public function form(): array | Closure | null
{
    return [
        DateTimePicker::make('validated_at')
            ->label('Date de validation')
            ->required()
            ->default(now()),
            
        Textarea::make('notes')
            ->label('Notes')
            ->rows(3),
    ];
}
```

### Transitions conditionnelles

Différentes transitions selon l'état source :

```php
// CanceledToDraft.php - Spécifique pour Canceled → Draft
public function getLabel(): string
{
    return __('Revenir au brouillon');
}

// ToDraft.php - Pour Validated → Draft  
public function getLabel(): string
{
    return __('Annuler validation');
}
```

## Système d'override

En raison de bugs dans FilamentStateFusion v2.1, nous utilisons des classes d'override :

### StateFusionActionGroup

- **Problème** : Méthodes de chaînage non fonctionnelles
- **Solution** : Override complet avec support des méthodes fluides

### StateFusionAction

- **Problème** : Types de couleur incompatibles, pas de support HasRedirection
- **Solution** : Support couleurs string et array, gestion des redirections

### ResolvesActionAttributes

- **Problème** : Restriction de type couleur trop stricte
- **Solution** : Support complet des types Filament Color

## Templates (Stubs)

### Commande de génération

```bash
php artisan make:states ModelName
```

### Stubs disponibles

1. **`state_class.stub`** - Classe abstraite State
2. **`state.stub`** - États individuels  
3. **`to_transition.stub`** - Transitions simples
4. **`from_to_transition.stub`** - Transitions spécifiques
5. **`redirected_transition.stub`** - Transitions avec redirection

## Personnalisation UI

### Couleurs disponibles

```php
// Couleurs string (recommandé)
'primary', 'secondary', 'success', 'warning', 'danger', 'info', 'gray'

// Couleurs Filament Color (array)
Color::Red, Color::Green, Color::Blue, etc.
```

### Icônes Heroicons

```php
'heroicon-o-pencil'     // Draft
'heroicon-o-check'      // Validation
'heroicon-o-x-mark'     // Annulation
'heroicon-o-arrow-right' // Transition générique
```

### Tailles des boutons

```php
StateFusionActionGroup::make('state')
    ->size('sm')    // sm, md, lg
    ->color('primary')
    ->icon('heroicon-o-cog')
```

## Debug et logs

### Activer les logs temporairement

```php
// Dans une transition
\Log::info('Debug transition', [
    'current_state' => get_class($this->model->state),
    'model_id' => $this->model->id,
    'data' => $this->data
]);

// Dans StateFusionAction
\Log::info('Action triggered', [
    'record_id' => $record->id,
    'transition' => $this->getToState()
]);
```

### Vérifier les transitions

```php
// Tester si une transition est possible
$quote->state->canTransitionTo(Validated::class); // true/false

// Lister les transitions possibles
$quote->state->getTransitionableStates();
```

## Checklist de création

### Nouveau modèle avec états

1. **Créer les états et transitions**

   ```bash
   php artisan make:states ModelName
   ```

2. **Configurer le modèle**

   ```php
   use HasStates;
   protected $casts = ['state' => ModelNameState::class];
   ```

3. **Ajouter dans Filament Resource**

   ```php
   StateFusionActionGroup::make('state')
   ```

4. **Migration base de données**

   ```php
   $table->string('state')->default('draft');
   // Champs additionnels selon transitions
   $table->timestamp('validated_at')->nullable();
   ```

5. **Tester les transitions**
   - Interface Filament
   - Vérifications conditionnelles
   - Formulaires de transition

### Maintenance

- **Logs** : Activer temporairement pour debug
- **Tests** : Vérifier les workflows complets
- **Documentation** : Mettre à jour cette doc si nouveaux patterns

## Ressources

- [Spatie Laravel Model States](https://spatie.be/docs/laravel-model-states/v2/introduction)
- [FilamentStateFusion GitHub](https://github.com/a909m/filament-state-fusion)
- [Filament Actions](https://filamentphp.com/docs/3.x/actions/overview)
- [Heroicons](https://heroicons.com/)

## Historique versions

- **v1.0** (Oct 2025) : Mise en place FilamentStateFusion + overrides
- **v1.1** (Oct 2025) : Ajout HasRedirection + commande génération
- **v1.2** (Oct 2025) : Documentation complète + stubs mis à jour

---

Documentation générée le 22 octobre 2025
 
 