<?php

namespace App\Services\MaatExports\Filament\Traits;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use App\Services\MaatExports\Base\FromCollectionExport;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification as FilamentNotification;

trait CanExportMaatExcel
{
    protected ?string $maatExporterClass = null;
    protected mixed $maatExporterRecord = null;

    public function exporter(string $exporterClass): static
    {
        $this->maatExporterClass = $exporterClass;

        // Auto-fill form with default options if available
        $this->fillForm(function () use ($exporterClass) {
            return [];
        });


        // Dynamically build form schema if getForm() exists
        $this->form(function () use ($exporterClass) {
            $exporter = app()->make($exporterClass, [
                'record' => $this->maatExporterRecord,
                'options' => [], // on laisse les defaults être gérés par le constructeur
            ]);

            return method_exists($exporter, 'getForm') ? $exporter->getForm() : [];
        });

        $this->action(function (array $data) use ($exporterClass) {
            $exporter = app()->make($exporterClass, [
                'record' => $this->maatExporterRecord,
                'options' => $data,
            ]);

            $generated = $exporter->generateFile($data);

            $this->cleanOldExports();

            $url = Storage::disk('public')->url('exports/' . basename($generated->path));

            FilamentNotification::make()
                ->title('Export terminé')
                ->success()
                ->body('Votre fichier est prêt à être téléchargé.')
                ->actions([
                    NotificationAction::make('download')
                        ->label('Télécharger')
                        ->url($url, true)
                        ->color('success')
                        ->markAsRead(),
                ])
                ->send();
        });

        return $this;
    }

    public function withRecord(mixed $record): static
    {
        $this->maatExporterRecord = $record;
        return $this;
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
