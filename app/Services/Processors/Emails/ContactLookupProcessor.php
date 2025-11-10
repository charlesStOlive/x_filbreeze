<?php

namespace App\Services\Processors\Emails;


use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use CharlesStOlive\MsGraphFilament\Services\Email\Dto\EmailMessageDTO;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use CharlesStOlive\MsGraphFilament\Services\Email\Contracts\EmailClient;
use CharlesStOlive\MsGraphFilament\Models\MsgUserIn;
use CharlesStOlive\MsGraphFilament\Models\MsgEmailIn;
use CharlesStOlive\MsGraphFilament\Enums\ProcessorStatus;
use CharlesStOlive\MsGraphFilament\Support\PreflightResult;
use CharlesStOlive\MsGraphFilament\Processors\BaseEmailInProcessor;

/**
 * Processeur EmailIn qui vérifie si l'expéditeur est dans la base contacts
 * et range l'email dans le dossier client correspondant
 * 
 * - Pas de regex requis
 * - Pas de queue (preflight-only)
 * - Analyse directe des contacts
 */
class ContactLookupProcessor extends BaseEmailInProcessor
{

    public function __construct(
        MsgUserIn $user,
        EmailMessageDTO $emailData,
        MsgEmailIn $email,
        ?EmailClient $emailClient = null
    ) {
        parent::__construct($user, $emailData, $email, $emailClient);
        $this->user = $user;
        $this->email = $email;
    }

    // --- Configuration du processeur ---
    public static function supportsQueue(): bool
    {
        return false; // Preflight-only, pas de queue
    }

    public static function requiresRegex(): bool
    {
        return false; // Pas de regex requis
    }

    // --- Métadonnées ---
    public static function getKey(): string
    {
        return 'contact-lookup';
    }

    public static function getIcon(): string
    {
        return 'heroicon-o-users';
    }

    public static function getLabel(): string
    {
        return 'Recherche contact et classement';
    }

    public static function getDescription(): string
    {
        return 'Vérifie si l\'expéditeur est dans la base contacts et range l\'email dans le dossier client';
    }

    public static function getDefaultTriggerCode(): string
    {
        return ''; // Pas de code par défaut car pas de regex
    }

    public static function getDefaults(): array
    {
        return [
            'mode' => 'inactif',
            'target_folder_prefix' => 'Clients/',
            'client_slug_field' => 'slug', // champ pour le nom du dossier
            'auto_create_folder' => true,
        ];
    }

    // --- Formulaire Filament ---
    public static function getForm(): array
    {
        return [
            TextInput::make('target_folder_prefix')
                ->label('Préfixe dossier cible')
                ->placeholder('Clients/')
                ->helperText('Préfixe pour les dossiers clients (ex: "Clients/")'),

            Select::make('client_slug_field')
                ->label('Champ slug client')
                ->options([
                    'slug' => 'Slug',
                    'name' => 'Nom',
                    'code' => 'Code',
                ])
                ->default('slug'),

            Toggle::make('auto_create_folder')
                ->label('Créer dossier automatiquement')
                ->default(true)
                ->helperText('Créer le dossier client s\'il n\'existe pas'),
        ];
    }

    // --- InfoList pour affichage configuration ---
    public static function getInfoList(): array
    {
        return [
            TextEntry::make('target_folder_prefix')
                ->label('Préfixe dossier'),

            TextEntry::make('client_slug_field')
                ->label('Champ slug client'),

            IconEntry::make('auto_create_folder')
                ->label('Création auto')
                ->boolean(),
        ];
    }

    // --- InfoList pour résultats ---
    public static function getResultsInfoList(): array
    {
        return [
            TextEntry::make('sender_email')
                ->label('Expéditeur'),

            TextEntry::make('contact_found')
                ->label('Contact trouvé')
                ->badge()
                ->color(fn(?bool $state): string => $state ? 'success' : 'danger')
                ->formatStateUsing(fn(?bool $state): string => $state ? 'Oui' : 'Non'),

            TextEntry::make('client_name')
                ->label('Client')
                ->visible(fn($record) => !empty($record)),

            TextEntry::make('target_folder')
                ->label('Dossier cible'),

            TextEntry::make('moved_to_folder')
                ->label('Déplacé vers')
                ->badge()
                ->color('success')
                ->visible(fn(?string $state): bool => !empty($state)),
        ];
    }

    // --- Méthodes d'accès aux données spécifiques ---
    protected function getUser()
    {
        return $this->user;
    }

    protected function getEmail()
    {
        return $this->email;
    }

    // --- Logique principale ---
    public function preflight(): PreflightResult
    {
        // Guards communs (mode inactif, etc.)
        $blockResult = $this->guardForIncomingEmail();
        if ($blockResult) {
            return $blockResult;
        }

        try {
            // Analyser l'expéditeur de l'email
            $senderEmail = $this->extractSenderEmail();
            if (!$senderEmail) {
                return PreflightResult::blocked('Impossible d\'extraire l\'email de l\'expéditeur');
            }

            $this->setResult('sender_email', $senderEmail);

            // Chercher le contact dans la base
            $contact = $this->findContact($senderEmail);
            $this->setResult('contact_found', $contact ? true : false);

            if (!$contact) {
                // Pas de contact trouvé, utiliser le dossier de repli (en dur)
                $fallbackFolder = 'Inconnus';
                $this->setResult('target_folder', $fallbackFolder);
                $this->setResult('client_name', null);

                $mode = $this->getServiceOption('mode', 'inactif');
                if ($mode !== 'test') {
                    $this->moveEmailToFolder($fallbackFolder);
                }

                return PreflightResult::success('Email classé dans le dossier de repli');
            }

            // Contact trouvé, déterminer le dossier client
            $clientSlug = $this->getClientSlug($contact);
            $this->setResult('client_name', $contact->name ?? $contact->raison_sociale ?? 'Client');

            $folderPrefix = $this->getServiceOption('target_folder_prefix', 'Clients/');
            $targetFolder = $folderPrefix . $clientSlug;
            $this->setResult('target_folder', $targetFolder);

            $mode = $this->getServiceOption('mode', 'inactif');
            if ($mode !== 'test') {
                // Créer le dossier si nécessaire
                if ($this->getServiceOption('auto_create_folder', true)) {
                    $created = $this->ensureFolderExists($targetFolder);
                    if ($created) {
                        $this->setResult('folder_created', 'Oui');
                    }
                }

                // Déplacer l'email
                $this->moveEmailToFolder($targetFolder);
            }

            return PreflightResult::success("Email classé dans le dossier client : {$targetFolder}");
        } catch (\Exception $e) {
            return PreflightResult::error('Erreur lors de l\'analyse : ' . $e->getMessage());
        }
    }

    // --- Méthodes utilitaires ---
    protected function extractSenderEmail(): ?string
    {
        // Extraire l'email de l'expéditeur depuis emailData
        return $this->emailData->fromEmail ?? null;
    }

    protected function findContact(string $email): ?object
    {
        // Chercher dans la table contacts avec le champ email (en dur)

        // Exemple avec un modèle Contact fictif - adapter selon votre DB
        if (class_exists(\App\Models\Contact::class)) {
            return \App\Models\Contact::where('email', $email)->first();
        }

        // Si pas de modèle Contact, essayer avec d'autres tables
        // Par exemple chercher dans une table clients
        if (class_exists(\App\Models\Client::class)) {
            return \App\Models\Client::where('email', $email)->first();
        }

        return null;
    }

    protected function getClientSlug($contact): string
    {
        $slugField = $this->getServiceOption('client_slug_field', 'slug');

        return $contact->{$slugField} ??
            $contact->slug ??
            $contact->code ??
            str()->slug($contact->name ?? $contact->raison_sociale ?? 'client');
    }

    protected function ensureFolderExists(string $folderPath): bool
    {
        // Logique pour créer le dossier dans Outlook/Exchange si nécessaire
        // Pour l'instant, on simule juste
        return false; // Pas créé car déjà existant
    }

    protected function moveEmailToFolder(string $folderPath): void
    {
        // Logique pour déplacer l'email vers le dossier spécifié
        // Utiliser l'EmailClient pour faire l'opération sur Exchange/Outlook
        // $this->emailClient->moveEmail($this->user, $this->email, $folderPath);

        // Pour l'instant, on stocke juste l'information
        $this->setResult('moved_to_folder', $folderPath);
    }

    /**
     * Cette méthode n'est pas utilisée car ce processeur ne fait que du preflight.
     */
    protected function perform()
    {
        // Rien à faire, toute la logique est dans preflight()
    }
}
