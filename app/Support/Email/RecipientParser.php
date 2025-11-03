<?php

namespace App\Support\Email;

final class RecipientParser
{
    /**
     * Extract recipient names from MS Graph recipients array
     */
    public function extractNames(array $recipients): array
    {
        return array_map(fn($recipient) => $recipient['emailAddress']['name'] ?? '', $recipients);
    }

    /**
     * Extract recipient email addresses from MS Graph recipients array
     */
    public function extractEmails(array $recipients): array
    {
        return array_map(fn($recipient) => $recipient['emailAddress']['address'] ?? '', $recipients);
    }

    /**
     * Extract domains from email addresses
     */
    public function extractDomains(array $emails): array
    {
        return array_unique(array_map(fn($email) => $this->extractDomainFromEmail($email), $emails));
    }

    /**
     * Extract the domain from an email address
     */
    public function extractDomainFromEmail(string $email): string
    {
        $parts = explode('@', $email);
        return $parts[1] ?? '';
    }

    /**
     * Format emails into MS Graph recipients format
     */
    public function formatRecipientsFromEmails(array $emails): array
    {
        return array_map(fn($email) => [
            'emailAddress' => ['address' => $email],
        ], $emails);
    }
}
