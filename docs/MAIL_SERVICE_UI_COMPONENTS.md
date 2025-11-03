# Documentation des composants UI Mail Services

Cette documentation décrit les composants d'interface utilisateur pour l'affichage et la gestion des services de traitement d'emails.

## MailServiceColumn - Composant de colonne Filament

Le composant `MailServiceColumn` permet d'afficher les services de traitement d'emails sous forme de boutons interactifs dans les tables Filament.

### Utilisation de base

```php
use App\Filament\Components\Tables\MailServiceColumn;

// Dans votre Resource ou RelationManager
Tables\Columns\MailServiceColumn::make('services')
    ->serviceType('email-draft')  // 'email-draft' ou 'email-in'
    ->openMode('results');        // 'view', 'edit', ou 'results'
```

### Configuration avancée

```php
MailServiceColumn::make('services')
    ->serviceType('email-draft')
    ->openMode('results')
    ->modalWidth('lg')              // Taille du modal : xs, sm, md, lg, xl, 2xl
    ->buttonSize('w-32 h-32')       // Taille des boutons : w-24 h-24, w-32 h-32, etc.
    ->showMessage(true);            // Afficher les messages de retour à côté des icônes
```

### Options disponibles

#### `serviceType(string $type)`
Définit le type de service à afficher :
- `'email-draft'` : Services pour les brouillons
- `'email-in'` : Services pour les emails entrants

#### `openMode(string $mode)`
Définit le mode d'ouverture des modals :
- `'view'` : Affichage des configurations (lecture seule)
- `'edit'` : Édition des configurations
- `'results'` : Affichage des résultats de traitement

#### `modalWidth(string $width)`
Contrôle la largeur du modal :
- `'xs'` : Extra petit
- `'sm'` : Petit  
- `'md'` : Moyen
- `'lg'` : Large
- `'xl'` : Extra large (défaut)
- `'2xl'` : Très large

#### `buttonSize(string $size)`
Définit la taille des boutons avec les classes Tailwind :
- `'w-16 h-16'` : Petit (64x64px)
- `'w-24 h-24'` : Moyen (96x96px) - défaut
- `'w-32 h-32'` : Large (128x128px)

#### `showMessage(bool $show)`
Active/désactive l'affichage des messages de retour :
- `false` : Boutons carrés standard (défaut)
- `true` : Boutons étendus avec messages à droite

### Design et apparence

#### Couleurs des bordures selon le statut
- **Vert** (`border-success-500`) : Service en mode "actif" 
- **Bleu** (`border-info-500`) : Service en mode "test"
- **Gris** (`border-gray-400`) : Service "inactif"

#### Icônes de statut
- **Coin supérieur gauche** : Mode d'ouverture
  - 👁️ `heroicon-o-eye` : Mode "results" 
  - ✏️ `heroicon-o-pencil` : Mode "edit" ou "view"
- **Coin inférieur droit** : Statut du service
  - ✅ `heroicon-o-check` : Succès
  - ⚠️ `heroicon-o-exclamation-triangle` : Bloqué  
  - ❌ `heroicon-o-x-mark` : Erreur

#### Layout avec messages
Quand `showMessage(true)` est activé :
- **Partie gauche** : Icône + titre (carré fixe)
- **Partie droite** : Message de retour (texte tronqué avec `...`)
- **Survol** : Affichage du message complet via `title`

### Exemples d'utilisation

#### Configuration simple pour les résultats
```php
MailServiceColumn::make('mail_services')
    ->serviceType('email-draft')
    ->openMode('results');
```

#### Configuration avancée avec messages
```php
MailServiceColumn::make('mail_services')
    ->serviceType('email-draft') 
    ->openMode('results')
    ->modalWidth('lg')
    ->buttonSize('w-20 h-20')
    ->showMessage(true);
```

#### Pour l'édition des configurations
```php
MailServiceColumn::make('mail_services')
    ->serviceType('email-draft')
    ->openMode('edit')
    ->modalWidth('xl');
```

## MailServiceCell - Composant Livewire

Le composant `MailServiceCell` gère l'interaction et l'affichage des boutons de services. Il est automatiquement utilisé par `MailServiceColumn`.

### Fonctionnalités

- **Rendu dynamique** des boutons selon les configurations
- **Gestion des modals** Filament pour l'affichage/édition
- **Intégration ServiceFormBuilder** pour les formulaires automatiques
- **États visuels** avec couleurs et icônes dynamiques
- **Chargement asynchrone** avec spinners pendant les requêtes

### Cycle de vie

1. **Initialisation** : Récupération des données de services via `rebuildServicesData()`
2. **Affichage** : Rendu des boutons avec styles dynamiques
3. **Interaction** : Ouverture des modals au clic
4. **Modal** : Génération automatique du contenu via `ServiceFormBuilder`

## ServiceFormBuilder - Générateur de formulaires

Le `ServiceFormBuilder` génère automatiquement les formulaires et infolists pour chaque service selon son mode d'utilisation.

### Types de contenu généré

#### Mode 'edit'
- **Formulaire Filament** avec tous les champs configurables du service
- **Validation automatique** des données
- **Sauvegarde** dans `services_options`

#### Mode 'view' 
- **Infolist Filament** des configurations actuelles
- **Affichage lecture seule** des paramètres

#### Mode 'results'
- **Infolist Filament** des résultats de traitement  
- **Messages de statut** (succès, blocage, erreur)
- **Données métier** retournées par le service

### Intégration automatique

Chaque service définit ses propres formulaires via :
```php
public static function getForm(): array
{
    return [
        TextInput::make('agent_id')
            ->label('ID Agent Mistral')
            ->visible(fn($get) => in_array($get('mode'), ['actif', 'test'])),
        // Plus de champs...
    ];
}
```

Le `ServiceFormBuilder` utilise ces définitions pour créer automatiquement l'interface appropriée selon le contexte.

## Changelog

### Novembre 2025 - Nouvelles fonctionnalités

**🎨 Design amélioré :**
- Bordures colorées selon le statut du service
- Icônes de statut positionnées dans les coins
- Support des messages de retour dans les boutons

**⚙️ Configuration flexible :**
- Taille de modal configurable (`modalWidth()`)
- Taille de boutons configurable (`buttonSize()`) 
- Affichage des messages optionnel (`showMessage()`)

**🔧 Architecture simplifiée :**
- Suppression de l'état "Partial" pour une logique plus claire
- Gestion cohérente des services bloqués
- Interface utilisateur unifiée pour tous les types de services

Cette architecture d'interface offre une **expérience utilisateur fluide**, une **configuration flexible**, et un **design cohérent** pour la gestion de vos services de traitement d'emails ! ✨