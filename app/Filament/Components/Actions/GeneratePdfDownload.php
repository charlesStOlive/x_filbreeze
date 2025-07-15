<?php

// Exemple adapté pour layout avec 1/4 form et 3/4 preview

namespace App\Filament\Components\Actions;

use Filament\Actions\Action;
use Filament\Forms;
use Illuminate\Support\Str;
use App\Services\Pdf\PdfRenderer;
use App\Services\Pdf\PdfTemplateRegistry;
use Spatie\Browsershot\Browsershot;

class GeneratePdfDownload extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Créer PDF')
            ->requiresConfirmation()
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
                                ->viewData(function (callable $get, $record) {
                                    $templateKey = $get('template');
                                    $options = $get('template_options') ?? [];
                                    $templateClass = collect(PdfTemplateRegistry::getTemplatesFor(
                                        PdfTemplateRegistry::resolveModelTypeFromRecord($record)
                                    ))->first(fn($cls) => $cls::key() === $templateKey);

                                    if (! $templateClass) return ['html' => '<p>Template introuvable</p>'];

                                    $template = new $templateClass($record);
                                    $html = app(PdfRenderer::class)->render($template, $options);

                                    return ['html' => $html];
                                })
                                ->disabled(),
                        ])->columnSpan(3),
                    ]),
            ])
            ->action(function (array $data, $record) {
                $template = PdfTemplateRegistry::getTemplateInstance($data['template'], $record);
                $options = $data['template_options'] ?? [];
                $html = app(PdfRenderer::class)->render($template, $data['template_options'] ?? []);

                $filename = $template->getFileName($options);
                if (empty($filename)) {
                    $filename = 'pdf_' . now()->format('Ymd_His') . '_' . Str::random(6);
                }
                $filename .= '.pdf';

                $path = storage_path('app/public/' . $filename);
                $path = storage_path('app/public/' . $filename);

                Browsershot::html($html)
                    ->format('A4')
                    ->scale(0.75)
                    ->margins(25, 25, 25, 25, 'px')
                    ->emulateMedia('screen')
                    ->showBackground()
                    ->savePdf($path);

                return response()->download($path)->deleteFileAfterSend(true);
            });
    }
}