<?php

namespace App\Services\Email\Contracts;

use App\Services\Email\Dto\EmailMessageDTO;
use App\Models\MsgUserDraft;
use App\Models\MsgEmailDraft;

interface EmailClient
{
    /**
     * Fetch draft email data from the email service
     */
    public function fetchDraft(MsgUserDraft $user, string $messageId): array;

    /**
     * Fetch email data for incoming emails
     */
    public function fetchEmail($user, string $messageId): array;

    /**
     * Update the body of an existing draft email
     */
    public function updateDraftBody(MsgUserDraft $user, MsgEmailDraft $email, string $contentType, string $content): void;

    /**
     * Create a new draft email
     */
    public function createDraft(MsgUserDraft $user, \App\Services\Email\Dto\EmailMessageDTO $emailDto): array;

    /**
     * Add attachments to an email
     */
    public function addAttachments(MsgUserDraft $user, MsgEmailDraft $email, array $attachments): void;

    /**
     * Update an existing email with given data
     */
    public function updateEmail(MsgUserDraft $user, MsgEmailDraft $email, array $data): void;
}
