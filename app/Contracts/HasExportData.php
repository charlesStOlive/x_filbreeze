<?php 

namespace App\Contracts;

interface HasExportData
{
    public static function getColumns(): array;

    public static function getFileName(): string;

    public static function getData(array $options = []): \Illuminate\Support\Collection;
}
