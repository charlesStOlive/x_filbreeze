<?php 

namespace App\Tables\Columns;

use Filament\Tables\Columns\Column;
use App\Services\EmailsProcessorRegisterServices;

class MailServiceColumn extends Column
{
    protected string $view = 'tables.columns.mail-service-column';
    protected string $serviceType;

    /**
     * Définit le type de service (`services_in` ou `services_draft`).
     */
    public function serviceType(string $type): static
    {
        $this->serviceType = $type;
        return $this;
    }

    /**
     * Transmet les données à la vue via `getState`.
     */
    public function getState(): mixed
    {
        $record = $this->getRecord();
        $services = EmailsProcessorRegisterServices::getAll($this->serviceType);

        $data = [];

        foreach ($services as $serviceKey => $service) {
            $mode = $record->getAttribute("{$this->getName()}.{$serviceKey}.mode") ?? 'inactif';
            if ($mode !== 'inactif') {
                $options = $this->getOptions($serviceKey, $record, $service);
                $data[] = [
                    'label' => $service['label'],
                    'mode' => ucfirst($mode),
                    'options' => $options,
                ];
            }
        }

        return $data;
    }

    /**
     * Récupère les options des services.
     */
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
