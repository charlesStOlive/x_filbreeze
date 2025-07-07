<?php

namespace App\Filament\Components\Concerns;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use App\Services\Exports\FromCollectionExport;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification as FilamentNotification;

trait CanExportMaatExcel
{
    protected ?string $maatExporterClass = null;

    public function exporter(string $exporterClass): static
    {
        $this->maatExporterClass = $exporterClass;

         $this->action(function (array $data) use ($exporterClass) {
            $filename = now()->format('Ymd_His') . '_' . Str::random(8) . '.xlsx';
            $path = 'exports/' . $filename;

            $columns = $exporterClass::getColumns();
            $columnFormats = method_exists($exporterClass, 'getColumnFormats')
                ? $exporterClass::getColumnFormats()
                : [];

            $rows = $exporterClass::getData($data)->map(fn($item) => collect($columns)->keys()->map(
                fn($key) => data_get($item, $key)
            ));

            Excel::store(new FromCollectionExport(
                $rows,
                array_values($columns),
                $columnFormats,
            ), $path, 'local');

            $this->cleanOldExports();

            // Génère le lien signé avec nom "affiché"
            $url = URL::temporarySignedRoute('exports.download', now()->addHour(), [
                'filename' => $filename,
                'display' => $exporterClass::getFileName(),
            ]);

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

    protected function cleanOldExports(): void
    {
        $files = Storage::disk('local')->files('exports');

        foreach ($files as $file) {
            if (Storage::disk('local')->lastModified($file) < now()->subHour()->timestamp) {
                Storage::disk('local')->delete($file);
            }
        }
    }
}
