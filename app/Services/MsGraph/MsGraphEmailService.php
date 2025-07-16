<?php

namespace App\Services\MsGraph;

use Exception;
use App\Models\MsgEmailIn;
use App\Models\MsgEmailDraft;
use App\Dto\MsGraph\EmailMessageDTO;
use App\Services\MsGraph\MsGraphAuthService;

class MsGraphEmailService
{
    protected MsGraphAuthService $authService;

    public function __construct(MsGraphAuthService $authService)
    {
        $this->authService = $authService;
    }

    public function fetchEmailData($user, $email)
    {
        $path = $this->getApiPathToMail($user, $email);
        return $this->authService->guzzle('get', $path);
    }

    public function createDraft($user, array $emailData): array
    {
        // Path pour créer un nouveau brouillon dans le dossier "Drafts" d'un utilisateur spécifique
        $path = "users/{$user->ms_id}/messages";
        // Envoyer la requête pour créer le brouillon
        return $this->authService->guzzle('post', $path, $emailData);
    }

    public function createNewDraftAndUploadAttachments($user, array $emailData, array $attachments = []): array
    {
        $path = "users/{$user->ms_id}/messages";

        // Ne pas inclure attachments dans le body du draft
        unset($emailData['attachments']);

        $draft = $this->authService->guzzle('post', $path, $emailData);

        $emailId = $draft['id'] ?? null;

        if ($emailId && ! empty($attachments)) {
            $this->uploadAttachments($user, $emailId, $attachments);
        }

        return $draft;
    }

    public function uploadAttachments($user, string $emailId, array $attachments): void
    {
        foreach ($attachments as $attachment) {
            if (empty($attachment['path']) || !file_exists($attachment['path'])) {
                continue;
            }

            $contentBytes = base64_encode(file_get_contents($attachment['path']));

            $payload = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $attachment['name'],
                'contentType' => $attachment['mime'],
                'contentBytes' => $contentBytes,
            ];

            $this->authService->guzzle('post', "users/{$user->ms_id}/messages/{$emailId}/attachments", $payload);
        }
    }

    public function updateEmail($user, $email, array $updateData): bool
    {
        $path = $this->getApiPathToMail($user, $email);

        if (empty($updateData)) {
            throw new Exception('No data to update email.');
        }
        $this->authService->guzzle('patch', $path, $updateData);
        return true;
    }

    public function forwardEmail($user, $email, string $forwardedTo, string $comment)
    {
        $path = $this->getApiPathToMail($user, $email);

        $forwardData = [
            'message' => [
                'toRecipients' => [
                    ['emailAddress' => ['address' => $forwardedTo]],
                ],
            ],
            'comment' => $comment,
            'saveToSentItems' => true,
        ];

        return $this->authService->guzzle('post', "{$path}/forward", $forwardData);
    }

    public function setEmailIsRead($user, $email, bool $isRead = true)
    {
        $path = $this->getApiPathToMail($user, $email);
        $updateData = ['isRead' => $isRead];
        return $this->authService->guzzle('patch', $path, $updateData);
    }

    public function moveEmailToFolder($user, $email, string $folderName)
    {
        $basePath = "users/{$user->ms_id}/mailFolders";
        $existingFolder = $this->authService->guzzle('get', "{$basePath}?\$filter=displayName eq '{$folderName}'");

        $folderId = count($existingFolder['value']) > 0
            ? $existingFolder['value'][0]['id']
            : $this->createNewFolder($basePath, $folderName)['id'];

        $path = $this->getApiPathToMail($user, $email);
        $moveData = ['destinationId' => $folderId];

        return $this->authService->guzzle('post', "{$path}/move", $moveData);
    }

    protected function getApiPathToMail($user, $email): string
    {
        $basePath = "users/{$user->ms_id}";
        return $email instanceof MsgEmailDraft
            ? "{$basePath}/mailFolders('Drafts')/messages/{$email->email_id}"
            : "{$basePath}/messages/{$email->email_id}";
    }

    protected function createNewFolder(string $basePath, string $folderName): array
    {
        $folderData = ['displayName' => $folderName];
        return $this->authService->guzzle('post', $basePath, $folderData);
    }
}
