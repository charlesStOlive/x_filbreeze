<?php

namespace App\Services\Pdf\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\ViewField;
use App\Services\Pdf\Base\BasePdfTemplate;
use App\Services\Document\Filament\Actions\BaseDocumentAction;

class GeneratePdfDownload extends BaseDocumentAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Créer PDF')
            ->icon('fas-file-pdf');
    }

    protected function getServiceSchema($record = null): array
    {
        return [
            Grid::make(4)
                ->schema([
                    Group::make([
                        Select::make('template')
                            ->label('Modèle de PDF')
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
                            })
                            ->suffixAction(
                                Action::make('preview')
                                    ->icon('heroicon-o-eye')
                                    ->label('Aperçu')
                                    ->tooltip('Ouvrir l\'aperçu du template')
                                    ->url(function (callable $get) use ($record) {
                                        $template = $get('template');
                                        if (!$template || !$record) {
                                            return null;
                                        }
                                        
                                        // Extraire le nom du template (après 'pdf_')
                                        $templateName = str_replace('pdf_', '', $template);
                                        
                                        // Génerer l'URL avec le nom du template
                                        return route('filament.admin.crm.resources.quotes.preview-pdf', [
                                            'record' => $record->id,
                                            'template' => $templateName
                                        ]);
                                    })
                                    ->openUrlInNewTab()
                                    ->disabled(fn(callable $get) => !$get('template'))
                            ),

                        Group::make()
                            ->schema(function (callable $get) use ($record) {
                                $key = $get('template');
                                $options = $get('template_options') ?? [];

                                $template = $this->getTemplateInstance($key, $record, $options);

                                return $template?->getForm() ?? [];
                            })
                            ->statePath('template_options')
                            ->columns(1),
                    ])->columnSpan(1),

                    Group::make([
                        ViewField::make('body')
                            ->label('Aperçu HTML')
                            ->view('components.fields.pdf-preview')
                            ->viewData(fn($get) => BasePdfTemplate::getPreviewData($get, $this->getRecord()))
                            ->disabled(),
                    ])->columnSpan(3),
                ]),
        ];
    }

    protected function handleAction(array $data, $record = null): mixed
    {
        $template = $this->getTemplateInstance(
            $data['template'],
            $record,
            $data['template_options'] ?? []
        );

        return $template->download();
    }

    protected function getExpectedTemplateType(): string
    {
        return \App\Services\Pdf\Base\BasePdfTemplate::class;
    }
}
