<?php 

namespace App\Filament\Components\Actions;

use Filament\Actions\Action;
use App\Filament\Components\Concerns\CanExportMaatExcel;

class ExportMaatExcelAction extends Action
{
    use CanExportMaatExcel;

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Exporter')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success');
    }
}