# Actions de Document pour les Listes

## Architecture Nouvelle

Pour les actions qui ne nécessitent pas d'enregistrement spécifique (comme les imports/exports de listes), nous avons créé une architecture dédiée.

## Classes de Base

### BaseDocumentListAction

```php
abstract class BaseDocumentListAction extends Action
{
    public function templates(array $templates): static
    protected function getTemplates(): array
    protected function getDefaultTemplate(): string
    protected function getTemplateInstance(string $key, ?array $options = null): mixed
    
    // Méthodes abstraites à implémenter
    abstract protected function getServiceSchema(): array;
    abstract protected function handleAction(array $data): mixed;
}
```

### Différences avec BaseDocumentAction

- **Pas de `$record`** : Les méthodes ne prennent pas de `$record` en paramètre
- **Templates simplifiés** : Gestion directe des templates sans contexte d'enregistrement
- **Schema simplifié** : `getServiceSchema()` sans paramètre `$record`
- **Action simplifiée** : `handleAction(array $data)` sans `$record`

## Actions Spécialisées

### Pour les Exports de Liste

```php
// app/Services/MaatExports/Filament/Actions/ExportMaatExcelListAction.php
class ExportMaatExcelListAction extends BaseDocumentListAction
{
    protected function getServiceSchema(): array
    {
        // Interface d'export avec aperçu des données
    }

    protected function handleAction(array $data): mixed
    {
        // Récupération des données via $this->getLivewire()->getTableQuery()
        // Export des données
    }
}
```

### Pour les Imports de Liste

```php
// app/Services/MaatImports/Filament/Actions/ImportMaatExcelListAction.php  
class ImportMaatExcelListAction extends BaseDocumentListAction
{
    protected function getServiceSchema(): array
    {
        // Interface d'import avec upload de fichier
    }

    protected function handleAction(array $data): mixed
    {
        // Import du fichier via le template
        // Rafraîchissement de la liste
    }
}
```

## Utilisation

### Dans une Page de Liste Filament

```php
// app/Filament/Resources/ProductResource/Pages/ListProducts.php
use App\Services\MaatImports\Filament\Actions\ImportMaatExcelListAction;
use App\Services\MaatExports\Filament\Actions\ExportMaatExcelListAction;
use App\Services\MaatImports\Templates\Product\ProductImporter;
use App\Services\MaatExports\Templates\Product\ProductMaatExporter;

protected function getHeaderActions(): array
{
    return [
        ActionGroup::make([
            ImportMaatExcelListAction::make('importproduct')
                ->label('Importer les produits')
                ->templates([ProductImporter::class]),
                
            ExportMaatExcelListAction::make('exportProduits')
                ->label('Exporter les produits') 
                ->templates([ProductMaatExporter::class]),
        ])
    ];
}
```

## Avantages de cette Architecture

### 1. **Séparation des Responsabilités**
- `BaseDocumentAction` : Pour les actions sur des enregistrements spécifiques
- `BaseDocumentListAction` : Pour les actions sur des listes/collections

### 2. **Interface Cohérente**
- Même syntaxe `->templates([...])` 
- Interface utilisateur similaire
- Gestion d'erreurs commune

### 3. **Flexibilité**
- Accès aux données de la liste via `$this->getLivewire()->getTableQuery()`
- Support des filtres de table existants
- Rafraîchissement automatique après import

### 4. **Extensibilité**
- Facile d'ajouter d'autres types d'actions de liste
- Templates réutilisables entre actions record et actions liste
- Architecture cohérente avec le reste du système

## Fonctionnalités Spéciales

### Accès aux Données de Liste
```php
// Dans handleAction()
$component = $this->getLivewire();
if (method_exists($component, 'getTableQuery')) {
    $query = $component->getTableQuery();
    $template->query($query);
}
```

### Rafraîchissement de Liste
```php
// Après import
$this->getLivewire()?->dispatch('refresh');
```

Cette architecture maintient la cohérence avec les autres actions de document tout en s'adaptant parfaitement aux besoins spécifiques des listes.
