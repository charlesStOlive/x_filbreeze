<?php 

namespace App\Tables\Columns;

class MailServiceColumn extends MailServiceBaseColumn
{
    protected string $view = 'tables.columns.mail-service-column';

    protected function getOptions(string $serviceKey, $record, array $service): array
    {
        $options = [];

        

        foreach ($service['options'] as $optionKey => $option) {
            if ($optionKey !== 'mode') {
                $value = $record->getAttribute("{$this->getName()}.{$serviceKey}.{$optionKey}") ?? null;
                $options[] = [
                    'label' => $option['label'] ?? ucfirst($optionKey),
                    'value' => $value,
                ];
            }
        }

        return $options;
    }
}
