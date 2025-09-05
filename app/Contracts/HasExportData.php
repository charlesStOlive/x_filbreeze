<?php 

namespace App\Contracts;

use Illuminate\Support\Collection;

interface HasExportData
{
    public function getColumns(): array;

    public function getFileName(): string;

    public function getData(array $options = []): Collection;
}
