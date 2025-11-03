<?php

namespace App\Infrastructure\MsGraph\Mappers;

use App\Services\Email\Dto\EmailMessageDTO;
use Carbon\CarbonImmutable;
use App\Support\Email\HtmlToTextConverter;
use App\Support\Email\RegexCodeExtractor;
use App\Support\Email\RecipientParser;
use App\Support\Email\AttachmentParser;

final class GraphMessageMapper
{
    public function __construct(
        private HtmlToTextConverter $html2text,
        private RegexCodeExtractor $regexExtractor,
        private RecipientParser $recipientParser,
        private AttachmentParser $attachmentParser,
    ) {}

    public function toDomain(array $graph): EmailMessageDTO
    {
        $bodyHtml = $graph['body']['content'] ?? '';
        $bodyText = $this->html2text->convert($bodyHtml);

        [$code, $options] = $this->regexExtractor->extract($bodyText);

        $to = $this->recipientParser->extractEmails($graph['toRecipients'] ?? []);
        $cc = $this->recipientParser->extractEmails($graph['ccRecipients'] ?? []);
        $bcc = $this->recipientParser->extractEmails($graph['bccRecipients'] ?? []);

        return new EmailMessageDTO(
            id: $graph['id'],
            createdAt: new CarbonImmutable($graph['createdDateTime'] ?? 'now'),
            receivedAt: new CarbonImmutable($graph['receivedDateTime'] ?? 'now'),
            modifiedAt: new CarbonImmutable($graph['lastModifiedDateTime'] ?? 'now'),
            sentAt: new CarbonImmutable($graph['sentDateTime'] ?? 'now'),
            hasAttachments: (bool)($graph['hasAttachments'] ?? false),
            internetMessageId: $graph['internetMessageId'] ?? '',
            subject: $graph['subject'] ?? '',
            importance: $graph['importance'] ?? 'normal',
            contentType: $graph['body']['contentType'] ?? 'Text',
            bodyHtml: $bodyHtml,
            bodyText: $bodyText,
            toEmails: $to,
            ccEmails: $cc,
            bccEmails: $bcc,
            fromName: $graph['from']['emailAddress']['name'] ?? '',
            fromEmail: $graph['from']['emailAddress']['address'] ?? '',
            webLink: $graph['webLink'] ?? '',
            inferenceClassification: $graph['inferenceClassification'] ?? 'other',
            attachments: $this->attachmentParser->extractAttachments($graph['attachments'] ?? []),
            regexCode: $code,
            regexOptions: $options
        );
    }

    /**
     * Convert domain DTO back to MS Graph format for email creation
     */
    public function toGraphFormat(EmailMessageDTO $dto): array
    {
        return [
            'from' => [
                'emailAddress' => [
                    'name' => $dto->fromName,
                    'address' => $dto->fromEmail,
                ],
            ],
            'toRecipients' => array_map(fn($email) => [
                'emailAddress' => ['address' => $email]
            ], $dto->toEmails),
            'ccRecipients' => array_map(fn($email) => [
                'emailAddress' => ['address' => $email]
            ], $dto->ccEmails),
            'bccRecipients' => array_map(fn($email) => [
                'emailAddress' => ['address' => $email]
            ], $dto->bccEmails),
            'subject' => $dto->subject,
            'body' => [
                'contentType' => $dto->contentType,
                'content' => $dto->bodyHtml,
            ],
            'attachments' => $dto->attachments,
            'importance' => $dto->importance,
        ];
    }
}
