<?php

namespace App\Services\Email\Services;

use App\Enums\EmailProcessing\{ProcessorStatus, EmailStatus};

final class EmailStatusCalculator
{
    /**
     * Compute the global email status based on active jobs, errors, and service results
     */
    public function compute(int $activeJobs, bool $hasError, array $servicesResults): string
    {
        // S'il y a des jobs actifs, on reste en processing
        if ($activeJobs > 0) {
            return EmailStatus::Processing->value;
        }

        // S'il y a une erreur globale
        if ($hasError) {
            return EmailStatus::Error->value;
        }

        // Toujours End si pas d'erreur globale et plus de jobs actifs
        return EmailStatus::End->value;
    }
}
