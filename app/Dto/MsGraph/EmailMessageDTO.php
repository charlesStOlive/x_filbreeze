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
        public string $allRecipentsStringMails = '',
        public string $toRecipentsStringMails = '',
        public array $allRecipentsNdd = [],
        public string $fromName,
        #[Rule('email')]
        public string $fromEmail,
        public string $fromNdd = '', // Nouveau champ pour le domaine
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
        $toEmails = self::extractRecipientEmails($data['toRecipients'] ?? []);
        $ccEmails = self::extractRecipientEmails($data['ccRecipients'] ?? []);
        $bccEmails = self::extractRecipientEmails($data['bccRecipients'] ?? []);

        $allEmails = array_merge($toEmails, $ccEmails, $bccEmails);

        return new self(
            id: $data['id'] ?? 'xxxxxx',
            createdDateTime: new Carbon($data['createdDateTime'] ?? now()),
            receivedDateTime: new Carbon($data['receivedDateTime'] ?? now()),
            sentDateTime: new Carbon($data['sentDateTime'] ?? now()),
            hasAttachments: $data['hasAttachments'] ?? false,
            internetMessageId: $data['internetMessageId'] ?? 'xxxxxxx',
            subject: $data['subject'],
            importance: $data['importance'] ?? 'normal',
            contentType: $data['body']['contentType'] ?? 'text/plain',
            bodyOriginal: $data['body']['content'] ?? '',
            bodyBrut: self::parseTextFromHtml($data['body']['content'] ?? ''),
            toRecipientsNames: self::extractRecipientNames($data['toRecipients'] ?? []),
            toRecipientsMails: $toEmails,
            ccRecipientsNames: self::extractRecipientNames($data['ccRecipients'] ?? []),
            ccRecipientsMails: $ccEmails,
            bccRecipientsNames: self::extractRecipientNames($data['bccRecipients'] ?? []),
            bccRecipientsMails: $bccEmails,
            toRecipentsStringMails: implode(',', $toEmails),
            allRecipentsStringMails: implode(',', $allEmails),
            allRecipentsNdd: self::extractDomains($allEmails),
            fromName: $data['from']['emailAddress']['name'] ?? '',
            fromEmail: $data['from']['emailAddress']['address'] ?? '',
            fromNdd: self::extractDomainFromEmail($data['from']['emailAddress']['address'] ?? ''),
            webLink: $data['webLink'] ?? '',
            inferenceClassification: $data['inferenceClassification'] ?? 'other',
            hasPJs: !empty($data['hasAttachments']),
            pjs: self::extractAttachments($data['attachments'] ?? [])
        );
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
     * Extract domains from emails.
     */
    private static function extractDomains(array $emails): array
    {
        return array_unique(array_map(fn($email) => self::extractDomainFromEmail($email), $emails));
    }

    /**
     * Extract the domain from an email address.
     */
    public static function extractDomainFromEmail(string $email): string
    {
        $parts = explode('@', $email);
        return $parts[1] ?? '';
    }

    /**
     * Convert HTML to plain text using Soundasleep.
     */
    private static function parseTextFromHtml(string $html): string
    {
        return \Soundasleep\Html2Text::convert($html, ['ignore_errors' => true, 'drop_links' => true]);
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
}
