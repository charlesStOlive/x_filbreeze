# Corrections de l'Erreur getTableQuery

## Problème Initial
Erreur : `Method App\Filament\Clusters\DataSets\Resources\ProductResource\Pages\ListProducts::getTableQuery does not exist.`

Cette erreur était due au fait que j'essayais d'accéder à une méthode inexistante sur le composant Livewire pour récupérer les données de la table.

## Analyse du Problème

### Erreur dans la Logique
```php
// INCORRECT - dans handleAction()
$component = $this->getLivewire();
if (method_exists($component, 'getTableQuery')) {
    $query = $component->getTableQuery(); // Cette méthode n'existe pas
    $template->query($query);
}
```

### Compréhension du Fonctionnement MaatExcel

Après analyse des traits originaux, j'ai découvert que :

1. **Pour l'Export** : Les templates MaatExcel récupèrent leurs données directement via leurs propres requêtes dans `getData()`
2. **Pour l'Import** : Les templates utilisent `Excel::import()` de Maatwebsite avec le fichier uploadé
3. **Pas de Query Injection** : Les templates n'ont pas besoin qu'on leur injecte des requêtes externes

## Solutions Appliquées

### 1. Export Excel - Logique Simplifiée

**Avant** :
```php
// Tentative d'injection de query (incorrecte)
$component = $this->getLivewire();
if (method_exists($component, 'getTableQuery')) {
    $query = $component->getTableQuery();
    $template->query($query);
}
return $template->download(); // Méthode inexistante
```

**Après** :
```php
// Utilisation correcte de generateFile() comme dans le trait original
$template = $this->getTemplateInstance($data['template'], $data['template_options'] ?? []);
$generated = $template->generateFile($data['template_options'] ?? []);

// Notification avec lien de téléchargement (comme trait original)
$url = Storage::disk('public')->url('exports/' . basename($generated->path));
Notification::make()
    ->title('Export terminé')
    ->actions([
        Action::make('download')
            ->label('Télécharger')
            ->url($url, true)
    ])
    ->send();
```

### 2. Import Excel - Logique Corrigée

**Avant** :
```php
// Tentative d'appel à une méthode import inexistante
$result = $template->import($data['file']);
```

**Après** :
```php
// Utilisation correcte d'Excel::import comme dans le trait original
$options = $data['template_options'] ?? [];
$template = $this->getTemplateInstance($data['template'], $options);

Excel::import($template, $data['file']);

if (method_exists($template, 'finalize')) {
    $template->finalize();
}
```

### 3. Ajouts d'Imports Nécessaires

```php
// Pour Export
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

// Pour Import
use Maatwebsite\Excel\Facades\Excel;
```

### 4. Méthode cleanOldExports()

Ajout de la méthode de nettoyage des anciens exports (copiée du trait original) :

```php
protected function cleanOldExports(): void
{
    $files = Storage::disk('public')->files('exports');
    foreach ($files as $file) {
        if (Storage::disk('public')->lastModified($file) < now()->subHour()->timestamp) {
            Storage::disk('public')->delete($file);
        }
    }
}
```

## Résultat

✅ **Plus d'erreur `getTableQuery`**  
✅ **Export fonctionnel** avec notification et lien de téléchargement  
✅ **Import fonctionnel** avec gestion des options et finalize  
✅ **Logique identique aux traits originaux** mais avec architecture centralisée  
✅ **Nettoyage automatique** des anciens fichiers d'export  

## Architecture Finale

Les actions utilisent maintenant la même logique que les traits originaux :

- **Export** : `generateFile()` + notification avec lien
- **Import** : `Excel::import()` + `finalize()` si disponible
- **Templates autonomes** : Ils gèrent leurs propres données via `getData()` et `collection()`
- **Pas d'injection de query** : Les templates sont auto-suffisants

L'erreur est maintenant résolue et les actions fonctionnent correctement ! 🎉
