<?php

namespace App\Infrastructure\MsGraph;

use App\Services\Email\Contracts\EmailClient;
use App\Models\MsgUserDraft;
use App\Models\MsgEmailDraft;

final class GraphEmailClient implements EmailClient
{
    public function __construct(
        private GraphEmailService $service,
        private \App\Infrastructure\MsGraph\Mappers\GraphMessageMapper $mapper
    ) {}

    public function fetchDraft(MsgUserDraft $user, string $messageId): array
    {
        return $this->service->fetchEmailData($user, new MsgEmailDraft(['email_id' => $messageId]));
    }

    public function fetchEmail($user, string $messageId): array
    {
        return $this->service->fetchEmailData($user, new \App\Models\MsgEmailIn(['email_id' => $messageId]));
    }

    public function updateDraftBody(MsgUserDraft $user, MsgEmailDraft $email, string $contentType, string $content): void
    {
        $this->service->updateEmail($user, $email, [
            'body' => [
                'contentType' => $contentType,
                'content' => $content
            ]
        ]);
    }

    public function createDraft(MsgUserDraft $user, \App\Services\Email\Dto\EmailMessageDTO $emailDto): array
    {
        $graphPayload = $this->mapper->toGraphFormat($emailDto);
        return $this->service->createDraft($user, $graphPayload);
    }

    public function addAttachments(MsgUserDraft $user, MsgEmailDraft $email, array $attachments): void
    {
        // Implémentation selon la méthode du service existant
        // À adapter selon l'API de MsGraphEmailService
    }

    public function updateEmail(MsgUserDraft $user, MsgEmailDraft $email, array $data): void
    {
        $this->service->updateEmail($user, $email, $data);
    }
}
