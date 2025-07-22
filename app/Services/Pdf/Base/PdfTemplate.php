<?php 

namespace App\Services\Pdf\Base;

interface PdfTemplate
{
    public static function key(): string;
    public static function label(): string;

    public function getView(): string;
    public function getData(array $options = []): array;

    public static function getForm(array $defaults = []): array;
    public static function getDefaultOptions(): array;
    public function getFileName(array $options = []): string;
}
