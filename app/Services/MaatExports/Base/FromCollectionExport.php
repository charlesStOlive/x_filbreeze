<?php 

namespace App\Services\MaatExports\Base;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;

class FromCollectionExport implements FromCollection, WithHeadings, WithColumnFormatting
{
    public function __construct(
        protected Collection $rows,
        protected array $headings,
        protected array $columnFormats = [],
    ) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function columnFormats(): array
    {
        return $this->columnFormats ?? [];
    }
}