<?php

namespace App\Support\Email;

final class AttachmentParser
{
    /**
     * Extract attachment details from MS Graph attachments array
     */
    public function extractAttachments(array $attachments): array
    {
        return array_map(function ($attachment) {
            return [
                'name' => $attachment['name'] ?? '',
                'contentType' => $attachment['contentType'] ?? '',
                'size' => $attachment['size'] ?? 0,
                'id' => $attachment['id'] ?? '',
            ];
        }, $attachments);
    }
}
