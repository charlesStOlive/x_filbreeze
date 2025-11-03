<?php

namespace App\Services\Processors\Emails;

use Exception;
use App\Models\MsgEmailIn;
use App\Services\Processors\Emails\Support\PreflightResult;
use App\Enums\EmailProcessing\ProcessorStatus;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;

class EmailInClientProcessor extends BaseEmailInProcessor
{
    public static function getKey(): string
    {
        return 'e-in-client';
    }

    public static function getIcon(): string
    {
        return 'heroicon-o-folder-open';
    }

    public static function getLabel(): string
    {
        return 'Ranger email dans dossier client';
    }

    public static function getDescription(): string
    {
        return 'Classe automatiquement les emails entrants dans le dossier du client correspondant';
    }

    public static function getDefaultTriggerCode(): string
    {
        return 'client';
    }

    public static function getDefaults(): array
    {
        return [
            'mode' => 'inactif',
            'target_folder' => 'Clients',
            'accepted_recipients' => "factu@notilac.fr\nsupport@notilac.fr",
            'client_field' => 'slug',
            'regex_code' => 'client',
        ];
    }

    // --- UI pour ton builder ---
    public static function getForm(): array
    {
        return [
            TextInput::make('target_folder')
                ->label('Dossier de destination')
                ->helperText('Nom du dossier où classer les emails')
                ->visible(fn($get) => in_array($get('mode'), ['actif', 'test'])),

            Textarea::make('accepted_recipients')
                ->label('Destinataires acceptés')
                ->helperText('Liste des emails acceptés (un par ligne)')
                ->rows(3)
                ->visible(fn($get) => in_array($get('mode'), ['actif', 'test'])),

            TextInput::make('client_field')
                ->label('Champ client pour dossier')
                ->helperText('Champ utilisé pour identifier le client (ex: slug)')
                ->visible(fn($get) => in_array($get('mode'), ['actif', 'test'])),

            TextInput::make('regex_code')
                ->label('Code de déclenchement')
                ->helperText('Code qui doit être présent dans l\'email pour déclencher le traitement')
                ->visible(fn($get) => in_array($get('mode'), ['actif', 'test'])),
        ];
    }

    public static function getInfoList(): array
    {
        return [
            TextEntry::make('target_folder')
                ->label('Dossier cible')
                ->icon('heroicon-o-folder'),

            TextEntry::make('accepted_recipients')
                ->label('Destinataires acceptés')
                ->formatStateUsing(fn($state) => str_replace("\n", ', ', $state))
                ->limit(100)
                ->icon('heroicon-o-envelope'),

            TextEntry::make('client_field')
                ->label('Champ client')
                ->badge()
                ->color('info')
                ->icon('heroicon-o-identification'),

            TextEntry::make('regex_code')
                ->label('Code de déclenchement')
                ->formatStateUsing(fn($state) => "## {$state} ##")
                ->badge()
                ->color('gray')
                ->copyable()
                ->copyMessage('Code de déclenchement copié!')
                ->icon('heroicon-o-hashtag'),
        ];
    }

    public static function getResultsInfoList(): array
    {
        return [
            TextEntry::make('client_identified')
                ->label('Client identifié')
                ->badge()
                ->color('success')
                ->icon('heroicon-o-user')
                ->visible(fn($state) => !empty($state)),

            TextEntry::make('newfolder')
                ->label('Nouveau dossier créé')
                ->badge()
                ->color('info')
                ->icon('heroicon-o-folder-plus')
                ->visible(fn($state) => !empty($state)),

            TextEntry::make('reason')
                ->label('Raison')
                ->color('warning')
                ->visible(fn($state) => !empty($state)),

            TextEntry::make('processing_mode')
                ->label('Mode de traitement')
                ->badge()
                ->color(fn($state) => match ($state) {
                    'moved' => 'success',
                    'test'  => 'info',
                    default => 'gray'
                })
                ->visible(fn($state) => !empty($state)),
        ];
    }

    // --- Phase 1 ---
    public function preflight(): PreflightResult
    {
        if ($block = $this->guardAndCaptureCode()) {
            $this->updateProcessorStatus(ProcessorStatus::Blocked, $block->reason);
            $this->email->save();
            return $block;
        }

        try {
            $this->beginProcessor();
            $this->startProcessingWithPlaceholder();
        } catch (Exception $ex) {
            $this->updateProcessorStatus(ProcessorStatus::Error, $ex->getMessage());
            $this->email->has_error = true;
            $this->email->save();
            return PreflightResult::blocked('Préflight en échec');
        }

        return PreflightResult::ok();
    }

    // --- Phase 2 ---
    protected function perform(): MsgEmailIn
    {
        try {
            $mode = $this->getResult('mode', 'inactif');
            $targetFolder = $this->getServiceOption('target_folder', static::getDefaults()['target_folder']);
            $acceptedRecipients = $this->getServiceOption('accepted_recipients', static::getDefaults()['accepted_recipients']);
            $clientField = $this->getServiceOption('client_field', static::getDefaults()['client_field']);

            // Vérification des destinataires acceptés
            $acceptedList = array_map('trim', explode("\n", $acceptedRecipients));
            $emailRecipients = array_merge($this->emailData->toEmails, $this->emailData->ccEmails, $this->emailData->bccEmails);
            
            $hasValidRecipient = false;
            foreach ($emailRecipients as $recipient) {
                if (in_array($recipient, $acceptedList)) {
                    $hasValidRecipient = true;
                    break;
                }
            }

            if (!$hasValidRecipient) {
                $this->finishProcessor(ProcessorStatus::Blocked, [
                    'reason' => 'Aucun destinataire valide trouvé',
                    'processing_mode' => $mode,
                ], "Email rejeté - destinataires non autorisés");
                return $this->email;
            }

            if ($mode === 'test') {
                $this->finishProcessor(ProcessorStatus::Success, [
                    'client_identified' => 'Test Client',
                    'processing_mode' => 'test',
                    'reason' => 'Test réussi - email aurait été classé',
                ], "Test réussi pour le classement client");
                return $this->email;
            }

            // TODO: Logique de classification réelle
            // - Analyser l'expéditeur/contenu pour identifier le client
            // - Créer/utiliser le dossier client approprié  
            // - Déplacer l'email dans le bon dossier

            $clientIdentified = 'Client Test'; // À remplacer par vraie logique
            $newFolder = $targetFolder . '/' . $clientIdentified;

            $this->finishProcessor(ProcessorStatus::Success, [
                'client_identified' => $clientIdentified,
                'newfolder' => $newFolder,
                'processing_mode' => 'moved',
            ], "Email classé dans le dossier client : {$clientIdentified}");

        } catch (Exception $e) {
            $this->finishProcessor(ProcessorStatus::Error, [
                'reason' => $e->getMessage(),
            ], $e->getMessage());
        }

        return $this->email;
    }
}