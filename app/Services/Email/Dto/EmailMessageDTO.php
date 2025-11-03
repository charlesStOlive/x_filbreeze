<?php

namespace App\Services\Email\Dto;

use Carbon\CarbonImmutable;

final class EmailMessageDTO
{
    public function __construct(
        public string $id,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $receivedAt,
        public CarbonImmutable $modifiedAt,
        public CarbonImmutable $sentAt,
        public bool $hasAttachments,
        public string $internetMessageId,
        public string $subject,
        public string $importance,   // ou enum
        public string $contentType,  // 'HTML'|'Text' (ou enum)
        public string $bodyHtml,
        public string $bodyText,
        /** @var string[] */
        public array $toEmails,
        /** @var string[] */
        public array $ccEmails,
        /** @var string[] */
        public array $bccEmails,
        public string $fromName,
        public string $fromEmail,
        public string $webLink,
        public string $inferenceClassification,
        /** @var array<array{name:string,contentType:string,size:int,id:string}> */
        public array $attachments,
        public string $regexCode = '',
        /** @var array<string, mixed> */
        public array $regexOptions = [],
    ) {}

    /**
     * Get basic email data for database storage
     */
    public function basicEmailData(): array
    {
        return [
            'from' => $this->fromEmail,
            'tos' => implode(',', array_merge($this->toEmails, $this->ccEmails, $this->bccEmails)),
            'subject' => $this->subject,
            'email_id' => $this->id,
        ];
    }

    /**
     * Create a copy of this DTO with modified properties (immutable pattern)
     */
    public function with(array $changes): self
    {
        return new self(
            id: $changes['id'] ?? $this->id,
            createdAt: $changes['createdAt'] ?? $this->createdAt,
            receivedAt: $changes['receivedAt'] ?? $this->receivedAt,
            modifiedAt: $changes['modifiedAt'] ?? $this->modifiedAt,
            sentAt: $changes['sentAt'] ?? $this->sentAt,
            hasAttachments: $changes['hasAttachments'] ?? $this->hasAttachments,
            internetMessageId: $changes['internetMessageId'] ?? $this->internetMessageId,
            subject: $changes['subject'] ?? $this->subject,
            importance: $changes['importance'] ?? $this->importance,
            contentType: $changes['contentType'] ?? $this->contentType,
            bodyHtml: $changes['bodyHtml'] ?? $this->bodyHtml,
            bodyText: $changes['bodyText'] ?? $this->bodyText,
            toEmails: $changes['toEmails'] ?? $this->toEmails,
            ccEmails: $changes['ccEmails'] ?? $this->ccEmails,
            bccEmails: $changes['bccEmails'] ?? $this->bccEmails,
            fromName: $changes['fromName'] ?? $this->fromName,
            fromEmail: $changes['fromEmail'] ?? $this->fromEmail,
            webLink: $changes['webLink'] ?? $this->webLink,
            inferenceClassification: $changes['inferenceClassification'] ?? $this->inferenceClassification,
            attachments: $changes['attachments'] ?? $this->attachments,
            regexCode: $changes['regexCode'] ?? $this->regexCode,
            regexOptions: $changes['regexOptions'] ?? $this->regexOptions,
        );
    }
}
