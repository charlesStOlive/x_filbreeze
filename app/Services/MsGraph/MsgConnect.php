<?php

namespace App\Services\MsGraph;

use App\Models\MsgUserDraft;
use App\Models\MsgUserIn;
use App\Models\MsgEmailDraft;
use App\Models\MsgEmailIn;
use App\Dto\MsGraph\EmailMessageDTO;
use App\Services\Processors\Emails\DraftEmailProcessor;
use App\Services\Processors\Emails\EmailInClientProcessor;
use Illuminate\Support\Facades\Log;

class MsgConnect
{
    /**
     * Lance les services de test pour un email simulé.
     * 
     * @param MsgUserDraft|MsgUserIn $msgUser
     * @param array $emailData
     */
    public function launchTestServices($msgUser, array $emailData): void
    {
        try {
            // Déterminer le type d'utilisateur et les services appropriés
            if ($msgUser instanceof MsgUserDraft) {
                $this->processDraftEmailServices($msgUser, $emailData);
            } elseif ($msgUser instanceof MsgUserIn) {
                $this->processIncomingEmailServices($msgUser, $emailData);
            } else {
                Log::warning('Type d\'utilisateur non supporté pour la simulation', [
                    'user_class' => get_class($msgUser)
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Erreur lors du lancement des services de test', [
                'error' => $e->getMessage(),
                'user_id' => $msgUser->id ?? null,
                'email_data' => $emailData
            ]);
        }
    }

    /**
     * Traite les services pour les emails brouillons.
     */
    private function processDraftEmailServices(MsgUserDraft $msgUser, array $emailData): void
    {
        // Créer un DTO à partir des données d'email
        $emailDto = $this->createEmailDTO($emailData);

        // Créer un enregistrement d'email de test
        $email = new MsgEmailDraft([
            'msg_user_draft_id' => $msgUser->id,
            'ms_id' => 'test-' . uniqid(),
            'subject' => $emailData['subject'] ?? 'Test Email',
            'from_email' => $emailData['from']['emailAddress']['address'] ?? '',
            'to_emails' => json_encode($emailData['toRecipients'] ?? []),
            'body_preview' => substr(strip_tags($emailData['body']['content'] ?? ''), 0, 100),
            'status' => 'pending',
            'is_test' => true,
        ]);
        $email->save();

        // Lancer les processeurs de services actifs
        $this->launchDraftProcessors($msgUser, $emailDto, $email);
    }

    /**
     * Traite les services pour les emails entrants.
     */
    private function processIncomingEmailServices(MsgUserIn $msgUser, array $emailData): void
    {
        // Créer un DTO à partir des données d'email
        $emailDto = $this->createEmailDTO($emailData);

        // Créer un enregistrement d'email de test
        $email = new MsgEmailIn([
            'msg_user_in_id' => $msgUser->id,
            'ms_id' => 'test-' . uniqid(),
            'subject' => $emailData['subject'] ?? 'Test Email',
            'from_email' => $emailData['from']['emailAddress']['address'] ?? '',
            'to_emails' => json_encode($emailData['toRecipients'] ?? []),
            'body_preview' => substr(strip_tags($emailData['body']['content'] ?? ''), 0, 100),
            'status' => 'pending',
            'is_test' => true,
        ]);
        $email->save();

        // Lancer les processeurs de services actifs
        $this->launchIncomingProcessors($msgUser, $emailDto, $email);
    }

    /**
     * Lance les processeurs pour les emails brouillons.
     */
    private function launchDraftProcessors(MsgUserDraft $msgUser, EmailMessageDTO $emailDto, MsgEmailDraft $email): void
    {
        // Récupérer tous les services configurés depuis la config
        $services = config('msgraph.email-draft', []);

        foreach ($services as $serviceClass) {
            if (!class_exists($serviceClass)) {
                continue;
            }

            $serviceKey = $serviceClass::getKey();
            $mode = $msgUser->getServiceOption($serviceKey, 'mode', 'inactif');

            // Lancer seulement les services actifs ou en test
            if (in_array($mode, ['actif', 'test'])) {
                Log::info("Lancement du service de test : {$serviceKey}", [
                    'mode' => $mode,
                    'user_id' => $msgUser->id
                ]);

                // Utiliser la méthode onQueue du service pour le lancer
                $serviceClass::onQueue($msgUser, $emailDto, $email);
            }
        }
    }

    /**
     * Lance les processeurs pour les emails entrants.
     */
    private function launchIncomingProcessors(MsgUserIn $msgUser, EmailMessageDTO $emailDto, MsgEmailIn $email): void
    {
        // Récupérer tous les services configurés depuis la config
        $services = config('msgraph.email-in', []);

        foreach ($services as $serviceClass) {
            if (!class_exists($serviceClass)) {
                continue;
            }

            $serviceKey = $serviceClass::getKey();
            $mode = $msgUser->getServiceOption($serviceKey, 'mode', 'inactif');

            // Lancer seulement les services actifs ou en test
            if (in_array($mode, ['actif', 'test'])) {
                Log::info("Lancement du service de test : {$serviceKey}", [
                    'mode' => $mode,
                    'user_id' => $msgUser->id
                ]);

                // Utiliser la méthode onQueue du service pour le lancer
                $serviceClass::onQueue($msgUser, $emailDto, $email);
            }
        }
    }

    /**
     * Crée un EmailMessageDTO à partir des données d'email.
     */
    private function createEmailDTO(array $emailData): EmailMessageDTO
    {
        // Transformer les données au format attendu par EmailMessageDTO::fromArray
        $formattedData = [
            'id' => 'test-' . uniqid(),
            'createdDateTime' => now()->toISOString(),
            'lastModifiedDateTime' => now()->toISOString(),
            'receivedDateTime' => now()->toISOString(),
            'sentDateTime' => now()->toISOString(),
            'hasAttachments' => false,
            'internetMessageId' => 'test-message-id',
            'subject' => $emailData['subject'] ?? 'Test Email',
            'importance' => 'normal',
            'body' => [
                'contentType' => $emailData['body']['contentType'] ?? 'html',
                'content' => $emailData['body']['content'] ?? '',
            ],
            'toRecipients' => $emailData['toRecipients'] ?? [],
            'ccRecipients' => $emailData['ccRecipients'] ?? [],
            'bccRecipients' => $emailData['bccRecipients'] ?? [],
            'from' => $emailData['from'] ?? [
                'emailAddress' => [
                    'name' => 'Test User',
                    'address' => 'test@example.com'
                ]
            ],
            'webLink' => '',
            'inferenceClassification' => 'other',
            'attachments' => []
        ];

        // Utiliser la méthode fromArray pour créer le DTO
        return EmailMessageDTO::fromArray($formattedData);
    }

    /**
     * Traite les notifications d'email (utilisé par les jobs).
     */
    public function processEmailNotification(array $notificationData): void
    {
        // Cette méthode serait implémentée pour traiter les vraies notifications
        // Pour l'instant, on la laisse vide car elle n'est pas utilisée dans la simulation
        Log::info('Traitement de notification d\'email', $notificationData);
    }
}
