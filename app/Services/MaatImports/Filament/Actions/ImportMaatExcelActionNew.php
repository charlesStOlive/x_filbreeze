<?php

namespace App\Services\MaatImports\Filament\Actions;

use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use App\Services\Document\Filament\Actions\BaseDocumentAction;

class ImportMaatExcelActionNew extends BaseDocumentAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Importer Excel')
            ->icon('fas-file-upload');
    }

    protected function getServiceSchema($record): array
    {
        return [
            Grid::make(2)
                ->schema([
                    Group::make([
                        Select::make('template')
                            ->label('Format d\'import')
                            ->options(
                                collect($this->getTemplatesForRecord($record))
                                    ->mapWithKeys(fn($cls) => [$cls::key() => $cls::label()])
                            )
                            ->live()
                            ->required()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) use ($record) {
                                $templateClass = collect($this->getTemplatesForRecord($record))
                                    ->first(fn($cls) => $cls::key() === $state);

                                if ($templateClass) {
                                    $set('template_options', $templateClass::getDefaultOptions());
                                }
                            }),

                        FileUpload::make('file')
                            ->label('Fichier Excel')
                            ->acceptedFileTypes([
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'text/csv'
                            ])
                            ->required()
                            ->live(),

                        Group::make()
                            ->schema(function (callable $get) use ($record) {
                                $key = $get('template');
                                if (!$key) return [];

                                $template = $this->getTemplateInstance($key, $record);
                                return $template?->getForm() ?? [];
                            })
                            ->statePath('template_options')
                            ->columns(1),
                    ])->columnSpan(1),

                    Group::make([
                        ViewField::make('preview')
                            ->label('Aperçu du mapping')
                            ->view('components.fields.import-preview')
                            ->viewData(function (callable $get) use ($record) {
                                $key = $get('template');
                                $file = $get('file');

                                if (!$key || !$file) return ['mapping' => []];

                                $template = $this->getTemplateInstance($key, $record);

                                return [
                                    'expected_columns' => $template->getExpectedColumns(),
                                    'mapping_rules' => $template->getMappingRules(),
                                    'validation_rules' => $template->getValidationRules(),
                                ];
                            })
                            ->disabled(),
                    ])->columnSpan(1),
                ]),
        ];
    }

    protected function handleAction(array $data, $record): mixed
    {
        try {
            $template = $this->getTemplateInstance(
                $data['template'],
                $record,
                $data['template_options'] ?? []
            );

            $result = $template->import($data['file']);

            Notification::make()
                ->title('Import réalisé avec succès')
                ->body("{$result['imported']} lignes importées, {$result['errors']} erreurs")
                ->success()
                ->send();

            return $result;
        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur lors de l\'import')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw $e;
        }
    }
}
