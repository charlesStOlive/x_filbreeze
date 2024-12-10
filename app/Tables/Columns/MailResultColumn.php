<?php 

namespace App\Tables\Columns;

use Filament\Tables\Columns\Column;
use App\Services\EmailsProcessorRegisterServices;

class MailResultColumn extends Column
{
    protected string $view = 'filament.tables.columns.mail-result-column';
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
     * Transmet les données formatées à la vue.
     */
    public function getState(): mixed
    {
        $record = $this->getRecord();
        $services = EmailsProcessorRegisterServices::getAll($this->serviceType);

        $data = [];

        foreach ($services as $serviceKey => $service) {
            $mode = $record->getAttribute("services_options.{$serviceKey}.mode") ?? 'inactif';

            if (in_array($mode, ['actif', 'test'])) {
                $success = $record->getAttribute("services_results.{$serviceKey}.success") ?? false;

                $results = $this->prepareResults($serviceKey, $record, $service, $success);

                if (!empty($results)) {
                    $data[] = [
                        'label' => $service['label'],
                        'mode' => ucfirst($mode),
                        'success' => $success,
                        'results' => $results,
                    ];
                }
            }
        }

        return $data;
    }

    /**
     * Prépare les résultats selon le statut `success`.
     */
    protected function prepareResults(string $serviceKey, $record, array $service, bool $success): array
    {
        $results = [];

        if ($success) {
            // Mappage des résultats uniquement si `success` est `true`
            foreach ($service['results'] as $resultKey => $result) {

                $resultValue = $record->getAttribute("services_results.{$serviceKey}.{$resultKey}") ?? null;
                // Vérifications supplémentaires : exclusion des champs "hidden" et des erreurs nulles
                if (($resultKey === 'errors' || $resultKey === 'reason') && !$resultValue) {
                    continue;
                }

                if (!empty($result['hidden'])) {
                    continue;
                }

                

                // Conversion des tableaux en chaînes JSON
                if (is_array($resultValue)) {
                    $resultValue = json_encode($resultValue);
                }

                $results[] = [
                    'label' => $result['label'] ?? ucfirst($resultKey),
                    'value' => $resultValue,
                ];
            }
        } else {
            // Inclure uniquement `reason` si `success` est `false`
            $reason = $record->getAttribute("services_results.{$serviceKey}.reason") ?? null;

            // Vérifications supplémentaires
            if ($reason !== null) {
                $results[] = [
                    'label' => 'Raison',
                    'value' => $reason,
                ];
            }
        }

        return $results;
    }
}
