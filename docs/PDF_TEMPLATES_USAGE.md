# Architecture des Actions de Document

## Nouvelle Architecture Centralisée

Tous les services de génération de documents (PDF, Email, Excel Import/Export) utilisent maintenant une architecture commune basée sur `BaseDocumentAction`.

### Classe de Base : BaseDocumentAction

```php
// app/Services/Document/Filament/Actions/BaseDocumentAction.php
abstract class BaseDocumentAction extends Action
{
    protected ?array $templates = null;
    
    public function templates(array $templates): static
    {
        $this->templates = $templates;
        return $this;
    }
    
    // Méthodes communes pour tous les services
    protected function getTemplatesForRecord($record): array
    protected function getDefaultTemplateForRecord($record): string  
    protected function getTemplateInstance(string $key, $record, ?array $options = null): mixed
    
    // Méthodes abstraites que chaque service doit implémenter
    abstract protected function getServiceSchema($record): array;
    abstract protected function handleAction(array $data, $record): mixed;
}
```

## Utilisation par Service

### 1. PDF (GeneratePdfDownload)

```php
GeneratePdfDownload::make('downloadPdf')
    ->templates([
        InvoiceSummaryPdfTemplate::class,
        InvoiceBasePdfTemplate::class,
        InvoiceComplete::class,
    ])
```

### 2. Email (GenerateMsGraphEmailDraft)

```php
GenerateMsGraphEmailDraftNew::make('createEmailDraft')
    ->templates([
        InvoiceSummaryTemplate::class,
        CompanyBaseTemplate::class,
    ])
```

### 3. Export Excel (ExportMaatExcelAction)

```php
ExportMaatExcelActionNew::make('exportExcel')
    ->templates([
        CompanyProductsExporter::class,
        ProductMaatExporter::class,
    ])
```

### 4. Import Excel (ImportMaatExcelAction)

```php
ImportMaatExcelActionNew::make('importExcel')
    ->templates([
        CompanyProductsImporter::class,
        ProductImporter::class,
    ])
```

## Nouvelle syntaxe (recommandée)

Avec la nouvelle syntaxe, vous pouvez maintenant passer directement les classes de templates à l'action `GeneratePdfDownload` :

### Exemple avec un seul template

```php
GeneratePdfDownload::make('downloadPdf')
    ->templates([
        InvoiceSummaryPdfTemplate::class
    ])
```

### Exemple avec plusieurs templates

```php
GeneratePdfDownload::make('downloadPdf')
    ->templates([
        InvoiceSummaryPdfTemplate::class,
        InvoiceBasePdfTemplate::class,
        InvoiceComplete::class,
    ])
```

## Avantages de cette approche

1. **Plus explicite** : Les templates disponibles sont directement visibles dans le code
2. **Pas de configuration externe** : Plus besoin de gérer le fichier `config/templates-pdf.php` 
3. **Plus flexible** : Chaque action peut avoir ses propres templates
4. **Type-safe** : Les classes sont vérifiées à l'exécution

## Fonctionnement

- Si vous définissez des templates explicitement avec `->templates([...])`, ces templates seront utilisés
- Si vous ne définissez rien, le système utilisera toujours le registre de configuration existant (backward compatibility)
- Le premier template de la liste sera sélectionné par défaut
- L'interface utilisateur affichera une liste déroulante avec tous les templates disponibles

## Migration

### Avant (avec configuration)
```php
// config/templates-pdf.php
'invoice' => [
    'default' => InvoiceSummaryPdfTemplate::class,
    'templates' => [
        InvoiceSummaryPdfTemplate::class,
        InvoiceBasePdfTemplate::class,
    ],
],

// Dans la page Filament
GeneratePdfDownload::make('downloadPdf')
```

### Après (syntaxe directe)
```php
// Dans la page Filament
GeneratePdfDownload::make('downloadPdf')
    ->templates([
        InvoiceSummaryPdfTemplate::class,
        InvoiceBasePdfTemplate::class,
    ])
```

## Rétrocompatibilité

L'ancien système continue de fonctionner. Si vous n'utilisez pas `->templates()`, le système utilisera automatiquement la configuration du fichier `config/templates-pdf.php`.
