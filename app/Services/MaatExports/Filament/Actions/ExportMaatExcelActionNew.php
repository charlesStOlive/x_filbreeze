<?php

namespace App\Services\MaatExports\Filament\Actions;

use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ViewField;
use App\Services\Document\Filament\Actions\BaseDocumentAction;

class ExportMaatExcelActionNew extends BaseDocumentAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Exporter Excel')
            ->icon('fas-file-excel');
    }

    protected function getServiceSchema($record): array
    {
        return [
            Grid::make(2)
                ->schema([
                    Group::make([
                        Select::make('template')
                            ->label('Format d\'export')
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
                            ->label('Aperçu des données')
                            ->view('components.fields.excel-preview')
                            ->viewData(function (callable $get) use ($record) {
                                $key = $get('template');
                                $options = $get('template_options') ?? [];

                                if (!$key) return ['data' => []];

                                $template = $this->getTemplateInstance($key, $record, $options);

                                // Prévisualisation des premières lignes
                                return [
                                    'headers' => $template->getHeaders(),
                                    'preview_data' => $template->getPreviewData(5), // 5 premières lignes
                                    'total_count' => $template->getTotalCount(),
                                ];
                            })
                            ->disabled(),
                    ])->columnSpan(1),
                ]),
        ];
    }

    protected function handleAction(array $data, $record): mixed
    {
        $template = $this->getTemplateInstance(
            $data['template'],
            $record,
            $data['template_options'] ?? []
        );

        return $template->download();
    }
}
