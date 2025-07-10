<?php namespace App\Services\MsGraph\EmailDraft;

use App\Models\Invoice;
use App\Models\Company;
use App\Services\MsGraph\EmailDraft\Templates\Contracts\EmailDraftTemplate;
use App\Services\MsGraph\EmailDraft\Templates\Invoice\DefaultInvoiceTemplate;
use App\Services\MsGraph\EmailDraft\Templates\Invoice\InvoiceSummaryTemplate;
use App\Services\MsGraph\EmailDraft\Templates\Company\DefaultCompanyTemplate;

class EmailDraftTemplateRegistry
{
    public static function getTemplatesFor(string $modelType): array
    {
        return match ($modelType) {
            'invoice' => [
                DefaultInvoiceTemplate::class,
                InvoiceSummaryTemplate::class,
            ],
            'company' => [
                DefaultCompanyTemplate::class,
            ],
            default => [],
        };
    }

    public static function getTemplateInstance(string $key, mixed $record): ?EmailDraftTemplate
    {
        $modelType = self::resolveModelTypeFromRecord($record);
        $allTemplates = collect(self::getTemplatesFor($modelType));

        $class = $allTemplates->first(fn($cls) => $cls::key() === $key);

        return $class ? new $class($record) : null;
    }

    protected static function resolveModelTypeFromRecord(mixed $record): string
    {
        return match (get_class($record)) {
            Invoice::class => 'invoice',
            Company::class => 'company',
            default => throw new \InvalidArgumentException('Modèle non supporté'),
        };
    }
}