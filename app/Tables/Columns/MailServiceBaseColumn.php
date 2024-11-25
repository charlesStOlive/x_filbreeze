<?php 

namespace App\Tables\Columns;

use Filament\Tables\Columns\Column;
use App\Services\EmailsProcessorRegisterServices;

abstract class MailServiceBaseColumn extends Column
{
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
    public function getState() :mixed
    {
        return $this->getTableData();
    }

    /**
     * Récupère les données pour la colonne.
     */
    protected function getTableData(): array
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
     * Récupère les options spécifiques à chaque colonne.
     */
    abstract protected function getOptions(string $serviceKey, $record, array $service): array;
}
