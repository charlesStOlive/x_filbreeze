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
     * @param mixed $record L’enregistrement Filament actuel.
     * @param string $serviceType Type de service à traiter (par exemple `email-in`, `email-draft`).
     * @param string $field Nom du champ parent (par exemple, `services_in`, `services_draft`).
     * @return array Composants du formulaire.
     */
    public static function build($record = null, string $serviceType, string $field): array
    {
        $formComponents = [];
        $services = EmailsProcessorRegisterServices::getAll($serviceType); // Récupération des services enregistrés

        foreach ($services as $serviceKey => $service) {
            $options = $service['options'];
            $sectionComponents = [];

            // Ajout du champ "mode" qui est toujours visible
            $modeFieldName = "{$field}.{$serviceKey}.mode";
            $modeOptions = $options['mode']['values'] ?? [
                'inactif' => 'Inactif',
                'actif' => 'Active',
                'test' => 'Test',
            ];

            $sectionComponents[] = Select::make($modeFieldName)
                ->label($options['mode']['label'] ?? 'Mode')
                ->options($modeOptions)
                ->reactive()
                ->default(fn() => $record ? $record->getAttribute($modeFieldName) : $options['mode']['default'] ?? 'inactif');

            // Ajout des autres options (visibilité conditionnelle)
            foreach ($options as $optionKey => $option) {
                if ($optionKey === 'mode') {
                    continue; // Ignorer "mode" ici, car il est déjà traité
                }

                $fieldName = "{$field}.{$serviceKey}.{$optionKey}";

                switch ($option['type']) {
                    case 'boolean':
                        $sectionComponents[] = Toggle::make($fieldName)
                            ->label($option['label'] ?? ucfirst($optionKey))
                            ->default(fn() => $record ? $record->getAttribute($fieldName) : $option['default'] ?? null)
                            ->visible(fn($get) => $get($modeFieldName) !== 'inactif');
                        break;

                    case 'string':
                        $sectionComponents[] = TextInput::make($fieldName)
                            ->label($option['label'] ?? ucfirst($optionKey))
                            ->default(fn() => $record ? $record->getAttribute($fieldName) : $option['default'] ?? null)
                            ->visible(function($get) use ($modeFieldName) {
                                return $get($modeFieldName) !== 'inactif';
                            }) ;
                        break;

                    case 'list':
                        $sectionComponents[] = Select::make($fieldName)
                            ->label($option['label'] ?? ucfirst($optionKey))
                            ->options($option['values'] ?? [])
                            ->default(fn() => $record ? $record->getAttribute($fieldName) : $option['default'] ?? null)
                            ->visible(fn($get) => $get($modeFieldName) !== 'inactif');
                        break;

                    default:
                        throw new RuntimeException("Type de champ inconnu pour {$fieldName}: {$option['type']}.");
                }
            }

            // Ajout de la section pour ce service
            $formComponents[] = Section::make($service['label'] ?? ucfirst($serviceKey))
                ->description($service['description'] ?? null)
                ->schema($sectionComponents);
        }

        return $formComponents;
    }
}
