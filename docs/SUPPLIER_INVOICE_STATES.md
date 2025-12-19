# États et Transitions - SupplierInvoice

## 📊 États disponibles

### Draft (Brouillon)
- **Couleur**: gray
- **Icône**: heroicon-o-pencil
- **Description**: Facture en cours de rédaction
- **État par défaut**: Oui

### Validated (Validé)
- **Couleur**: gray
- **Icône**: heroicon-o-pencil
- **Description**: Facture validée et prête

### Canceled (Annulé)
- **Couleur**: gray
- **Icône**: heroicon-o-pencil
- **Description**: Facture annulée

### Error (Erreur)
- **Couleur**: danger
- **Icône**: heroicon-o-exclamation-circle
- **Description**: Erreur bloquante - nécessite correction

### Warning (Avertissement)
- **Couleur**: warning
- **Icône**: heroicon-o-exclamation-triangle
- **Description**: Avertissement - vérification recommandée

## 🔄 Transitions disponibles

### Depuis Draft
- ✅ **Draft → Validated** (avec validation automatique)
  - Vérifie que `supplier_id` est renseigné → sinon **Warning**
  - Vérifie que `invoice_number` est renseigné → sinon **Error**
  - Vérifie que `invoice_number` est unique pour le supplier → sinon **Error**
  
- ✅ **Draft → Canceled**
- ✅ **Draft → Error** (manuel)
- ✅ **Draft → Warning** (manuel)

### Depuis Validated
- ✅ **Validated → Canceled**

### Depuis Error
- ✅ **Error → Validated**

### Depuis Warning
- ✅ **Warning → Validated**

### Depuis Canceled
- ✅ **Canceled → Draft**

## 🎯 Logique de validation automatique

Lors de la transition **Draft → Validated**, le système effectue les vérifications suivantes :

1. **Vérification du Supplier** (Warning si manquant)
   ```php
   if (empty($supplierInvoice->supplier_id)) {
       // → État Warning
   }
   ```

2. **Vérification du numéro de facture** (Error si manquant)
   ```php
   if (empty($supplierInvoice->invoice_number)) {
       // → État Error
   }
   ```

3. **Vérification de l'unicité** (Error si doublon)
   ```php
   $duplicateExists = SupplierInvoice::where('supplier_id', $supplierInvoice->supplier_id)
       ->where('invoice_number', $supplierInvoice->invoice_number)
       ->where('id', '!=', $supplierInvoice->id)
       ->exists();
   
   if ($duplicateExists) {
       // → État Error
   }
   ```

## 💼 Interface Filament

### Page d'édition

#### Si état = Draft
Affiche 2 boutons spécifiques :
- **Valider** → Passe à Validated (avec validation)
- **Annuler** → Passe à Canceled

#### Si état ≠ Draft
Affiche un bouton automatique **"Changer état"** qui propose toutes les transitions possibles depuis l'état actuel.

### Actions Bulk (sélection multiple)

#### Bulk Validate
- Traite uniquement les factures en état **Draft**
- Applique la même logique de validation que la transition individuelle
- Affiche un résumé : nombre validées / warnings / erreurs

#### Bulk Cancel
- Annule toutes les factures sélectionnées
- Change l'état vers **Canceled**

### Filtres
Filtre par état avec sélection multiple :
- Draft
- Validated
- Error
- Warning
- Canceled

## 📝 Exemple d'utilisation

### 1. Créer une nouvelle facture
```php
$invoice = SupplierInvoice::create([
    'supplier_id' => 1,
    'invoice_number' => 'FAC-2025-001',
    'invoice_at' => now(),
    'total_ttc' => 1000,
]);
// État par défaut : Draft
```

### 2. Tenter de valider
```php
// Via Filament : Cliquer sur "Valider"
// Le système vérifie automatiquement et :
// - Passe à Validated si tout est OK
// - Passe à Warning si supplier_id manque
// - Passe à Error si invoice_number manque ou n'est pas unique
```

### 3. Corriger et re-valider
```php
// Si Error ou Warning, corriger les données
$invoice->update(['invoice_number' => 'FAC-2025-002']);

// Depuis l'interface, utiliser "Changer état" → Validated
// Ou utiliser StateFusionActionGroup automatique
```

## 🔧 Configuration technique

### Modèle
```php
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

### Migration
```php
Schema::table('crm_supplier_invoices', function (Blueprint $table) {
    $table->string('state')->default('draft');
});
```

### Configuration des états
Voir : `app/Models/States/SupplierInvoice/SupplierInvoiceState.php`

## 🎨 Package utilisé

Ce système utilise le package **filament-state-fusion-enhanced** qui combine :
- `spatie/laravel-model-states` pour la gestion des états
- `a909m/filament-statefusion` pour l'intégration Filament
- Fonctionnalités avancées : diagrammes Mermaid, analyse d'états, etc.
