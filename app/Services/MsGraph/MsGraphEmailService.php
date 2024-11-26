<?php 

namespace App\Services\MsGraph;

use Exception;
use App\Models\MsgEmailIn;
use App\Models\MsgEmailDraft;

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
