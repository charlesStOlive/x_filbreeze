# Documentation complète : Architecture MS Graph avec Preflight/Perform

## Vue d'ensemble

L'architecture MS Graph utilise un système de **traitement d'emails en deux phases** :
1. **Preflight** (synchrone) : Validation, préparation et marquage initial
2. **Perform** (asynchrone via queue) : Traitement réel et mise à jour finale

Cette approche garantit une **séparation claire** entre la validation rapide et le traitement lourd, tout en évitant les appels coûteux (comme Mistral AI) pendant la phase de validation.

## Architecture générale

### 🔄 Flux de traitement

```mermaid
graph TD
    A[Webhook MS Graph] --> B[MsGraphNotificationService]
    B --> C[Création EmailDTO]
    C --> D[Pour chaque service actif]
    D --> E[Instanciation Processor]
    E --> F[preflight() - Phase synchrone]
    F --> G{preflight OK?}
    G -->|Oui| H[onQueue() - Mise en queue]
    G -->|Non| I[Service bloqué - Log]
    H --> J[perform() - Traitement asynchrone]
    J --> K[Mise à jour finale]
```

### 📁 Structure des fichiers

```
app/Services/
├── Processors/Emails/
│   ├── BaseEmailDraftProcessor.php          # Classe de base abstraite
│   ├── DraftEmailProcessor.php         # Service de correction de texte
│   ├── TradEmailProcessor.php          # Service de traduction
│   ├── EmailInClientProcessor.php      # Service emails entrants
│   └── Support/
│       └── PreflightResult.php         # Classe de résultat preflight
├── MsGraph/
│   ├── MsGraphNotificationService.php  # Orchestrateur principal
│   ├── ServiceFormBuilder.php          # Générateur de formulaires UI
│   └── MsGraphEmailService.php         # Service API MS Graph
└── EmailsProcessorRegisterServices.php # Registre des services
```

## Création d'un nouveau service

### 1. Créer la classe de service

Créez une nouvelle classe qui étend `BaseEmailDraftProcessor` :

```php
<?php
// app/Services/Processors/Emails/MonNouveauProcessor.php

namespace App\Services\Processors\Emails;

use Exception;
use App\Models\MsgEmailDraft;
use App\Services\Processors\Emails\Support\PreflightResult;
use App\Enums\EmailProcessing\ProcessorStatus;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;

class MonNouveauProcessor extends BaseEmailDraftProcessor
{
    // === MÉTADONNÉES DU SERVICE ===
    
    public static function getKey(): string
    {
        return 'mon-service'; // Clé unique du service
    }
    
    public static function getIcon(): string
    {
        return 'heroicon-o-sparkles'; // Icône Heroicon
    }
    
    public static function getLabel(): string
    {
        return 'Mon Nouveau Service';
    }
    
    public static function getDescription(): string
    {
        return 'Description de ce que fait le service';
    }
    
    public static function getDefaultTriggerCode(): string
    {
        return 'moncode'; // Code par défaut dans l'email (##moncode##)
    }

    // === CONFIGURATION PAR DÉFAUT ===
    
    public static function getDefaults(): array
    {
        return [
            'mode' => 'inactif',
            'ma_option' => 'valeur_par_défaut',
            'mon_boolean' => true,
            'regex_code' => 'moncode',
        ];
    }

    // === INTERFACE UTILISATEUR ===
    
    /**
     * Formulaire d'édition dans l'interface
     */
    public static function getForm(): array
    {
        return [
            TextInput::make('ma_option')
                ->label('Mon Option')
                ->helperText('Description de cette option')
                ->visible(fn($get) => in_array($get('mode'), ['actif', 'test'])),

            Toggle::make('mon_boolean')
                ->label('Mon Booléen')
                ->helperText('Active/désactive une fonctionnalité')
                ->visible(fn($get) => in_array($get('mode'), ['actif', 'test'])),

            TextInput::make('regex_code')
                ->label('Code de déclenchement')
                ->helperText('Code qui doit être présent dans l\'email')
                ->visible(fn($get) => in_array($get('mode'), ['actif', 'test'])),
        ];
    }
    
    /**
     * Infolist d'affichage des options dans l'interface
     */
    public static function getInfoList(): array
    {
        return [
            TextEntry::make('ma_option')
                ->label('Mon Option')
                ->copyable()
                ->icon('heroicon-o-cog'),

            TextEntry::make('mon_boolean')
                ->label('Mon Booléen')
                ->formatStateUsing(fn($state) => $state ? 'Activé' : 'Désactivé')
                ->badge()
                ->color(fn($state) => $state ? 'success' : 'danger'),

            TextEntry::make('regex_code')
                ->label('Code de déclenchement')
                ->formatStateUsing(fn($state) => "## {$state} ##")
                ->badge()
                ->color('primary')
                ->icon('heroicon-o-hashtag'),
        ];
    }
    
    /**
     * Infolist d'affichage des résultats après traitement
     */
    public static function getResultsInfoList(): array
    {
        return [
            TextEntry::make('resultat_traitement')
                ->label('Résultat du traitement')
                ->html()
                ->visible(fn($state) => !empty($state)),

            TextEntry::make('temps_execution')
                ->label('Temps d\'exécution')
                ->suffix(' ms')
                ->badge()
                ->color('info')
                ->visible(fn($state) => !empty($state)),
        ];
    }

    // === LOGIQUE MÉTIER ===
    
    /**
     * Phase 1 : Validation et préparation (synchrone)
     */
    public function preflight(): PreflightResult
    {
        // 1. Validation standard (mode, code, etc.)
        if ($block = $this->guardAndCaptureCode()) {
            $this->setResult('blocked_message', $block->reason);
            $this->updateProcessorStatus(ProcessorStatus::Blocked, [
                'facts' => ['reason' => $block->reason]
            ]);
            $this->email->save();
            return $block;
        }

        // 2. Validations spécifiques à votre service
        $maOption = $this->getServiceOption('ma_option');
        if (empty($maOption)) {
            $reason = 'Option manquante';
            $this->setResult('blocked_message', $reason);
            $this->updateProcessorStatus(ProcessorStatus::Blocked, [
                'facts' => ['reason' => $reason]
            ]);
            $this->email->save();
            return PreflightResult::blocked($reason);
        }

        try {
            // 3. Initialisation du traitement
            $this->beginProcessor(); // Marque le début + active_jobs++
            $this->startProcessingWithPlaceholder(); // "Je travaille" dans l'email
        } catch (Exception $ex) {
            $this->setResult('error_message', $ex->getMessage());
            $this->updateProcessorStatus(ProcessorStatus::Error, [
                'error_message' => $ex->getMessage()
            ]);
            $this->email->has_error = true;
            $this->email->save();
            return PreflightResult::blocked('Préflight en échec');
        }

        return PreflightResult::ok();
    }
    
    /**
     * Phase 2 : Traitement réel (asynchrone via queue)
     */
    protected function perform(): MsgEmailDraft
    {
        try {
            // 1. Récupération des paramètres
            $mode = $this->getResult('mode', 'inactif');
            $maOption = $this->getServiceOption('ma_option');
            $monBoolean = (bool)$this->getServiceOption('mon_boolean', true);
            
            $startTime = microtime(true);
            
            // 2. Traitement du contenu
            $clean = $this->removeRegexKeyAndLineIfEmptyHTML($this->emailData->bodyOriginal);
            
            if ($mode === 'test') {
                // Mode test : simulation sans action réelle
                $resultat = "Simulation : traiterait avec option '{$maOption}'";
                
                $this->finishProcessor(ProcessorStatus::Success, [
                    'resultat_traitement' => $resultat,
                    'temps_execution' => round((microtime(true) - $startTime) * 1000, 2),
                    'mode_test' => true,
                ]);
                
                return $this->email;
            }
            
            // 3. Traitement réel
            $resultat = $this->effectuerMonTraitement($clean, $maOption, $monBoolean);
            
            // 4. Mise à jour de l'email
            if ($monBoolean) {
                $this->updateBody($resultat);
                $this->markOriginalProcessed('Terminé - Traité par Mon Service');
            } else {
                // Autre logique selon vos besoins
            }
            
            // 5. Succès
            $this->finishProcessor(ProcessorStatus::Success, [
                'resultat_traitement' => $resultat,
                'temps_execution' => round((microtime(true) - $startTime) * 1000, 2),
                'option_utilisee' => $maOption,
            ]);
            
        } catch (Exception $e) {
            // 6. Gestion d'erreur
            $this->finishProcessor(ProcessorStatus::Error, [
                'error_message' => $e->getMessage(),
            ]);
        }

        return $this->email;
    }
    
    /**
     * Votre logique métier spécifique
     */
    private function effectuerMonTraitement(string $content, string $option, bool $boolean): string
    {
        // Implémentez votre logique ici
        return "Contenu traité avec option: {$option}";
    }
}
```

### 2. Enregistrer le service

Ajoutez votre service dans la configuration `config/msgraph.php` :

```php
// config/msgraph.php

'email-draft' => [
    \App\Services\Processors\Emails\DraftEmailProcessor::class,
    \App\Services\Processors\Emails\TradEmailProcessor::class,
    \App\Services\Processors\Emails\MonNouveauProcessor::class, // ← Ajoutez ici
],
```

## Méthodes disponibles dans BaseEmailDraftProcessor

### 🔧 Méthodes de gestion des options et résultats

```php
// Récupération d'une option de configuration du service
protected function getServiceOption(string $key, mixed $default = null): mixed

// Récupération d'un résultat stocké
protected function getResult(string $key, mixed $default = null): mixed

// Stockage d'un résultat
protected function setResult(string $key, mixed $value): void
```

### 📊 Méthodes de gestion du statut

```php
// Mise à jour du statut du processeur
protected function updateProcessorStatus(ProcessorStatus $status, array $extra = []): void

// Début du traitement (active_jobs++, status = Processing)
protected function beginProcessor(): void

// Fin du traitement (active_jobs--, recalcul du statut global)
protected function finishProcessor(ProcessorStatus $final, array $facts = []): void

// Recalcul du statut global de l'email (logique simplifiée)
protected function recomputeEmailStatus(): void
```

### 🛡️ Méthodes de validation

```php
// Validation standard (mode, code regex)
protected function guardAndCaptureCode(): ?PreflightResult

// Insertion du placeholder "Je travaille"
protected function startProcessingWithPlaceholder(): void
```

### ✏️ Méthodes de manipulation du contenu

```php
// Insertion de texte dans la clé regex (##code##)
protected function insertInRegexKey(string $body, string $replacement): string

// Remplacement complet de la clé regex
protected function replaceRegexKey(string $body, string $replacement): string

// Suppression de la clé regex et nettoyage des lignes vides
protected function removeRegexKeyAndLineIfEmptyHTML(string $htmlText): string

// Mise à jour du contenu de l'email via MS Graph
protected function updateBody(string $content): void

// Marquage de l'email original comme traité
protected function markOriginalProcessed(string $message = 'Terminé'): void
```

## États et statuts

### ProcessorStatus (Enum)

```php
ProcessorStatus::Idle        // Initial
ProcessorStatus::Processing  // En cours de traitement
ProcessorStatus::Success     // Succès
ProcessorStatus::Blocked     // Bloqué (validation échouée)
ProcessorStatus::Error       // Erreur technique
```

### EmailStatus (Enum)

```php
EmailStatus::New        // Nouvel email
EmailStatus::Processing // Traitement en cours (active_jobs > 0)
EmailStatus::End        // Terminé (tous les services terminés)
EmailStatus::Error      // Erreur globale
```

**Note** : L'état `EmailStatus::Partial` a été supprimé pour simplifier la logique. Tous les emails avec services terminés (succès, blocages, ou mélange) passent directement à `End`.

### Logique de calcul des statuts

La détermination du statut global de l'email suit une logique simplifiée :

```php
// Dans BaseEmailDraftProcessor::recomputeEmailStatus()
protected function recomputeEmailStatus(): void
{
    if (($this->email->active_jobs ?? 0) > 0) {
        $this->email->status = EmailStatus::Processing->value;
        return;
    }

    if ($this->email->has_error) {
        $this->email->status = EmailStatus::Error->value;
        return;
    }

    // Toujours End si pas d'erreur globale et plus de jobs actifs
    $this->email->status = EmailStatus::End->value;
    $this->email->finished_at = now();
}
```

**Règles simplifiées** :
- ✅ **Processing** : Tant qu'il y a des jobs actifs (`active_jobs > 0`)
- ❌ **Error** : Si une erreur globale est survenue (`has_error = true`)
- 🏁 **End** : Dans tous les autres cas (jobs terminés, avec ou sans blocages)

Cette logique est appliquée :
- À la fin de chaque service via `finishProcessor()`
- Après tous les preflight dans `MsGraphNotificationService`

## Interface utilisateur

### Formulaires Filament

L'interface utilise **ServiceFormBuilder** pour générer automatiquement :

- **Formulaires d'édition** des services (via MailServiceCell Livewire)
- **Infolists d'affichage** des configurations
- **Infolists de résultats** après traitement

```php
// Dans votre service
public static function getForm(): array
{
    return [
        TextInput::make('option')
            ->visible(fn($get) => in_array($get('mode'), ['actif', 'test'])),
        // Vos champs ici
    ];
}
```

### Modes de fonctionnement

- **inactif** : Service désactivé
- **test** : Mode simulation (aucune modification réelle)
- **actif** : Mode production (modifications effectives)

## Intégration avec MS Graph

### Webhooks

Les webhooks MS Graph déclenchent automatiquement le traitement :

1. **Email reçu** → `processEmailNotification()`
2. **Brouillon créé/modifié** → `processDraftNotification()`

### Services automatiques

La classe `MsGraphNotificationService` :
- Charge dynamiquement les services depuis la configuration
- Filtre par mode (actif/test uniquement)
- Lance preflight() puis onQueue() pour chaque service éligible

## Bonnes pratiques

### ✅ À faire

- **Validations dans preflight()** : Toutes les vérifications coûteuses
- **Gestion d'erreurs** : Try/catch avec `finishProcessor(ProcessorStatus::Error)`
- **Mode test** : Toujours implémenter pour les tests sans impact
- **Logging** : Utiliser `setResult()` pour tracer les opérations
- **Options par défaut** : Définir dans `getDefaults()`

### ❌ À éviter

- **Appels lourds dans preflight()** : Mistral, API externes, etc.
- **Modifications directes de l'email** dans preflight() (sauf placeholder)
- **Oubli du `beginProcessor()`** en début de preflight()
- **Oubli du `finishProcessor()`** en fin de perform()

## Exemple complet : Service de validation

```php
class EmailValidationProcessor extends BaseEmailDraftProcessor
{
    public static function getKey(): string { return 'validation'; }
    public static function getLabel(): string { return 'Validation Email'; }
    public static function getDefaultTriggerCode(): string { return 'valide'; }
    
    public function preflight(): PreflightResult
    {
        if ($block = $this->guardAndCaptureCode()) {
            // Standard guard a échoué
            return $block;
        }
        
        // Validation spécifique : email doit contenir un domaine valide
        $content = $this->emailData->bodyOriginal;
        if (!preg_match('/@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $content)) {
            $reason = 'Aucun email valide trouvé dans le contenu';
            $this->setResult('blocked_message', $reason);
            $this->updateProcessorStatus(ProcessorStatus::Blocked, [
                'facts' => ['reason' => $reason]
            ]);
            $this->email->save();
            return PreflightResult::blocked($reason);
        }
        
        $this->beginProcessor();
        $this->startProcessingWithPlaceholder();
        return PreflightResult::ok();
    }
    
    protected function perform(): MsgEmailDraft
    {
        try {
            $content = $this->emailData->bodyOriginal;
            preg_match_all('/@([a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $content, $matches);
            $domains = array_unique($matches[1]);
            
            $results = [];
            foreach ($domains as $domain) {
                $results[] = [
                    'domain' => $domain,
                    'valid' => $this->validateDomain($domain),
                ];
            }
            
            $validCount = count(array_filter($results, fn($r) => $r['valid']));
            
            $this->finishProcessor(ProcessorStatus::Success, [
                'domains_found' => $domains,
                'validation_results' => $results,
                'valid_count' => $validCount,
                'total_count' => count($domains),
            ]);
            
        } catch (Exception $e) {
            $this->finishProcessor(ProcessorStatus::Error, [
                'error_message' => $e->getMessage(),
            ]);
        }
        
        return $this->email;
    }
    
    private function validateDomain(string $domain): bool
    {
        return checkdnsrr($domain, 'MX');
    }
}
```

## Changelog récent

### Novembre 2025 - Simplification des statuts d'emails

**🔧 Modifications apportées :**

- **Suppression de l'état `EmailStatus::Partial`** : Logique simplifiée pour éviter la complexité
- **Nouvelle règle de statuts** : Tous les emails avec services terminés passent à `End`
- **Correction du bug des services bloqués** : Les emails avec tous les services bloqués lors du preflight passent maintenant correctement à `End`

**📋 Comportement avant :**
```php
// Ancienne logique complexe
$hasSuccess = in_array(ProcessorStatus::Success->value, $statuses, true);
$hasBlocked = in_array(ProcessorStatus::Blocked->value, $statuses, true);

$this->email->status = ($hasSuccess && $hasBlocked)
    ? EmailStatus::Partial->value  // État intermédiaire problématique
    : EmailStatus::End->value;
```

**✅ Comportement après :**
```php
// Nouvelle logique simplifiée
$this->email->status = EmailStatus::End->value;  // Toujours End si pas d'erreur
```

**🎯 Impact :**

- **Plus de confusion** entre "partiellement terminé" et "terminé"
- **Gestion cohérente** des emails avec services bloqués/mélangés
- **Interface utilisateur simplifiée** dans les tables Filament
- **Statuts prévisibles** : New → Processing → End (ou Error)

Cette architecture offre une **séparation claire des responsabilités**, une **gestion robuste des erreurs**, et une **interface utilisateur automatique** pour tous vos services de traitement d'emails ! 🚀