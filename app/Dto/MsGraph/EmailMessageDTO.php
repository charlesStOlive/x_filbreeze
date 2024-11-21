<?php

namespace App\Dto\MsGraph;

use Carbon\Carbon;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Rule;

class EmailMessageDTO extends Data
{
    public function __construct(
        public string $id,
        public Carbon $createdDateTime,
        public Carbon $receivedDateTime,
        public Carbon $sentDateTime,
        #[Rule('boolean')]
        public bool $hasAttachments,
        public string $internetMessageId,
        public string $subject,
        public string $importance,
        public string $contentType,
        public string $bodyOriginal,
        public string $bodyBrut,
        public array $toRecipientsNames = [],
        public array $toRecipientsMails = [],
        public array $ccRecipientsNames = [],
        public array $ccRecipientsMails = [],
        public array $bccRecipientsNames = [],
        public array $bccRecipientsMails = [],
        public string $fromName,
        #[Rule('email')]
        public string $fromEmail,
        public string $webLink,
        public string $inferenceClassification,
        #[Rule('boolean')]
        public bool $hasPJs,
        public array $pjs = []
    ) {}

    /**
     * Hydrate the DTO from raw data.
     */
    public static function fromArray(array $data): self
    {
        $bodyHtml = $data['body']['content'] ?? '';
        $contentType = $data['body']['contentType'] ?? 'text/plain';

        return new self(
            id: $data['id'],
            createdDateTime: new Carbon($data['createdDateTime']),
            receivedDateTime: new Carbon($data['receivedDateTime']),
            sentDateTime: new Carbon($data['sentDateTime']),
            hasAttachments: $data['hasAttachments'] ?? false,
            internetMessageId: $data['internetMessageId'],
            subject: $data['subject'],
            importance: $data['importance'] ?? 'normal',
            contentType: $contentType,
            bodyOriginal: $bodyHtml,
            bodyBrut: self::parseTextFromHtml($bodyHtml),
            toRecipientsNames: self::extractRecipientNames($data['toRecipients'] ?? []),
            toRecipientsMails: self::extractRecipientEmails($data['toRecipients'] ?? []),
            ccRecipientsNames: self::extractRecipientNames($data['ccRecipients'] ?? []),
            ccRecipientsMails: self::extractRecipientEmails($data['ccRecipients'] ?? []),
            bccRecipientsNames: self::extractRecipientNames($data['bccRecipients'] ?? []),
            bccRecipientsMails: self::extractRecipientEmails($data['bccRecipients'] ?? []),
            fromName: $data['from']['emailAddress']['name'] ?? '',
            fromEmail: $data['from']['emailAddress']['address'] ?? '',
            webLink: $data['webLink'] ?? '',
            inferenceClassification: $data['inferenceClassification'] ?? 'other',
            hasPJs: !empty($data['hasAttachments']),
            pjs: self::extractAttachments($data['attachments'] ?? [])
        );
    }

    /**
     * Convert HTML to plain text using Soundasleep.
     */
    private static function parseTextFromHtml(string $html): string
    {
        return \Soundasleep\Html2Text::convert($html, ['ignore_errors' => true, 'drop_links' => true]);
    }

    /**
     * Extract recipient names.
     */
    private static function extractRecipientNames(array $recipients): array
    {
        return array_map(fn($recipient) => $recipient['emailAddress']['name'] ?? '', $recipients);
    }

    /**
     * Extract recipient emails.
     */
    private static function extractRecipientEmails(array $recipients): array
    {
        return array_map(fn($recipient) => $recipient['emailAddress']['address'] ?? '', $recipients);
    }

    /**
     * Extract attachment details.
     */
    private static function extractAttachments(array $attachments): array
    {
        return array_map(function ($attachment) {
            return [
                'name' => $attachment['name'] ?? '',
                'contentType' => $attachment['contentType'] ?? '',
                'size' => $attachment['size'] ?? 0, // size in bytes
                'id' => $attachment['id'] ?? '',
            ];
        }, $attachments);
    }

    /**
     * Convert to array excluding specific fields (e.g., bodyBrut).
     */
    public function toCleanedArray(): array
    {
        return $this->except('bodyOriginal')->toArray();
    }

    /**
     * Convert to JSON excluding specific fields (e.g., bodyBrut).
     */
    public function toCleanedJson(): string
    {
        return json_encode($this->toFilteredArray(), JSON_PRETTY_PRINT);
    }
}
