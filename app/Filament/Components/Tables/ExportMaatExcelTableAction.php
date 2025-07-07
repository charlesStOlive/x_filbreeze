<?php 

namespace App\Filament\Components\Tables;

use Filament\Tables\Actions\Action;
use App\Filament\Components\Concerns\CanExportMaatExcel;

class ExportMaatExcelTableAction extends Action
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