<?php 

namespace App\Services\MaatImports\Filament\Tables;

use Filament\Tables\Actions\Action;
use App\Services\MaatImports\Filament\Traits\CanImportMaatExcel;



class ImportMaatExcelTableAction extends Action
{
    use CanImportMaatExcel;

    public static function make(?string $name = null): static
    {
        return parent::make($name);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Importer')
            ->icon('heroicon-o-cloud-arrow-up')
            ->modalWidth('md');
    }
}
