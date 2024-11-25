<?php 

namespace App\Tables\Columns;

class MailResultColumn extends MailServiceBaseColumn
{
    protected string $view = 'tables.columns.mail-result-column';

    protected function getOptions(string $serviceKey, $record, array $service): array
    {
        $options = [];

        foreach ($service['results'] as $optionKey => $option) {
            if (!in_array($optionKey, ['success', 'reason'])) {
                $value = $record->getAttribute("results.{$serviceKey}.{$optionKey}") ?? null;
                $options[] = [
                    'label' => $option['label'] ?? ucfirst($optionKey),
                    'value' => $value,
                ];
            }
        }

        return $options;
    }
}
