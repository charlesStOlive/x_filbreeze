<?php 

namespace App\Services\MsGraph;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;

class DynamicFormBuilder
{
    /**
     * Génère le formulaire en fonction des services configurés et du record.
     *
     * @param array $servicesConfig Configuration des services.
     * @param mixed $record Enregistrement courant.
     * @return array Liste des composants du formulaire.
     */
    public static function build(array $servicesConfig, $record = null): array
    {
        $formComponents = [];

        foreach ($servicesConfig as $serviceKey => $service) {
            if (!isset($service['options']) || !is_array($service['options'])) {
                continue; // Ignore les services mal formés
            }

            // Composants de la section courante
            $sectionComponents = [];

            foreach ($service['options'] as $optionKey => $option) {
                $fieldName = "{$serviceKey}_{$optionKey}";

                switch ($option['type']) {
                    case 'boolean':
                        $sectionComponents[] = Toggle::make($fieldName)
                            ->label($option['label'] ?? ucfirst($optionKey))
                            ->default(fn() => $record ? $record->{$fieldName} : ($option['default'] ?? false));
                        break;

                    case 'string':
                        $sectionComponents[] = TextInput::make($fieldName)
                            ->label($option['label'] ?? ucfirst($optionKey))
                            ->default(fn() => $record ? $record->{$fieldName} : ($option['default'] ?? ''));
                        break;

                    case 'list':
                        $sectionComponents[] = Select::make($fieldName)
                            ->label($option['label'] ?? ucfirst($optionKey))
                            ->options($option['values'] ?? [])
                            ->default(fn() => $record ? $record->{$fieldName} : ($option['default'] ?? null));
                        break;

                    default:
                        // Log ou ignorer les types inconnus
                        break;
                }
            }

            // Ajouter la section avec les composants
            $formComponents[] = Section::make($service['label'] ?? ucfirst($serviceKey))
                ->description($service['description'] ?? null)
                ->schema($sectionComponents);
        }

        return $formComponents;
    }
}
