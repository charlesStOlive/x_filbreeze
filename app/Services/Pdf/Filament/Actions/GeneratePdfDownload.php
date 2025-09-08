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
    protected ?array $templates = null;

    public function templates(array $templates): static
    {
        $this->templates = $templates;
        return $this;
    }

    protected function getTemplatesForRecord($record): array
    {
        // Si des templates sont définis explicitement, les utiliser
        if ($this->templates !== null) {
            return $this->templates;
        }

        // Sinon, utiliser le système de registre existant
        return PdfTemplateRegistry::getTemplatesFor(
            PdfTemplateRegistry::resolveModelTypeFromRecord($record)
        );
    }

    protected function getDefaultTemplateForRecord($record): string
    {
        $availableTemplates = $this->getTemplatesForRecord($record);
        
        if ($this->templates !== null) {
            // Si des templates sont définis explicitement, prendre le premier
            return $availableTemplates[0] ?? null;
        }

        // Sinon, utiliser le système de registre existant
        return PdfTemplateRegistry::getDefaultTemplateFor(
            PdfTemplateRegistry::resolveModelTypeFromRecord($record)
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Créer PDF')
            ->icon('fas-file-pdf')
            ->modalWidth('7xl')
            ->fillForm(function ($record) {
                $availableTemplates = $this->getTemplatesForRecord($record);
                $defaultTemplate = $this->getDefaultTemplateForRecord($record);
                
                if (empty($availableTemplates)) {
                    throw new \RuntimeException('Aucun template disponible pour ce type d\'enregistrement');
                }

                $templateClass = $defaultTemplate ?? $availableTemplates[0];
                
                return [
                    'template' => $templateClass::key(),
                    'template_options' => $templateClass::getDefaultOptions(),
                ];
            })
            ->schema(fn($record) => [
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
