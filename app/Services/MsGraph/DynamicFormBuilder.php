<?php 

namespace App\Services\MsGraph;

use RuntimeException;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use App\Services\EmailsProcessorRegisterServices;

class DynamicFormBuilder
{
    /**
     * Génère un formulaire en fonction des services enregistrés et du record.
     *
     * @param string $field Nom du champ parent (e.g., `services`).
     * @param mixed $record L’enregistrement Filament actuel.
     * @return array Composants du formulaire.
     */
    public static function build(string $field, $record = null): array
    {
        $formComponents = [];
        $services = EmailsProcessorRegisterServices::getAll(); // Récupération centralisée des services

        foreach ($services as $serviceKey => $service) {
            // Récupère les options du service depuis la classe
            $options = $service['options'];
            $sectionComponents = [];

            foreach ($options as $optionKey => $option) {
                // Utilisation de la clé complète (dot notation)
                $fieldName = "{$field}.{$serviceKey}.{$optionKey}";
                \Log::info($fieldName);
                \Log::info($record->getAttribute($fieldName));

                switch ($option['type']) {
                    case 'boolean':
                        $sectionComponents[] = Toggle::make($fieldName)
                            ->label($option['label'] ?? ucfirst($optionKey))
                            ->default(fn() => $record ? $record->getAttribute($fieldName) : $option['default'] ?? null);
                        break;

                    case 'string':
                        $sectionComponents[] = TextInput::make($fieldName)
                            ->label($option['label'] ?? ucfirst($optionKey))
                            ->default(fn() => $record ? $record->getAttribute($fieldName) : $option['default'] ?? null);
                        break;

                    case 'list':
                        $sectionComponents[] = Select::make($fieldName)
                            ->label($option['label'] ?? ucfirst($optionKey))
                            ->options($option['values'] ?? [])
                            ->default(fn() => $record ? $record->getAttribute($fieldName) : $option['default'] ?? null);
                        break;

                    default:
                        throw new RuntimeException("Type de champ inconnu pour {$fieldName}: {$option['type']}.");
                }
            }

            // Ajoute une section pour ce service
            $formComponents[] = Section::make($service['label'] ?? ucfirst($serviceKey))
                ->description($service['description'] ?? null)
                ->schema($sectionComponents);
        }

        return $formComponents;
    }
}
