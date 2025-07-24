<?php 

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
            ->modalWidth('7xl')
            ->fillForm(function ($record) {
                $template = PdfTemplateRegistry::getDefaultTemplateInstance($record);
                $templateClass = get_class($template);

                return [
                    'template' => $templateClass::key(),
                    'template_options' => [], // on laisse vide, ce sera géré par le form
                ];
            })
            ->form(fn ($record) => [
                Forms\Components\Grid::make(4)
                    ->schema([
                        Forms\Components\Group::make([
                            Forms\Components\Select::make('template')
                                ->label('Modèle de PDF')
                                ->options(
                                    collect(PdfTemplateRegistry::getTemplatesFor(
                                        PdfTemplateRegistry::resolveModelTypeFromRecord($record)
                                    ))->mapWithKeys(fn ($cls) => [$cls::key() => $cls::label()])
                                )
                                ->live()
                                ->required()
                                ->afterStateUpdated(function ($state, callable $set, $get) use ($record) {
                                    $templateClass = collect(PdfTemplateRegistry::getTemplatesFor(
                                        PdfTemplateRegistry::resolveModelTypeFromRecord($record)
                                    ))->first(fn ($cls) => $cls::key() === $state);

                                    if ($templateClass) {
                                        // Remettre les options à zéro pour forcer le rechargement du formulaire
                                        $set('template_options', []);
                                    }
                                }),

                            Forms\Components\Group::make()
                                ->schema(function (callable $get) use ($record) {
                                    $key = $get('template');
                                    $templateClass = collect(PdfTemplateRegistry::getTemplatesFor(
                                        PdfTemplateRegistry::resolveModelTypeFromRecord($record)
                                    ))->first(fn ($cls) => $cls::key() === $key);

                                    return $templateClass
                                        ? (new $templateClass($record))->getForm()
                                        : [];
                                })
                                ->statePath('template_options')
                                ->columns(1),
                        ])->columnSpan(1),

                        Forms\Components\Group::make([
                            Forms\Components\ViewField::make('body')
                                ->label('Aperçu HTML')
                                ->view('components.fields.pdf-preview')
                                ->viewData(fn ($get) => BasePdfTemplate::getPreviewData($get, $this->getRecord()))
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
