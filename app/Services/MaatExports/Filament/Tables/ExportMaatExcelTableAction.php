<?php 

namespace App\Services\MaatExports\Filament\Tables;

use Filament\Actions\Action;
use App\Services\MaatExports\Filament\Traits\CanExportMaatExcel;

class ExportMaatExcelTableAction extends Action
{
    use CanExportMaatExcel;

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Exporter')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray');
    }
}