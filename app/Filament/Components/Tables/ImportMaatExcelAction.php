<?php 

namespace App\Filament\Components\Tables;

use Filament\Tables\Actions\Action;
use App\Filament\Components\Concerns\CanImportMaatExcel;

class ImportMaatExcelAction extends Action
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
