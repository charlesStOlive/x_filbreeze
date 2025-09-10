<?php

namespace App\Services\MaatExports\Filament\Actions;

use Filament\Schemas\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use App\Services\Document\Filament\Actions\BaseDocumentAction;

class ExportMaatExcelAction extends BaseDocumentAction
{
    protected function setUp(): void
    {
        parent::setUp();
        
        $this
            ->label('Exporter Excel')
            ->icon('heroicon-o-cloud-arrow-down')
            ->color('success')
            ->modalWidth('md');
    }

    protected function getServiceSchema($record = null): array
    {
        return [
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
        ];
    }

    protected function handleAction(array $data, $record = null): mixed
    {
        $template = $this->getTemplateInstance(
            $data['template'],
            $record,
            $data['template_options'] ?? []
        );

        // Si pas de record spécifique, on utilise generateFile (pour les listes)
        if ($record === null) {
            $generated = $template->generateFile($data['template_options'] ?? []);

            $this->cleanOldExports();

            $url = Storage::disk('public')->url('exports/' . basename($generated->path));

            Notification::make()
                ->title('Export terminé')
                ->success()
                ->body('Votre fichier est prêt à être téléchargé.')
                ->actions([
                    Action::make('download')
                        ->label('Télécharger')
                        ->url($url, true)
                        ->color('success')
                        ->markAsRead(),
                ])
                ->send();

            return $generated;
        }

        // Si on a un record spécifique, on utilise download direct
        return $template->download();
    }

    protected function cleanOldExports(): void
    {
        $files = Storage::disk('public')->files('exports');

        foreach ($files as $file) {
            if (Storage::disk('public')->lastModified($file) < now()->subHour()->timestamp) {
                Storage::disk('public')->delete($file);
            }
        }
    }

    protected function getExpectedTemplateType(): string
    {
        return \App\Services\MaatExports\Base\BaseExcelTemplate::class;
    }
}