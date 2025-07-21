<?php 

namespace App\Contracts;

interface HasExportData
{
    public function getColumns(): array;

    public function getFileName(): string;

    public function getData(array $options = []): \Illuminate\Support\Collection;
}
