<?php

namespace App\Services\MaatExports\Filament\Actions;

use Filament\Schemas\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use App\Services\Document\Filament\Actions\BaseDocumentListAction;

class ExportMaatExcelListAction extends BaseDocumentListAction
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

    protected function getServiceSchema(): array
    {
        return [
            Select::make('template')
                ->label('Format d\'export')
                ->options(
                    collect($this->getTemplates())
                        ->mapWithKeys(fn($cls) => [$cls::key() => $cls::label()])
                )
                ->live()
                ->required()
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    $templateClass = collect($this->getTemplates())
                        ->first(fn($cls) => $cls::key() === $state);

                    if ($templateClass) {
                        $set('template_options', $templateClass::getDefaultOptions());
                    }
                }),

            Group::make()
                ->schema(function (callable $get) {
                    $key = $get('template');
                    if (!$key) return [];

                    $template = $this->getTemplateInstance($key);
                    return $template?->getForm() ?? [];
                })
                ->statePath('template_options')
                ->columns(1),
        ];
    }

    protected function handleAction(array $data): mixed
    {
        $template = $this->getTemplateInstance(
            $data['template'],
            $data['template_options'] ?? []
        );

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

    protected function cleanOldExports(): void
    {
        $files = Storage::disk('public')->files('exports');

        foreach ($files as $file) {
            if (Storage::disk('public')->lastModified($file) < now()->subHour()->timestamp) {
                Storage::disk('public')->delete($file);
            }
        }
    }
}
