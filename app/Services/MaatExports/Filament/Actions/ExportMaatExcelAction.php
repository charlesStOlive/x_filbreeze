<?php 

namespace App\Services\MaatExports\Filament\Actions;

use Filament\Actions\Action;
use App\Services\MaatExports\Filament\Traits\CanExportMaatExcel;

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