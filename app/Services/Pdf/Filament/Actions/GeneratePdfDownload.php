<?php

// Exemple adapté pour layout avec 1/4 form et 3/4 preview

namespace App\Services\Pdf\Filament\Actions;

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
            ->modalWidth('7xl') // maximise la modal
            ->fillForm(function ($record) {
                $template = PdfTemplateRegistry::getDefaultTemplateInstance($record);
                $templateClass = get_class($template);
                $options = $templateClass::getDefaultOptions();

                return [
                    'template' => $templateClass::key(),
                    'template_options' => $options,
                ];
            })
            ->form(fn($record) => [
                Forms\Components\Grid::make(4)
                    ->schema([
                        Forms\Components\Group::make([
                            Forms\Components\Select::make('template')
                                ->label('Modèle de PDF')
                                ->options(
                                    collect(PdfTemplateRegistry::getTemplatesFor(
                                        PdfTemplateRegistry::resolveModelTypeFromRecord($record)
                                    ))->mapWithKeys(fn($cls) => [$cls::key() => $cls::label()])
                                )
                                ->live()
                                ->required(),

                            Forms\Components\Group::make()
                                ->schema(function (callable $get, $record) {
                                    $key = $get('template');
                                    $templateClass = collect(PdfTemplateRegistry::getTemplatesFor(
                                        PdfTemplateRegistry::resolveModelTypeFromRecord($record)
                                    ))->first(fn($cls) => $cls::key() === $key);

                                    return $templateClass
                                        ? $templateClass::getForm($templateClass::getDefaultOptions())
                                        : [];
                                })
                                ->statePath('template_options')
                                ->columns(1),
                        ])->columnSpan(1),

                        Forms\Components\Group::make([
                            Forms\Components\ViewField::make('body')
                                ->label('Aperçu HTML')
                                ->view('components.fields.pdf-preview')
                                ->viewData(fn($get, $record) => BasePdfTemplate::getPreviewData($get, $record))
                                ->disabled(),
                        ])->columnSpan(3),
                    ]),
            ])
            ->action(function (array $data, $record) {
                $template = PdfTemplateRegistry::getTemplateInstance($data['template'], $record);
                return $template->download($data['template_options'] ?? []);
            });
    }
}