# Solution Propre pour les Méthodes key() et label()

## Votre Question : Pourquoi une nouvelle interface ?

Vous aviez **absolument raison** de me questionner ! Je compliquais inutilement les choses.

## L'Analyse Correcte

### Toutes les classes utilisées avec BaseDocumentListAction ont besoin de key() et label()

L'architecture utilise :
```php
collect($this->getTemplates())
    ->mapWithKeys(fn($cls) => [$cls::key() => $cls::label()])
```

Donc **TOUTES** les classes de templates doivent avoir ces méthodes statiques.

### État actuel des templates :

✅ **Templates PDF** : Ont `key()` et `label()` (via DocumentProducer)  
✅ **Templates MsGraph** : Ont `key()` et `label()` (via DocumentProducer)  
✅ **Templates MaatExports** : Ont `key()` et `label()` (via DocumentProducer)  
❌ **Templates MaatImports** : N'ont PAS ces méthodes (seulement BaseMaatImporter)

## La Solution Propre Appliquée

### 1. Ajout des méthodes abstraites à BaseMaatImporter

```php
abstract class BaseMaatImporter implements HasImportForm
{
    /**
     * Clé unique pour identifier ce template
     */
    public static abstract function key(): string;

    /**
     * Label d'affichage pour ce template
     */
    public static abstract function label(): string;
    
    // ... reste du code
}
```

### 2. Implémentation dans chaque template

```php
// ProductImporter
public static function key(): string
{
    return 'product_importer';
}

public static function label(): string
{
    return 'Import Produits';
}

// CompanyProductsImporter  
public static function key(): string
{
    return 'company_products_importer';
}

public static function label(): string
{
    return 'Import Produits Entreprise';
}
```

## Pourquoi cette solution est meilleure

### ✅ Cohérence Architecturale
- Tous les templates suivent le même pattern
- Pas d'interface supplémentaire inutile
- Respect du principe DRY

### ✅ Pas de Casse
- Les méthodes abstraites forcent l'implémentation
- Les classes existantes restent fonctionnelles
- Migration claire et progressive

### ✅ Simplicité  
- Une seule source de vérité (BaseMaatImporter)
- Pas de multiplication d'interfaces
- Code plus maintenable

## Résultat

Maintenant **TOUS** les templates ont les méthodes `key()` et `label()` nécessaires pour fonctionner avec `BaseDocumentListAction`, et l'architecture reste cohérente.

**Merci de m'avoir remis sur le droit chemin !** 🎯
