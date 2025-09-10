<?php

namespace App\Services\MaatImports\Filament\Actions;

use Filament\Schemas\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\Document\Filament\Actions\BaseDocumentAction;

class ImportMaatExcelAction extends BaseDocumentAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Importer Excel')
            ->icon('heroicon-o-cloud-arrow-up')
            ->color('primary')
            ->modalWidth('md');
    }

    protected function getServiceSchema($record = null): array
    {
        return [
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
                ->required(),

            Group::make()
                ->schema(function (callable $get) use ($record) {
                    $key = $get('template');
                    if (!$key) return [];

                    $template = $this->getTemplateInstance($key, $record);
                    return $template?->getForm() ?? [];
                })
                ->statePath('template_options')
                ->columns(1),
        ];
    }

    protected function handleAction(array $data, $record = null): mixed
    {
        try {
            // Séparer les options du fichier
            $options = $data['template_options'] ?? [];
            $file = $data['file'];

            // Créer une instance du template avec les options
            $template = $this->getTemplateInstance(
                $data['template'],
                $record,
                $options
            );

            // Utiliser Excel::import
            Excel::import($template, $file);
            
            // Appeler finalize si la méthode existe
            if (method_exists($template, 'finalize')) {
                $template->finalize();
            }

            Notification::make()
                ->title('Import réalisé avec succès')
                ->body("Import terminé")
                ->success()
                ->send();

            // Rafraîchir la table après l'import si on est dans une liste
            if ($record === null) {
                $this->getLivewire()?->dispatch('refresh');
            }

            return true;

        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur lors de l\'import')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw $e;
        }
    }

    protected function getExpectedTemplateType(): string
    {
        return \App\Services\MaatImports\Base\BaseMaatImporter::class;
    }
}
