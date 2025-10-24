# Architecture unifiée d'analyse d'états

## Vue d'ensemble

L'architecture d'analyse d'états unifie les différentes approches de génération de diagrammes et d'analyse des états de modèles Laravel avec Spatie ModelStates.

### Composants principaux

1. **StateParserService** - Service unifié d'analyse des modèles et états
2. **StateFormatterService** - Gestionnaire de formatters extensible
3. **StateAnalysisService** - Service façade principal
4. **Formatters** - Système de formatters basé sur des interfaces
5. **StateAnalysis Facade** - Interface simple d'utilisation

## Services disponibles

### StateParserService

Service central qui analyse les modèles et extrait les informations d'état :

```php
use App\Services\StateParserService;

$parser = app(StateParserService::class);

// Obtenir tous les modèles avec états
$models = $parser->getModelsWithStates();

// Parser un modèle spécifique
$data = $parser->parseModel('App\Models\Invoice', [
    'include_initial_state' => true,
    'include_transitions' => true
]);

// Parser une instance de modèle
$invoice = new Invoice();
$data = $parser->parseModelInstance($invoice);
```

### StateFormatterService

Gestionnaire de formatters extensible :

```php
use App\Services\StateFormatterService;
use App\Services\Formatters\MermaidFormatter;

$formatter = app(StateFormatterService::class);

// Enregistrer un nouveau formatter
$formatter->registerFormatter(new CustomFormatter());

// Formatter des données
$result = $formatter->format($parsedData, 'mermaid', [
    'include_styles' => true
]);

// Obtenir les formatters disponibles
$available = $formatter->getAvailableFormats();
```

### StateAnalysisService (Service principal)

Service façade qui combine parser et formatter :

```php
use App\Services\StateAnalysisService;

$analysis = app(StateAnalysisService::class);

// Analyse simple
$mermaid = $analysis->toMermaid('App\Models\Invoice');
$json = $analysis->toJson('App\Models\Invoice');
$array = $analysis->toArray('App\Models\Invoice');

// Analyse avec options
$data = $analysis->analyze('App\Models\Invoice', 'json', [
    'format' => 'mermaid',
    'pretty_print' => true
]);

// Analyse en lot
$results = $analysis->batchAnalyze([
    'App\Models\Invoice',
    'App\Models\Quote'
], 'mermaid');

// Statistiques système
$stats = $analysis->getStatistics();
```

## Facade StateAnalysis

Interface la plus simple d'utilisation :

```php
use App\Facades\StateAnalysis;

// Génération directe
$mermaid = StateAnalysis::toMermaid('App\Models\Invoice');
$json = StateAnalysis::toJson('App\Models\Invoice');
$array = StateAnalysis::toArray('App\Models\Invoice');

// Analyse avec options
$data = StateAnalysis::analyze('App\Models\Invoice', 'mermaid', [
    'include_styles' => true,
    'include_comments' => true
]);

// Informations système
$stats = StateAnalysis::getStatistics();
$models = StateAnalysis::getModelsWithStates();
$formats = StateAnalysis::getAvailableFormats();

// Gestion du cache
StateAnalysis::clearCache('App\Models\Invoice');
```

## Formatters disponibles

### MermaidFormatter

Génère des diagrammes Mermaid :

```php
$mermaid = StateAnalysis::toMermaid('App\Models\Invoice', [
    'type' => 'flowchart',           // Type de diagramme
    'direction' => 'LR',             // Direction (LR, TD, etc.)
    'include_styles' => true,        // Inclure les styles CSS
    'include_comments' => true,      // Inclure les commentaires
    'include_tooltips' => false,     // Inclure les tooltips
    'max_line_length' => 30,         // Longueur max des lignes
]);
```

### JsonFormatter

Génère du JSON avec différents formats :

```php
$json = StateAnalysis::toJson('App\Models\Invoice', [
    'format' => 'mermaid',           // mermaid, raw, simple
    'pretty_print' => true,          // Formatage JSON
    'include_metadata' => true,      // Inclure les métadonnées
]);
```

### ArrayFormatter

Génère des arrays PHP avec différentes structures :

```php
$array = StateAnalysis::toArray('App\Models\Invoice', [
    'format' => 'structured',        // structured, flat, legacy
    'include_metadata' => true,      // Inclure les métadonnées
]);
```

## Extensibilité

### Créer un formatter personnalisé

```php
use App\Contracts\StateFormatterInterface;

class CustomFormatter implements StateFormatterInterface
{
    public function getName(): string
    {
        return 'custom';
    }

    public function getMimeType(): ?string
    {
        return 'text/custom';
    }

    public function getFileExtension(): string
    {
        return 'custom';
    }

    public function format(array $data, array $options = []): mixed
    {
        // Votre logique de formatage
        return $this->generateCustomFormat($data, $options);
    }

    public function validateOptions(array $options): array
    {
        // Validation des options
        return [];
    }

    public function getDefaultOptions(): array
    {
        return [
            'custom_option' => 'default_value'
        ];
    }
}
```

### Enregistrer le formatter

```php
// Dans un Service Provider
use App\Services\StateFormatterService;

public function boot()
{
    $formatter = app(StateFormatterService::class);
    $formatter->registerFormatter(new CustomFormatter());
}

// Ou via la configuration
// config/state-analysis.php
'custom_formatters' => [
    App\Formatters\CustomFormatter::class,
],
```

## Configuration

Le fichier `config/state-analysis.php` permet de configurer :

- **Cache** : Durée, store, activation
- **Découverte de modèles** : Répertoires, namespace, exclusions
- **Formatters** : Options par défaut pour chaque formatter
- **Sécurité** : Modèles autorisés/bloqués, limites de batch
- **Performance** : Limites de temps et mémoire
- **Debug** : Logging, traces d'erreur

## Intégration avec les composants existants

### Trait HasMermaidStateDiagram

Le trait utilise automatiquement le nouveau service :

```php
use App\Traits\HasMermaidStateDiagram;

class Invoice extends Model
{
    use HasStates, HasMermaidStateDiagram;

    // La méthode getMermaidData() utilise StateAnalysisService
    public function showDiagram()
    {
        return $this->getMermaidData();
    }
}
```

### DocumentStatesCommand

La commande console a été refactorisée pour utiliser le service :

```bash
# Utilise StateAnalysisService en interne
php artisan states:analyze Invoice --format=mermaid-json
```

### API endpoints

Les contrôleurs utilisent le service unifié :

```php
// Dans un contrôleur
public function getMermaidDiagram(string $model)
{
    return StateAnalysis::toJson($model, [
        'format' => 'mermaid',
        'pretty_print' => true
    ]);
}
```

## Commandes disponibles

```bash
# Vue d'ensemble du système
php artisan state:demo

# Analyser un modèle spécifique
php artisan state:demo Invoice --format=mermaid
php artisan state:demo Invoice --format=json
php artisan state:demo Invoice --format=array
php artisan state:demo Invoice --format=all

# Commande existante (refactorisée)
php artisan states:analyze Invoice --format=mermaid-json
```

## Avantages de l'architecture unifiée

1. **Source unique de vérité** : Un seul endroit pour la logique d'analyse
2. **Extensibilité** : Système de formatters basé sur des interfaces
3. **Cohérence** : Même logique partout, résultats cohérents
4. **Performance** : Cache intégré, optimisations centralisées
5. **Maintenance** : Code DRY, pas de duplication
6. **Flexibilité** : Supports multiples formats et options
7. **Testabilité** : Services isolés, facilement testables

## Migration depuis l'ancienne architecture

L'architecture est rétrocompatible :

- Le trait `HasMermaidStateDiagram` fonctionne sans changement
- La commande `DocumentStatesCommand` utilise le nouveau service
- Les API endpoints existants continuent de fonctionner
- Fallback automatique vers l'ancienne logique en cas d'erreur

La migration se fait progressivement sans casser l'existant.