<?php

namespace App\Services\Pdf\Filament\Actions;

use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ViewField;
use Filament\Forms;
use Filament\Actions\Action;
use App\Services\Pdf\Base\BasePdfTemplate;
use App\Services\Pdf\Base\PdfTemplateRegistry;

class GeneratePdfDownload extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Créer PDF')
            ->icon('fas-file-pdf')
            ->modalWidth('7xl')
            ->fillForm(function ($record) {
                $template = PdfTemplateRegistry::getDefaultTemplateInstance($record);
                $templateClass = get_class($template);

                return [
                    'template' => $templateClass::key(),
                    'template_options' => $templateClass::getDefaultOptions(), // ✅ injecte les valeurs par défaut
                ];
            })
            ->schema(fn($record) => [
                Grid::make(4)
                    ->schema([
                        Group::make([
                            Select::make('template')
                                ->label('Modèle de PDF')
                                ->options(
                                    collect(PdfTemplateRegistry::getTemplatesFor(
                                        PdfTemplateRegistry::resolveModelTypeFromRecord($record)
                                    ))->mapWithKeys(fn($cls) => [$cls::key() => $cls::label()])
                                )
                                ->live()
                                ->required()
                                ->afterStateUpdated(function ($state, callable $set, callable $get) use ($record) {
                                    $templateClass = collect(PdfTemplateRegistry::getTemplatesFor(
                                        PdfTemplateRegistry::resolveModelTypeFromRecord($record)
                                    ))->first(fn($cls) => $cls::key() === $state);

                                    if ($templateClass) {
                                        $set('template_options', $templateClass::getDefaultOptions());
                                    }
                                }),

                            Group::make()
                                ->schema(function (callable $get) use ($record) {
                                    $key = $get('template');
                                    $options = $get('template_options') ?? [];

                                    $template = PdfTemplateRegistry::getTemplateInstance($key, $record, $options);

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
            ])
            ->action(function (array $data, $record) {
                $template = PdfTemplateRegistry::getTemplateInstance(
                    $data['template'],
                    $record,
                    $data['template_options'] ?? []
                );

                return $template->download();
            });
    }
}
